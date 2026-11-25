# Pixelforge Studio architecture

Through Phase 7: provider submission, signed webhooks, atomic settlement, bounded
recovery, Studio/gallery/Cost Inspector, and admin financial reporting. Deployment
exists; live financial verification is deferred to Phase 9. Wallet-funded generation starts at Phase 7 rollout.

## Boundaries and responsibilities

| Area                   | Responsibility                                                                    |
| ---------------------- | --------------------------------------------------------------------------------- |
| Identity               | Existing Fortify authentication; Policies/Gates for ownership/admin access        |
| Generation             | Local identity, immutable submission intent, provider correlation, history/status |
| Pricing and settlement | Estimates, frozen provider rates, exact cost and customer charge                  |
| Provider integration   | Typed DTOs, HTTP transport, signed webhook ingress, bounded recovery              |
| Optional finances      | Wallet ledger and admin reconciliation after required flows work                  |

Use domain groupings inside conventional Laravel directories as features grow.
Thin controllers coordinate Form Requests, Policies, Actions, and responses.
Prefer invokable controllers for distinct writes and resource controllers for
cohesive CRUD. Actions own workflows; services own reusable calculations/state
rules; Jobs execute asynchronous steps; Scheduler finds stalled work. HTTP
adapters only translate external contracts. Eloquent owns persistence and
relationships. Inject a provider contract at the external boundary. Events
are justified by actual consumers, not used to hide workflow ordering.

## Persistence and transactions

Persist local identity, immutable request data, and a stable idempotency key
before network submission. Commit first; dispatch after commit. Recovery must
find committed pending work even if dispatch fails. Never hold a transaction
open across an HTTP request.

Use short transactions, row locks where supported, and SQL unique/check/foreign
key constraints for critical invariants. Provider IDs and webhook event IDs
must be unique within their provider/account scope; a generation has at most
one settlement. The state transition, settlement, and optional ledger changes
commit atomically. Do not rely on queue uniqueness or cache locks alone.
Keep provider lifecycle separate from local submission/retry/settlement state.

## Provider and webhook contracts

