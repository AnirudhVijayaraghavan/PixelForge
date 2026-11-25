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

## Release identity and source

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
  Phase 6 has not started; its production correctness prerequisite is not yet
  signed off.
