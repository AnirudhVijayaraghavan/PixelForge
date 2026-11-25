# Phase 5B verification baseline

Audit date: **2026-11-25 UTC** (2026-11-24 America/New_York).
Public application: <https://pixelforge-production-hcilan.laravel.cloud/>.
Repository: <https://github.com/AnirudhVijayaraghavan/PixelForge>.

**Production verification is incomplete.** Public availability and guest
protection were checked directly. The human subsequently confirmed production
Studio access after login, responsive quality-based quotes, and an existing
successful image/history/Cost Inspector. Private runtime configuration,
worker/scheduler execution, settlement uniqueness, and production financial
reconciliation remain unverified. No new provider generation, deployment, production
configuration change, migration, financial edit, or deletion was performed.

Statuses mean: **PASS** observed evidence; **FAIL** an observed mismatch;
**BLOCKED** required access/evidence unavailable; **NOT RUN** not exercised.
Local automated/manual results are explicitly separate from production results.

## Release identity and source (Phase 5B snapshot)

| Check                                    | Status  | Evidence                                                                                                                                                |
| ---------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Public GitHub main                       | PASS    | Remote HEAD `b46619047fa9082ced4fcfccf353fd7443c2ce44`, commit `v0.2.0`                                                                                 |
| Desktop repository matches remote        | PASS    | Clean main tracking origin/main at the same SHA; remote read with `git ls-remote`                                                                       |
| Tested U: source matches committed files | PASS    | All 380 Desktop tracked files compared by SHA-256; 379 present files byte-identical, only LICENSE absent from U:                                        |
| Current workspace commit                 | BLOCKED | U: repository has no commits or remote; source is untracked. Do not treat its empty Git diff as a clean committed release                               |
| GitHub CI for that commit                | PASS    | Completed `ci` check, conclusion success; [job evidence](https://github.com/AnirudhVijayaraghavan/PixelForge/actions/runs/45532405891/job/138930687207) |
| Cloud running commit matches GitHub      | BLOCKED | No authenticated Cloud release metadata available; GitHub deployment list returned no records                                                           |
| Production PostgreSQL                    | BLOCKED | Source supports PostgreSQL, but private production runtime configuration was not inspected                                                              |
| Production queue and worker              | BLOCKED | Expected async connection and queue pixelforge; actual Cloud worker configuration/execution unavailable                                                 |
| Production scheduler                     | BLOCKED | Source registers an every-minute command; registration alone does not prove Cloud execution                                                             |
| Production API key and signing secret    | BLOCKED | No production runtime credential-presence check or signed-event evidence obtained; local values were not substituted                                    |
| License consistency                      | FAIL    | Desktop/GitHub LICENSE is The Unlicense; both composer.json copies declare MIT. Neither declaration was changed                                         |

The new README and this report are written only in U:. The separate Desktop copy
and GitHub were read only. After these additions, the two documentation files need
to enter the maintainer's normal Git workflow; no commit, push, or release occurs
automatically. Earlier authentication/cache corrections are included in the
byte-identical committed source; whether Cloud serves that commit is unverified.

## Public production checks actually performed

Bounded HTTPS GET requests, certificate verification enabled, redirects not
followed. No login session, account creation, quote, provider submission, or
webhook POST. Observed **2026-11-25 03:43:19-03:43:40 UTC**.

| Request                                    | Expected                  | Observed                 | Status |
| ------------------------------------------ | ------------------------- | ------------------------ | ------ |
| GET /                                      | Public page               | 200 HTML                 | PASS   |
| GET /up                                    | Health endpoint           | 200 JSON                 | PASS   |
| GET /login                                 | Login page                | 200 HTML                 | PASS   |
| GET /register                              | Registration page         | 200 HTML                 | PASS   |
| GET /studio                                | Guest redirected to login | 302 to production /login | PASS   |
| GET /generations                           | Guest redirected to login | 302 to production /login | PASS   |
| GET /dashboard                             | Guest remains protected   | 302 to production /login | PASS   |
| GET /generations/statuses with JSON Accept | Guest denied              | 401 JSON                 | PASS   |
| GET /webhooks/pixelforge                   | POST-only route           | 405 JSON, Allow: POST    | PASS   |

Health and route reachability do not establish database identity, authenticated
rendering, effective APP_DEBUG, signature verification, or financial correctness.
A later read-only GET of the webhook 405 JSON inspected field names only:
no exception/file/line/trace fields were present. This limited check passed;
effective APP_DEBUG was not inferred from it. No cookies, signatures, credentials,
signed URLs, or response payloads were saved.

## Human-confirmed production browser checks

Following the public probes, the human answered "all passed" to these explicit
instructions using an existing account on the production origin:

1. Studio opens after login.
2. Changing quality updates the price quote.
3. An existing successful generation opens from history with its image and
   Cost Inspector.

These are human-observed production presentation passes, including the existing
image result. The guide did not log in or perform browser automation. The
instruction prohibited clicking Generate; no additional generation was requested
or executed by the guide. No exact financial amounts, event IDs, provider/local
ID correlation, worker logs, scheduler logs, or database counts were supplied.
The result does not establish a unique settlement, duplicate-event behavior,
production-key usage reconciliation, or a new end-to-end submission.

## Required production scenarios

| ID  | Scenario                        | Status  | Evidence / remaining scope                                                                                                                                                                   |
| --- | ------------------------------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P01 | Authenticated Studio            | PASS    | Human confirms Studio opens after login on the production URL. Account verification state and running commit were not captured.                                                              |
| P02 | Server-generated pricing        | PASS    | Human confirms changing quality updates the production quote. Presentation verified; raw Network response and no-generation DB count were not independently captured.                        |
| P03 | Accepted provider generation    | NOT RUN | Existing production local/provider ID correlation; obtain approval before any additional generation                                                                                          |
| P04 | Queue worker execution          | BLOCKED | Cloud worker configuration and an actual matching SubmitGeneration completion                                                                                                                |
| P05 | Signed generation webhooks      | BLOCKED | Production-key dashboard delivery plus matching processed local event                                                                                                                        |
| P06 | Successful image completion     | PASS    | Human confirms an existing successful production generation displays its image. No new completion was triggered; provider identity and completion timestamp were not independently captured. |
| P07 | One exact settlement            | BLOCKED | One production settlement reconciled to confirmed usage and accepted rates                                                                                                                   |
| P08 | Failed generation costs zero    | NOT RUN | Existing production failed record with no successful settlement and zero-charge display                                                                                                      |
| P09 | Duplicate delivery settles once | NOT RUN | Same production event delivered again with one unchanged settlement; replay needs explicit operator approval                                                                                 |
| P10 | History and Cost Inspector      | PASS    | Human confirms production history opens the existing successful generation with its image and Cost Inspector. Exact displayed amounts were not supplied or independently reconciled.         |
| P11 | Recovery scheduler executes     | BLOCKED | Cloud scheduler enabled plus actual scheduled-command/recovery-job execution evidence                                                                                                        |
| P12 | Provider usage reconciliation   | BLOCKED | Production-key /v1/usage and matching production DB aggregates for identical closed UTC dates                                                                                                |

No production scenario is marked PASS from a deployment-success message or from
local tests. First inspect existing rows and dashboard deliveries to avoid
unnecessary submissions. The guide requested no new generation in Phase 5B.

## Historical local evidence, not production evidence

[MANUAL_TESTING.md](MANUAL_TESTING.md) records human-led local PostgreSQL/tunnel
tests from the earlier session:

- Signed ping HTTP 204: `evt_01M7VGWGDCFXVVM458BBV0R93B`.
- One succeeded generation, one submission attempt, processed processing/success
  events, and exactly one settlement at `2026-11-20T04:26:24+00:00`.
- Estimated customer amount **0.018000000 USD**; exact provider cost
  **0.017515000 USD**; exact customer charge **0.021893750 USD**.
  Markup is exactly 25%; actual minus estimate is **0.003893750 USD**.
- A read-only provider generation GET matched local identity, usage, rates, and
  recalculated financial amounts.
- A separate failed generation had zero successful settlements; the human
  confirmed zero charge. The previous success retained one settlement.
- Human-confirmed image, owner history, Cost Inspector, and prompt reuse.

These used the development environment/key. They are not production amounts and
were not reused as production GET /usage reconciliation. No production totals
have been obtained; missing evidence must not be represented as zero totals.

## Read-only production evidence collection

The Cloud CLI is absent from both source copies and PATH; no dependency was
installed and no Cloud authentication was initiated. The public URL needs no
credentials. Private checks can be performed by the owner in the intended Cloud
environment's Commands panel without sharing secrets or granting the guide access.

1. Record the active deployment's commit SHA from Cloud and compare it with the
   GitHub SHA above. Record the actual worker connection/queue/timeout and the
   scheduler toggle. Evidence of configuration is separate from evidence of work.
2. Check safe runtime values and credential presence with this read-only command.
   It refuses the wrong host/environment and never prints credential values:

```shell
php artisan tinker --execute='if (! app()->isProduction() || parse_url(config("app.url"), PHP_URL_HOST) !== "pixelforge-production-hcilan.laravel.cloud") { throw new RuntimeException("Wrong verification environment."); } $configured = static fn ($value) => is_string($value) && trim($value) !== "" ? "CONFIGURED" : "MISSING"; dump(["environment" => app()->environment(), "debug" => config("app.debug"), "app_url" => config("app.url"), "db_driver" => DB::connection()->getDriverName(), "db_timezone" => DB::connection()->getDriverName() === "pgsql" ? DB::selectOne("SHOW TIME ZONE") : "NOT POSTGRESQL", "app_timezone" => config("app.timezone"), "queue_default" => config("queue.default"), "pixelforge_connection" => config("services.pixelforge.queue_connection"), "queue_name" => "pixelforge", "cache_store" => config("cache.default"), "api_key" => $configured(config("services.pixelforge.api_key")), "webhook_secret" => $configured(config("services.pixelforge.webhook_secret"))]);'
```

Inspect the selected DB name/host privately in Cloud as well; do not dump a full
configuration array or connection URL. For workers, confirm the named connection
has a supported async driver and a reservation exceeding 35 seconds. Database
default retry_after is 90 seconds; job timeout is 30 seconds.

3. `php artisan schedule:list` proves registration only. Cloud execution history
   or logs must separately show the scheduled reconciliation command ran. Do not
   run `schedule:run`, `pixelforge:reconcile`, `queue:work`, or `queue:retry` as
   read-only probes: those execute work and may submit requests.
4. Use an existing production generation to correlate its provider ID, immutable
   inputs, accepted rates, completion timestamp, processed event IDs, and unique
   settlement. Share only safe identifiers, counts, usage, timestamps, and amounts.
5. With an existing production account, check Studio, a quote, owner history, and
   Cost Inspector. Request explicit approval before creating an account, sending
   a webhook replay, changing configuration, or generating any additional image.

### Exact production financial reconciliation

**Status: BLOCKED.** No production-key request was made; production DB totals,
provider totals, per-category token differences, and monetary differences are
unknown. Source inspection confirms that the application client does not yet
implement GET /usage. This audit adds no provider operation or reporting feature.

Choose a closed UTC completion-date interval with no more than 92 inclusive days.
Use the same interval for both sides. The generation completion columns are
TIMESTAMP WITHOUT TIME ZONE, and the application writes UTC. Group the stored
UTC timestamp directly by date; do not reinterpret it in the browser timezone or
group by settlement/creation date. Confirm runtime timezone is UTC first.

Run this SELECT in the intended production PostgreSQL console, replacing the
two example date literals with the agreed interval. It performs no writes:

```sql
SELECT
    CAST(g.provider_completed_at AS date) AS completion_date_utc,
    COUNT(*)::text AS succeeded_generations,
    COUNT(s.generation_id)::text AS settlements,
    COUNT(*) FILTER (WHERE s.generation_id IS NULL)::text AS missing_settlements,
    SUM((g.actual_token_usage->>'input_text_tokens')::numeric)::text AS input_text_tokens,
    SUM((g.actual_token_usage->>'input_image_tokens')::numeric)::text AS input_image_tokens,
    SUM((g.actual_token_usage->>'output_tokens')::numeric)::text AS output_tokens,
    SUM((g.actual_token_usage->>'reasoning_tokens')::numeric)::text AS reasoning_tokens,
    SUM(s.provider_cost_nanodollars)::text AS provider_cost_nanodollars,
    to_char(SUM(s.provider_cost_nanodollars) / 1000000000::numeric,
        'FM999999999999999999999999999990.000000000') AS provider_cost_usd
FROM generations AS g
LEFT JOIN generation_settlements AS s ON s.generation_id = g.id
WHERE g.provider_status = 'succeeded'
  AND g.provider_completed_at >= TIMESTAMP '2026-11-24 00:00:00'
  AND g.provider_completed_at < TIMESTAMP '2026-11-25 00:00:00'
GROUP BY CAST(g.provider_completed_at AS date)
ORDER BY completion_date_utc;
```

This example covers only 2026-11-24 UTC; it is not an executed query or a claim
that production activity exists on that date. A missing settlement is an invariant
failure. It must not be silently omitted by an inner join or converted to free
success. PostgreSQL aggregates integer nanodollars exactly as numeric values.

In Cloud Commands, use the production environment's configured key for the
corresponding read-only provider GET, never a key copied into chat or a command:

```shell
php artisan tinker --execute='if (! app()->isProduction() || parse_url(config("app.url"), PHP_URL_HOST) !== "pixelforge-production-hcilan.laravel.cloud" || rtrim((string) config("services.pixelforge.base_url"), "/") !== "https://pixelforge.promptedit.dev/v1") { throw new RuntimeException("Wrong production provider environment."); } $key = config("services.pixelforge.api_key"); if (! is_string($key) || trim($key) === "") { throw new RuntimeException("Production key is MISSING."); } $response = Http::acceptJson()->withToken($key)->connectTimeout(3)->timeout(15)->withoutRedirecting()->get("https://pixelforge.promptedit.dev/v1/usage", ["start_date" => "2026-11-24", "end_date" => "2026-11-24"]); if ($response->status() !== 200) { dump(["http_status" => $response->status(), "reconciliation" => "BLOCKED"]); } else { $data = $response->json(); if (($data["object"] ?? null) !== "usage" || ! is_array($data["data"] ?? null) || ! is_array($data["total"] ?? null)) { throw new RuntimeException("Unexpected usage structure."); } $fields = ["date", "generations", "input_text_tokens", "input_image_tokens", "output_tokens", "reasoning_tokens", "cost_usd"]; dump(["start_date" => $data["start_date"] ?? null, "end_date" => $data["end_date"] ?? null, "data" => array_map(static fn ($day) => array_intersect_key($day, array_flip($fields)), $data["data"]), "total" => array_intersect_key($data["total"], array_flip($fields))]); }'
```

Replace both dates together with the agreed interval. The command does not retry
429s automatically; capture status and honor Retry-After before another request.
Do not print request headers, raw exception messages, or the key. No such command
was run by the guide in production.

Compare each UTC day and the full interval: successful generation count, all four
token counts, and provider cost as integer nanodollars/nine-decimal strings. Treat
provider zero days explicitly when local SQL has no row. Sum nanodollars before
display formatting. Customer markup is revenue, not a provider usage amount.
Reject incomplete intervals, malformed amounts, or missing settlements rather
than claiming equality. Record both sides and their exact differences here.

Key provenance is an existing limitation: generations store accepted rates and
provider identity but not the originating API-key ID. Confirm a single unchanged
production key for the interval, and account for any dashboard/out-of-band work.
If keys rotated or multiple keys contributed, separate their historical usage
before drawing conclusions. Investigate mismatches; never edit financial facts
to force reconciliation. No new schema is introduced by this audit.

## Quality gates

Fresh Phase 5B gates passed on 2026-11-25 UTC: 387 tests / 1861 assertions,
Pint, PHPStan (0 errors), TypeScript, frontend checks, and build. Test isolation
was re-inspected: forced SQLite :memory:, separate testing cache paths,
pre-provider runtime validation, isolated services, and blocked stray HTTP remain
intact. No additional tests or application changes were needed for documentation.

| Command                              | Status | Fresh result                                                  |
| ------------------------------------ | ------ | ------------------------------------------------------------- |
| composer test --no-interaction       | PASS   | Pint PASS; PHPStan 0 errors; Pest 387 tests / 1861 assertions |
| composer lint:check --no-interaction | PASS   | Pint PASS                                                     |
| npm.cmd run types:check              | PASS   | vue-tsc PASS, exit 0                                          |
| npm.cmd run check                    | PASS   | Formatting PASS; 77 files linted with no warnings/errors      |
| npm.cmd run build                    | PASS   | PASS; 3676 modules; optional fontaine warning only            |

Dependency inspection found an extraneous local react package; no dependency
was installed, removed, or changed. Fresh npm ci in an isolated checkout uses
the lockfile. The fresh build reported the optional fontaine warning; no package
was added to suppress it. Its process-only APP_ROUTES_CACHE override bypassed a
stale development route cache without clearing that cache or changing .env.
The documented PHP commands passed syntax checks only; they were not executed.
Documentation was checked for secret values/patterns and broken local links.
All previously tracked source files still match the Desktop repository; only
README.md and docs/VERIFICATION.md are new.

## Outstanding baseline decisions

- Obtain production release/runtime, worker/scheduler, event/settlement, and
  exact production usage evidence before signing off the financial baseline.
- Keep detailed duplicate/recovery and PostgreSQL concurrency scenarios separate
  from successful local sequential tests. Do not test concurrency against a
  development/production database by injecting financial rows.
- Resolve the legal license metadata choice explicitly; no silent license edit.
- Put the README/report through the existing Git workflow when approved; they
  have not been committed or published by the guide.
- Admin reporting, wallets, purchases, and new financial schema remain absent.
  At that audit, Phase 6 had not started and production correctness was not
  signed off. The user subsequently authorized Phase 6 and deferred live
  financial verification to Phase 9; see the implementation record below.

## Phase 5C interactive production verification - in progress

The earlier Phase 5B results above remain historical evidence. This phase adds
no application features and authorizes no automatic production changes.

- GitHub main and the clean Desktop repository now report
  `76031f8641bd64dbc9ff9d312c7b045a1e941224`, Document setup and verification status.
  The active Cloud deployment commit still requires dashboard confirmation.
- The project-local and PATH Cloud CLI are still unavailable. The owner will
  perform read-only checks in the production environment's dashboard/Commands
  panel. No package installation or Cloud authentication was initiated.
- Reviewed queue settings, SubmitGeneration, ApplyGenerationSnapshot, schedule
  registration, configuration names, prior evidence, and the migration-status
  implementation before proposing diagnostics. The runtime preflight was syntax
  checked locally without execution; it prints selected non-secret fields and
  only CONFIGURED/MISSING credential presence.
- No additional generation or provider request has been executed in this phase.
  The user supplied the two planned prompts but explicitly requires approval
  before either creation. Image settings/current quotes must be reviewed first.

| ID  | Operation                                     | Status  | Evidence / next action                                                                                                  |
| --- | --------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------- |
| C01 | Production preflight and release identity     | BLOCKED | Await active Cloud SHA and selected runtime output; migration, worker, and scheduler evidence will follow interactively |
| C02 | A cinematic mountain landscape [succeed]      | NOT RUN | One proposed generation; approval pending, no local/provider ID or amounts yet                                          |
| C03 | Abstract geometric composition [fail]         | NOT RUN | One proposed generation; approval pending, zero-charge/no-settlement result not yet observed                            |
| C04 | Exact production-key UTC usage reconciliation | BLOCKED | Need production usage and DB aggregates; counts, costs, and differences remain unknown                                  |
| C05 | Authentic provider-event replay               | NOT RUN | Need an existing signed event, baseline settlement count, and explicit replay approval                                  |

Do not infer worker execution from its configured connection or scheduler
execution from schedule:list. No Phase 5C acceptance or Phase 6 readiness is
claimed while this evidence is outstanding.

## Phase 6 admin financial reporting - local implementation

The user explicitly authorized Bonus 1 and deferred production financial
reconciliation to Phase 9. Earlier production BLOCKED/NOT RUN entries remain
unchanged evidence, not claims that the new feature was exercised in production.
No wallet, purchases, onboarding tour, deployment, production mutation, or new
live provider request was performed.

Implemented a default-off protected admin capability, explicit console grant/
revoke command, UTC daily/user/day-user reports, paginated financial drill-down,
typed GET /usage parsing, and manually refreshed reconciliation. Existing
settlement/provider-generation algorithms were not changed. New schema is only
users.is_admin; the authored migration has not been applied to development or
production. All migrations in tests use the guarded SQLite memory database.

Exact aggregates use bounded chunked BigInteger accumulation and decimal-string
formatting, including totals above signed BIGINT. Reconciliation compares counts,
four token categories, and provider cost; one-nanodollar and token-only mismatches
remain mismatched. Failed/stale provider fetches are never shown as matched.
Snapshots are scalar arrays with environment/key/date isolation and global
per-key cooldown/atomic refresh locking. No financial facts are rewritten.

| Check                                                 | Status  | Evidence                                                                                                                      |
| ----------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------- |
| Focused automated feature tests                       | PASS    | 54 tests / 251 assertions; HTTP fake, SQLite memory                                                                           |
| Query count and bounded pages                         | PASS    | Tests cover selected page sizes, subsequent pages, and bounded queries without per-user reads                                 |
| PostgreSQL query syntax/planning                      | PASS    | Read-only EXPLAIN of grouped settlement/user query accepted on local PostgreSQL and used existing indexes; no records written |
| PostgreSQL concurrency / production report acceptance | NOT RUN | SQLite tests and EXPLAIN do not prove production locking or real provider equality                                            |
| Admin navigation and responsive UI                    | NOT RUN | Source/type/build checks only; no manual browser session claimed                                                              |
| Actual production-key usage reconciliation            | BLOCKED | Deferred to Phase 9; no production usage totals/differences obtained                                                          |
| Development/production admin migration and promotion  | NOT RUN | Explicit rollout/operator action required; none executed                                                                      |

Focused coverage includes fractional-cent totals without cent rounding,
large aggregates, multiple users/dates, UTC midnight and offset normalization,
historical rate changes, zero failed amounts, empty periods, missing settlements,
provider parsing/precision/errors/timeouts, exact matching and metric mismatches,
out-of-band usage, freshness/Retry-After, serialized database cache, key isolation,
overlap protection, guest/user/admin access, privilege escalation, console grants,
and cross-user generation isolation. Existing settlement uniqueness tests remain.

### Phase 6 quality gates

| Command                              | Status | Result                                                        |
| ------------------------------------ | ------ | ------------------------------------------------------------- |
| composer test --no-interaction       | PASS   | Pint PASS; PHPStan 0 errors; Pest 441 tests / 2112 assertions |
| composer lint:check --no-interaction | PASS   | Pint PASS                                                     |
| npm.cmd run types:check              | PASS   | vue-tsc PASS after fresh Wayfinder generation                 |
| npm.cmd run check                    | PASS   | Formatting PASS; 84 files linted without warnings/errors      |
| npm.cmd run build                    | PASS   | PASS; 3689 modules; existing optional fontaine warning        |

Known limitations: linear range scans are bounded in memory but cost grows with
settlements in the selected 92 days; cache snapshots expire and are not a durable
reconciliation audit ledger; historic provider key attribution is not persisted;
open UTC days may produce transient mismatches. No authenticated admin screenshot
or real-browser accessibility test was performed. The existing license metadata
discrepancy remains unresolved. Source changes are local and uncommitted.

Final local result: **441 tests / 2112 assertions**, with Pint, PHPStan (0 errors),
TypeScript, frontend checks, and build passing. The focused addition is 54 tests
and 251 assertions. No safety configuration or quality threshold was relaxed.

Fresh local route inspection confirms auth plus can:viewFinancialReports on all
three new endpoints. migrate:status confirms only the admin migration is pending.
The local route cache still described the pre-admin routes and caused dev tooling
to overwrite generated admin helpers. After confirming app.env=local and
app.url=http://localhost:8002, only that local route cache was deliberately
cleared, then Wayfinder regenerated. No configuration cache, .env, database,
production setting, or production deployment was changed. Existing settlement
logic, dependency manifests/lockfiles, and the separate Desktop copy remain
unchanged. Final source/secret/whitespace review is recorded below.

Final review covered all 32 manifest files against the saved task baseline or
the separate committed Desktop source. No configured secret values, private-key
markers, or whitespace errors were detected. Dependency manifests/lockfiles,
test-isolation files, and the lifecycle settlement action were unchanged. The
Desktop repository remains clean; no commit or push was made. The U: working
tree still has no commits, so ordinary tracked Git diff output alone cannot
represent this change set; the per-file baseline comparison was used instead.

Phase 6 is complete locally and ready as the foundation for Phase 7. An approved
release must apply the admin migration and explicitly provision an admin, then
perform an authenticated admin browser smoke test. Actual production financial
acceptance remains deferred to Phase 9; it is not inferred from automated tests.

### Phase 6 file manifest

32 source/documentation files: 21 new and 11 modified. Generated Wayfinder and
build artifacts are ignored and excluded.

```text
README.md
app/Console/Commands/ManageAdmin.php
app/Domain/Billing/FinancialReport.php
app/Domain/Billing/FinancialTotals.php
app/Domain/Billing/ProviderReconciliation.php
app/Domain/Billing/ReportPeriod.php
app/Http/Controllers/Admin/FinancialReportController.php
app/Http/Controllers/Admin/GenerationFinancialController.php
app/Http/Controllers/Admin/RefreshReconciliationController.php
app/Http/Middleware/HandleInertiaRequests.php
app/Http/Requests/FinancialReportRequest.php
app/Integrations/Pixelforge/PixelforgeClient.php
app/Integrations/Pixelforge/PixelforgeUsageData.php
app/Models/User.php
app/Providers/AppServiceProvider.php
database/factories/UserFactory.php
database/migrations/2026_11_25_042923_add_is_admin_to_users_table.php
docs/ARCHITECTURE.md
docs/VERIFICATION.md
resources/js/components/AppSidebar.vue
resources/js/components/generations/FinancialGenerationTable.vue
resources/js/components/generations/FinancialTable.vue
resources/js/components/generations/ProviderReconciliationPanel.vue
resources/js/components/generations/ReportPagination.vue
resources/js/pages/admin/financials/Index.vue
resources/js/pages/admin/financials/Show.vue
resources/js/types/financial-report.ts
resources/js/types/global.d.ts
routes/web.php
tests/Feature/AdminFinancialReportsTest.php
tests/Feature/ProviderUsageTest.php
tests/Fixtures/pixelforge/usage.json
```

## Phase 7 credit wallets

Local wallet coverage uses the existing fail-closed SQLite memory database and provider fakes. Automated scenarios cover purchase confirmation and replay, wallet scoping, blocked ledger mutations, competing admission at the exact boundary, a one-nanodollar shortfall, accepted-rate exposure, settlement replay, historical cutover and recovery. Interleaving operations sequentially verifies accounting effects. SQLite does not exercise PostgreSQL row locks.

| Check                                             | Status  | Evidence                                                                                                                                                                           |
| ------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Wallet accounting and authorization               | PASS    | 23 wallet cases, including constrained same-owner/amount settlement capture, a one-nanodollar shortfall, rate drift, rollback after debit insertion, and account closure.          |
| Full local quality gates                          | PASS    | 464 Pest tests, 2,367 assertions. PHPStan level 7 reports zero errors. Pint, TypeScript, frontend formatting/lint and the production build passed.                                 |
| Local migration status                            | PASS    | Read-only inspection shows the Phase 6 admin migration applied locally and the Phase 7 wallet migration pending. Development and production migrations were not run in this phase. |
| Disposable PostgreSQL concurrency                 | NOT RUN | The test guard only supports SQLite memory. Actual concurrent creates, purchases/admissions, duplicate webhooks and polling races require a separately isolated PostgreSQL target. |
| Production wallet migration, purchase and capture | NOT RUN | Deferred to release verification.                                                                                                                                                  |
| Manual wallet browser workflow                    | NOT RUN | No interactive browser smoke test recorded.                                                                                                                                        |

## Phase 8 product experience

| Check                                                             | Result  | Evidence                                                                                                                                                                                                                                                                                                                                                                        |
| ----------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Laravel tests, static analysis and formatting                     | PASS    | 465 Pest tests / 2,393 assertions. PHPStan zero errors. Composer test and lint:check passed. Landing asserts the guest Inertia page. Studio tests cover first-use eligibility with another user's history present.                                                                                                                                                              |
| Frontend gates                                                    | PASS    | TypeScript, formatting, lint (88 files) and Vite production build passed. The existing optional fontaine warning remains. Dependency manifests and lockfiles have no Phase 8 changes.                                                                                                                                                                                           |
| Responsive browser smoke                                          | PASS    | Headless Chrome with the production assets and Laravel-rendered pages from an isolated SQLite memory test. At 320, 375, 768, 1024 and 1440 CSS pixels: landing, Studio, empty and populated galleries, detail, wallet and admin reports had no document-level horizontal overflow. No JavaScript console errors or exceptions observed.                                         |
| Guide, image and error states                                     | PASS    | Chrome verified that an unanswered invitation stays dismissed on a later visit and exercised Next/Back, Skip, Finish, replay, reload, separate accounts, missing highlight target, Escape and focus return. Enlarged preview opened at 320 and 1440. A broken fixture and expired fixture showed their distinct messages. Simulated pricing unavailability disabled submission. |
| Hero fallback and motion                                          | PASS    | The layer control worked with WebGL disabled. Reduced motion produced a zero-duration transform transition. Dark-mode initialization and static artwork worked. Desktop and mobile screenshots were inspected; the README image is the guest landing page captured locally.                                                                                                     |
| Local performance sample                                          | PASS    | One unthrottled 1440 px localhost headless Chrome navigation recorded LCP 932 ms and CLS 0 through the first interaction. A DevTools click on the layer button plus two animation frames took 20 ms wall time. This is a local interaction sample, not field INP.                                                                                                               |
| Physical devices, assistive technology and production performance | NOT RUN | Screen-reader speech, physical touch/GPU load, Safari, production LCP/CLS/INP and live end-to-end acceptance remain manual. See MANUAL_TESTING.md.                                                                                                                                                                                                                              |

The Welcome JS entry changed from 28,505 bytes (6,010 gzip) to 6,902 bytes (2,916 gzip). Its scoped CSS is 6,961 bytes (2,153 gzip). The original static SVG is 27,931 bytes (5,188 gzip). These are file sizes and local gzip measurements. Entry size excludes shared application modules, including the reused settings controls, and total transfer depends on compression and cache state. No Three.js, Vue renderer or motion package was added.

Browser verification used an isolated Chrome profile, synthetic test records and a local image fixture, with external requests blocked. It exercised rendered UI and mock pricing failure. Production authentication, provider delivery, wallet accounting and reconciliation still require Phase 9 release verification. No real generation or purchase was triggered during the browser checks.

### Phase 8 files

- `README.md`
- `app/Http/Controllers/StudioController.php`
- `app/Http/Resources/GenerationResource.php`
- `docs/ARCHITECTURE.md`
- `docs/MANUAL_TESTING.md`
- `docs/VERIFICATION.md`
- `docs/images/studio-landing.png`
- `public/art/registration-study.svg`
- `resources/js/components/RegistrationStudy.vue`
- `resources/js/components/StudioGuide.vue`
- `resources/js/components/generations/FinancialTable.vue`
- `resources/js/components/generations/GenerationCard.vue`
- `resources/js/components/generations/GenerationComposer.vue`
- `resources/js/components/generations/GenerationImage.vue`
- `resources/js/components/generations/GenerationOptions.vue`
- `resources/js/components/generations/PriceEstimate.vue`
- `resources/js/components/generations/ProviderReconciliationPanel.vue`
- `resources/js/composables/useImageExpiry.ts`
- `resources/js/pages/Welcome.vue`
- `resources/js/pages/admin/financials/Index.vue`
- `resources/js/pages/generations/Index.vue`
- `resources/js/pages/generations/Show.vue`
- `resources/js/pages/studio/Index.vue`
- `resources/js/pages/wallet/Index.vue`
- `tests/Feature/ExampleTest.php`
- `tests/Feature/GenerationExperienceTest.php`