The [official reference](https://pixelforge.promptedit.dev/docs) specifies:

- Create accepts `prompt`, `size`, `quality`, `reference_images`, and `metadata`.
  Correlate a pre-persisted local identity through metadata. Use the same
  `Idempotency-Key` and exact parameters after ambiguous failures; account-scoped
  keys never expire. Do not create a replacement just because a request timed out.
- Generation objects contain frozen `rates`; successful ones contain `usage`,
  not a price. Use documented pricing data for estimates, never hardcoded rates.
- Verify `Pixelforge-Signature` with HMAC-SHA256 over `timestamp.raw-body`,
  constant-time comparison, and a bounded timestamp tolerance before decoding.
  Only that signed endpoint may be exempt from CSRF; user routes retain it.
- Persist/deduplicate the event ID and apply its snapshot transactionally before
  acknowledgement. Never regress succeeded/failed states. Handle events
  arriving before the create response and conflicting/stale observations safely.
- Delivery may exhaust retries. Reconcile via retrieval/listing; honor
  Retry-After with capped attempts/backoff. Image URLs expire after 24 hours.
- Usage reports are per API key, attributed to the UTC completion day. Keep
  provider/account/key identity and completion time for reconcilable reporting.

Validate external DTOs and all user input. Authorize each owned resource on the
server, protect admin routes explicitly, and never leak credentials or payloads
into frontend props/logs. Use separate provider keys for each environment.

## Money and rounding policy

Persist USD as exact integer nanodollars (1 USD = 1,000,000,000 units), with
checked signed-64-bit bounds. Parse decimal strings using decimal arithmetic
(Brick Math); never convert through floats. Preserve each generation's
rates and usage. Sum exact per-token category costs without intermediate
rounding; these must reconcile with the provider's nine-decimal usage totals.

Multiply the exact provider total by 5/4. Quantize the customer total once to
the nearest nanodollar, ties away from zero (half-up for nonnegative charges).
Record the rounding delta/rational remainder if needed for audit. Current
documented rates allow exact nanodollar settlement including markup; if future
rates need finer precision, stop and revise representation rather than silently
rounding provider cost. Reject overflow/unsupported precision. Failed generations
settle to zero; a generation is charged at most once.

Estimates use documented token uncertainty and are clearly distinct from actual
charges. Display formatting never changes stored amounts. Pass exact monetary
values to Vue as strings, including values too large for JavaScript integers.

`GenerationPricing` receives immutable provider parameters and rate snapshots;
it performs no I/O. Text tokens use the Unicode character count divided by the
supplied character ratio, rounded up. Text bounds round outward (floor/ceiling).
Reasoning estimates use the bounds' arithmetic midpoint, rounded up to a whole
token. Actual calculation accepts confirmed `TokenUsage` and accepted rates,
independently of any quote. Sum exact category costs before checking the total's
nanodollar precision; sub-nanodollar category amounts are never rounded away.

## Phase 1 persistence

`generations` stores immutable local submission intent and quote data before a
provider identity exists. Local pending/submitting/submitted/uncertain/rejected
states are separate from nullable provider queued/processing/succeeded/failed
states. Provider identity, lifecycle, and accepted rates must become known
together. Success requires usage and a completion time. Accepted rates and
provider identity cannot be replaced through normal Eloquent updates.

`pixelforge_webhook_events` stores unique event identity, an optional local
association, timestamps, and minimal diagnostics, with no raw payload or secrets.

`generation_settlements` is the sole source of actual monetary amounts. Its
unique generation key prevents a second settlement; a composite foreign key
to generation identity/status and a fixed succeeded sentinel prevent settlement
of failed or unfinished work, even through raw SQL. Financial columns use signed
BIGINT with range/integer/nonnegative checks. Laravel's schema builder lacks
portable table CHECK definitions, so the three focused migrations use explicit
CREATE TABLE SQL shared by PostgreSQL and SQLite, with Laravel-defined indexes.

Generation `settled_at` is only a reconciliation projection. Snapshot settlement
writes it and the authoritative settlement in one short transaction.
Eloquent financial attributes serialize as
integer strings; Money renders USD with exactly nine decimals. Standard model
mass assignment is fully guarded; ownership is enforced by GenerationPolicy.
All timestamps must be written in UTC. PostgreSQL execution/locking verification
still requires a separately approved disposable integration database.

## Phase 2 submission lifecycle

`POST /generations` is an authenticated JSON endpoint in the normal web middleware
group, using session authentication, CSRF, a Form Request, and the create policy.
Only prompt, size, quality, and an optional reference_images list are accepted;
client metadata, identity, status, and financial fields are rejected.

`PixelforgeClient` uses Laravel HTTP with a 3-second connection timeout, 15-second
total timeout, and redirects disabled. Public pricing is validated and cached for
60 seconds; it never uses an API key or a hardcoded fallback. Generation POST/GET
use bearer authentication and validated immutable response DTOs. Provider errors
are classified by code; exceptions/logs contain local safe messages, never raw
provider error bodies, prompts, headers, keys, or signing secrets.

`CreateGeneration` fetches pricing and calculates the quote before opening a
transaction. It then persists the UUID, independent idempotency key, normalized
inputs, full quote, and complete provider payload, including metadata containing
the local UUID. Submission is dispatched only after commit. The HTTP response is
202 with the local UUID, pending status, and exact estimated charge string. No
provider generation POST happens in the creation request.

`SubmitGeneration` targets PIXELFORGE_QUEUE_CONNECTION (database by default) and
the pixelforge queue, independently of the global default. Its payload is the UUID.
A short row-lock transaction claims an attempt; HTTP executes after that transaction
ends. A fresh submitting claim has a 60-second lease to defer concurrent duplicates.
The immutable payload and idempotency key remain the primary provider deduplication
mechanism. A duplicate after acceptance skips POST entirely.

There are at most five submission attempts per generation and ten queue attempts
per job, with a 30-second job timeout (below the default database retry_after of 90
seconds). Transient errors release the job using the greater of Retry-After and
5/15/60/180-second delays for the four retries; workers never sleep. Error and retry timestamps are
persisted. An expired in-flight claim is treated as uncertain before retrying.

Definitive credential/validation/content/idempotency rejections become locally
rejected, with no provider ID or charge. Daily quota exhaustion stops retries and
records the next UTC quota boundary. POST connection failures, malformed accepted
data, and snapshot/settlement validation failures remain uncertain. Uncertainty
survives later rejection responses because they cannot disprove prior acceptance.
Exhausted/stopped uncertain records keep their original identity and payload;
duplicates cannot restart them when no next attempt is scheduled. Failed queue
jobs and sanitized local diagnostics remain observable.

`ApplyGenerationSnapshot` is the single operation for POST responses, webhooks,
and GET reconciliation. It validates metadata, immutable inputs, identity, and
accepted rates under a row lock. Queued cannot replace processing; established
terminal states cannot be replaced. Accepted rates may differ from quote rates
but cannot change after acceptance. Every successful 202 replay is settled using
confirmed usage and accepted rates, never estimated usage or current pricing.

On success, the action saves the succeeded generation first, creates the unique
settlement, then synchronizes settled_at in the same transaction. This order honors
the existing composite foreign key. Replays cannot create a second settlement.
Failed provider completion records its error and represents zero actual charge by
the failed state plus absence of a settlement; settled_at remains null. A terminal
snapshot applied before a queued create response remains terminal. Any failure in
financial validation rolls back all snapshot/settlement changes atomically.

## Phase 3 audit and webhook transactions

The Phase 2 audit confirmed webhook-before-response correlation, succeeded-before-
settlement FK ordering, free failures, unique settlements, and immutable rates.
It found no repair for commit-to-enqueue/worker-crash gaps and a hardcoded queue
connection. Phase 3 adds repair leases and configurable async queues, marks
uncertain in-flight claims, and parks final-attempt worker crashes explicitly.
Terminal
success replays now reject changed confirmed usage and repair settled_at from
the existing settlement without replacing terminal metadata.

`POST /webhooks/pixelforge` is stateless, outside the session/auth web group.
Laravel 13 preventRequestForgery excludes exactly this path; user routes retain
CSRF. Verification requires a configured secret, exactly one numeric timestamp
and 64-hex digest, absolute clock skew <=300 seconds, and hash_equals against
HMAC-SHA256(timestamp + '.' + original raw bytes), before JSON decoding. Signed
ping needs no generation. Envelopes validate event ID, ISO timestamp, closed event
types, the full generation DTO, local UUID and event/status agreement. Optional
event ID/type headers must agree with the signed body.

ProcessPixelforgeWebhook performs one transaction (up to three deadlock retries):
insert identity with insertOrIgnore backed by UNIQUE(provider_event_id), lock the
event, lock/apply the generation, attach its FK, mark processed, commit. Initially
inserting without the generation FK avoids concurrent FK-share-lock upgrades
before the generation lock. Processed deliveries validate their association and
immutable identity without updating rows. Different event IDs serialize on the
same generation. The nested snapshot transaction has no HTTP; its settlement and
projection remain inside the event transaction.

New and duplicate successful deliveries return 204. Invalid signature is 401;
malformed/conflicting signed data is 400; missing local records or transient SQL
failures are 503. Failures roll back event, generation and settlement together.
Logs contain safe event/local IDs and codes, never signatures, secrets, full
bodies or exception payloads.

| Stored provider state | Accepted advancement                  | Stale observations |
| --------------------- | ------------------------------------- | ------------------ |
| unset                 | queued, processing, succeeded, failed | none               |
| queued                | processing, succeeded, failed         | queued             |
| processing            | succeeded, failed                     | queued             |
| succeeded             | none; one immutable settlement        | all lifecycle data |
| failed                | none; no settlement, zero charge      | all lifecycle data |

Identity/rates are checked before ignoring stale data. Repeated success must
agree with confirmed usage. A webhook can establish identity/rates and settle
before POST returns; the later queued response cannot erase terminal data. A
duplicate submission after acceptance skips POST. Definitively rejected local
requests remain distinct from accepted provider failures.

## Recovery and operational bounds

Scheduler runs pixelforge:reconcile every minute with withoutOverlapping(5).
The command selects at most 20 eligible rows (manual --limit=1..50), rechecks each
under a row lock and dispatches after commit. A persisted five-minute dispatch
lease prevents duplicate scheduling. Up to ten repair dispatches without an actual
worker claim tolerate lost dispatches/backlogs; claiming real work resets that
dispatch counter. Broker failures retain the lease for later repair and do not
abort the rest of the batch. The command makes no provider HTTP requests.

Known-provider nonterminal work is polled no sooner than 120 seconds after provider
creation/acceptance or the last poll. ReconcileGeneration claims briefly, GETs
outside SQL, and uses ApplyGenerationSnapshot. Nonterminal polls are spaced by
120 seconds; transient errors release with max(Retry-After, 120/120/300/600/900).
At most 20 GET attempts persist across jobs. Credentials, not-found, identity
conflicts, and exhaustion stop polling with diagnostics and unresolved provider
state; they never fabricate failure or free settlement. Terminal rows are excluded.
Ordinary slow 60-90-second generations are not timed out as provider failures.

Unknown-provider work is repairable after 60 seconds, respecting the initial
five-minute dispatch lease, active submission lease, retry deadline, and five-POST
budget. Expired submitting work becomes uncertain. Interrupted/ambiguous POSTs
reuse the original stored payload and key without creating a new local generation.
Temporary quota rejection becomes pending only after both the next UTC quota
boundary and any longer Retry-After, without resetting attempts. Permanent rejection and uncertainty blocked
by credentials/conflict require operator review.

Both jobs share Laravel's cache-backed 50-request/minute account queue limiter,
leaving headroom within the provider's 60/minute limit. Use a shared persistent
cache across workers; account activity outside this app can still cause 429s.
Provider Retry-After remains authoritative. Releases consume ten bounded queue
attempts per job; durable provider budgets and repair-dispatch caps prevent endless
redispatch. Recovery depends on provider, DB, worker and scheduler availability.
Exhausted/stopped records require operator diagnosis; automatic budgets are never
silently reset. Preserve the same provider account/base URL and request identity.

## Local development

Configure the intended local database and copy the Pixelforge placeholders from
`.env.example` into the private `.env`, using a real key for this environment.
Set PIXELFORGE_QUEUE_CONNECTION=database locally. After configuration/route changes,
clear local cached configuration/routes and restart any existing worker. Apply
the pending migrations to that intended local database yourself; automated tests
continue to use only the guarded SQLite memory database.

```shell
php artisan config:clear
php artisan route:clear
php artisan migrate
```

Run these in separate terminals:

```shell
php artisan serve --host=localhost --port=8002
npm.cmd run dev
php artisan queue:work database --queue=pixelforge --timeout=30 --tries=10
php artisan schedule:work
```

Use the configured connection name in queue:work if it differs from database.
Sync/deferred/background/null connections are rejected. retry_after must exceed
35 seconds for the 30-second job timeout (database default 90); SQS visibility
must exceed that timeout. Run the scheduler independently; production cron invokes
schedule:run every minute. Worker execution does not depend on frontend traffic.

Manual diagnosis: `php artisan pixelforge:reconcile --limit=10`, optionally
`--generation=<local-uuid>`. It respects leases/budgets and never resets them.
Apply the pending recovery-tracking migration before these processes.
The existing jobs/failed_jobs migrations supply queue persistence. Inspect failures
with `php artisan queue:failed`; retrying a stopped record does not silently reset
its persisted attempt budget or request identity. A sync queue is not an asynchronous
deployment. No worker, deployment, live submission, or development migration is
started by the test suite. Studio consumes the JSON endpoint through Inertia's
same-origin HTTP client and normal XSRF cookie/header protection.

## Phase 4 presentation boundaries

Studio, paginated My Generations (12 per page), and generation detail are Inertia
pages. Session authentication, create/viewAny/view policies, and user-scoped
queries protect reads and prompt reuse. The dashboard login destination renders
Studio; settings/authentication remain intact. GenerationResource explicitly
allowlists public fields and requires eager-loaded settlements, avoiding N+1s and
operational/credential leakage. Prompts render as text; references are URL inputs,
never uploads or server-fetched images.

POST /generations/quote shares creation's Form Request and QuoteGeneration action.
It reads cached public pricing and returns decimal-string estimates, bounds,
rates and provenance, without persistence or dispatch. Creation independently
validates/reprices; the displayed range is not a guaranteed maximum. Vue debounces
450 ms, cancels obsolete quotes, counts Unicode code points using Laravel-compatible
boundary trimming, and preserves user-scoped tab drafts. An interrupted creation
is never automatically resent; its draft retains an uncertainty warning.

GET /generations/statuses accepts at most 12 distinct owned UUIDs. It reads only
local data with eager-loaded settlements; optional detail includes billing rows.
The shared polling composable waits four seconds between completed requests,
pauses when hidden, revalidates on visibility restoration, stops at terminal
states, cancels on unmount, and preserves last-known data on errors.

The Cost Inspector recalculates category breakdowns through GenerationPricing
using confirmed usage and accepted rates, verifies them against the authoritative
settlement, and prepares exact totals/markup/differences in PHP. Vue only renders
decimal strings. Missing/inconsistent successful billing is unavailable, never
zero. Failed/rejected work is uncharged; uncertainty remains pending. Image links
are temporary, hidden after expiry, and have graceful load-error fallbacks.

### Local webhook testing

Run a public HTTPS tunnel forwarding to local port 8002. In the Pixelforge
dashboard, configure this environment's API key webhook URL as
`<public-tunnel-origin>/webhooks/pixelforge`; copy that key's signing secret into
private PIXELFORGE_WEBHOOK_SECRET and restart after configuration changes.
Use the dashboard's Send test event (signed ping), then a test generation with
worker and scheduler running. Expect 204 and one processed event; verify exactly
one settlement after success and none after failure. A local replay with a
changed signature/body must return 401 with no writes. Keep credentials,
signatures, payloads and tunnel inspector dumps out of logs. Automated tests
start neither a tunnel nor live provider requests.

## Verification and scope discipline

Pest uses SQLite `:memory:`, array cache/session/mail, synchronous queue, null
failed-job storage/broadcasting, disabled Inertia SSR, and blocked stray HTTP. PHPUnit forces those
values and uses dedicated configuration/routes/events cache paths. A preflight
in `tests/bootstrap.php` validates loaded configuration without registering
providers. Every `Tests\TestCase` application registers the same guard directly
after LoadConfiguration, before providers and RefreshDatabase/migration traits.
It rejects wrong drivers/databases, URLs, read/write overrides, and unsafe
service defaults, then removes other named database connections. It never
opens PDO to decide whether a target is safe. Unsafe test caches fail explicitly;
normal development caches are neither cleared nor rewritten by the test command.

Run `composer test` (Pint, existing PHP analysis, Pest), or narrow tests with
`php artisan test --compact tests/Unit/TestEnvironmentTest.php`. Run
`npm run check`, `npm run types:check`, and `npm run build` for frontend quality.
Use `npm.cmd` on this PowerShell host. Never run `composer setup` to verify an
existing environment: it includes migration. Integration DBs require a future
explicit opt-in with target validation; SQLite cannot prove PostgreSQL locks.
The custom TestCase boot sequence follows installed Laravel 13; review it on
framework upgrades before adopting cached-config/routes testing traits.

Use reusable accessible Vue/shadcn components, Wayfinder routes, and responsive
Inertia pages. Loading/failure/success and estimate/actual differences must be
clear. Existing Eloquent and Laravel primitives keep workflows reviewable;
generic repositories, CQRS, event sourcing, microservices, and speculative
infrastructure add no value at this assessment's scope.

## Phase 6 admin financial reporting

The default-off users.is_admin capability is excluded from mass assignment and
hidden in serialized user data. viewFinancialReports guards the page, refresh,
and dedicated financial generation detail route. Registration/profile actions
cannot grant it. app:admin USER_ID --force (optionally --revoke) is the explicit
trusted-console operation. No existing user is promoted by the migration.
Shared props expose only the resulting capability for conditional navigation;
ordinary generation policies remain owner-only, including for admins.

FinancialReport owns bounded UTC completion-date queries (1-92 inclusive days).
Settlement facts are authoritative: customer charges and provider costs are
summed exactly, with gross profit as their difference. No estimate/current rate
is used. TIMESTAMP completion values are stored in UTC by the existing provider
DTO. Missing successful settlements are surfaced as an invariant problem.

FinancialTotals accumulates integer strings with Brick BigInteger. It validates
individual Money bounds but never narrows an aggregate back to signed BIGINT;
BigDecimal.ofUnscaledValue renders exact nine-decimal USD. One lazyById scan uses
500-row chunks, holding at most 92 daily accumulators and the selected user/group
pages. User and daily-user groups paginate 15 rows; completion drill-down uses 20
records with eager-loaded users/settlements. There are no per-user queries or
SQL money SUMs. This portable linear scan is proportionate to the assessment;
larger reporting workloads would justify NUMERIC-safe database aggregation or
reviewed rollups, with the same financial semantics.

PixelforgeUsageData validates the documented /usage object, exact requested UTC
range, unique complete daily coverage, integer counts, nine-decimal cost strings,
and totals equal to the days. Aggregate money retains arbitrary precision.
FinancialReportRequest applies Gate authorization, strict dates, and the inclusive
92-day bound. Thin controllers delegate queries and provider refresh separately.

ProviderReconciliation fetches only on an admin POST, outside SQL transactions.
Its injected cache repository stores validated scalar arrays for 24 hours, with
key/environment/base-URL/range isolation, a 30-second atomic lock, and a key-wide
60-second cooldown extended by Retry-After. Snapshots are stale at 10 minutes.
Failure preserves the last successful data/time but yields UNAVAILABLE. No GET
page render fetches provider data. All comparable daily metrics must be equal for
MATCHED; any mismatch is MISMATCHED, missing data is NOT_CHECKED, and stale/failed
fetches are UNAVAILABLE. Whole-period status cannot hide daily discrepancies.
No settlement is mutated by reporting or reconciliation.

The provider is API-key scoped, not user scoped. Local rows lack historic key IDs,
so key rotation and outside-application activity require operator attribution;
comparison is explicit rather than forced equal. An open UTC day can legitimately
change between local reads and a provider snapshot. Shared persistent cache is
required across replicas. Cache eviction discards comparison history; this is
an operational snapshot, not a new authoritative financial ledger.

Admin Vue pages reuse existing cards, alerts, tables, navigation, and the Cost
Inspector. Exact amount strings are rendered without JavaScript arithmetic.
Dedicated table/reconciliation components keep filters, pagination, and fetch
feedback distinct. An admin financial detail view is read-only and does not
provide cross-user prompt reuse or normal-user status polling.

The admin migration and operational promotion require a separate approved rollout.
No development/production migration or promotion is performed during this phase.
Live provider financial acceptance remains deferred to Phase 9; automated tests
use the original fail-closed SQLite memory environment and fake provider HTTP.

## Wallet accounting

The migration creates one USD wallet per existing user without copying old financial activity. Registration creates the user and zero wallet together. A unique user primary key also makes lazy creation safe for imported accounts. A generation and its reservation commit together in the new creation path. The reservation is the cutover marker. Every generation created before this path, including one that completes after rollout, remains outside wallet capture.

The wallet row serializes changes to its signed settled balance and outstanding reserved total, both in integer nanodollars. Available is settled minus reserved. Simulated purchases lock that row and append a credit. A per-owner UUID identifies each confirmed denomination. Replaying the same intent returns the previous effect, and changing the denomination is rejected. The ledger rejects SQL UPDATE and DELETE. A capture has a unique generation and foreign keys to both the owner's reservation and the matching generation settlement amount. Account closure with financial history requires operator handling.

Creation takes the browser's Idempotency-Key UUID, checks for replay, prices outside the transaction, locks the wallet, then rechecks the intent and funds. Only a newly inserted generation is touched while holding the wallet lock. For existing generations the order is generation first, then wallet. Purchases only lock the wallet. Admission uses the current quote's maximum: ceil(ceil(Unicode code points / characters per token) times (1 + variance)), plus billed reference image tokens, fixed output and maximum reasoning. GenerationPricing applies snapshot rates to those counts and marks up the total by 25%, HALF_UP once at nine decimal USD. The after-commit job uses the original provider payload and a separate provider idempotency key.

The snapshot action computes the accepted maximum from the frozen token bounds and accepted rates. If the entire increase is affordable it enlarges the hold. Otherwise the original hold stays active, and the recorded accepted maximum exposes the shortfall. A hypothetical maximum outside supported precision is flagged for review. Terminal success copies the authoritative settlement amount into one wallet debit, closes the hold, and updates both balances in the same transaction. The ledger stores positive amounts; purchase rows add and capture rows subtract. The original quote and final held amount remain auditable. Actual charges can exceed the hold, leaving an explicit deficit and blocking new unaffordable submissions. Financial reports still aggregate settlements.

Confirmed terminal failures and definitive submission rejections release the hold in the same transaction. Retryable overload, an eligible next-day quota retry, worker death, ambiguous POST, and exhausted unknown outcomes retain their holds. Recovery uses the original generation/key and the existing scheduler. For unresolved holds, investigate the provider identity and recover a trusted snapshot; elapsed time alone cannot establish a free failure. There is no automatic expiry. Deploy the schema first, then switch HTTP and queue workers together and drain old workers before admitting wallet-funded work. An older worker cannot capture wallet debits.
