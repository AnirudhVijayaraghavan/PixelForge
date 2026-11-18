# Pixelforge Studio engineering standards

Read `docs/ASSESSMENT_PLAN.md` and `docs/ARCHITECTURE.md` before implementation.
Phase 0 establishes the foundation only. Begin product work only when requested.
Verify installed versions and use Boost documentation search for package APIs.
Keep Boost's generated guidelines and skills; extend them here without replacing
the generated `AGENTS.md` block.

## Structure and responsibilities

- Use a pragmatic domain-oriented modular monolith within Laravel's existing
  folders. Group by domain when needed; do not scaffold speculative modules.
- Prefer first-party Laravel features and established ecosystem packages. Keep
  controllers thin; use invokable controllers for distinct writes and resource
  controllers for cohesive CRUD. Use dedicated Form Requests for validation and
  authorization, with Policies/Gates enforcing ownership and admin access.
- Put workflows in focused Actions and reusable domain behavior in services.
  Keep business rules out of controllers, Vue components, and HTTP adapters.
- Use typed DTOs for external data, value objects for money, Eloquent for
  persistence, dependency injection, and interfaces at meaningful external
  boundaries. Do not wrap Eloquent in generic repositories.
- Use Jobs for asynchronous work, Scheduler for recovery, Laravel HTTP Client
  for external calls, and Events only when they remove real coupling.
- Prefer explicit readable code. Avoid CQRS, event sourcing, microservices,
  excessive interfaces, and packages without a demonstrated requirement.

## Financial and asynchronous invariants

- Money never uses binary floats. Persist exact integer USD nanodollars and
  use decimal arithmetic; preserve monetary frontend props as decimal strings.
- Customer price is provider cost times exactly 1.25. Preserve each generation's
  provider rates and usage. Follow the rounding policy in `ARCHITECTURE.md`;
  presentation rounding never changes settlement amounts.
- Settlement is atomic and idempotent, backed by SQL uniqueness and constraints.
  Charge at most once per generation; failed generations cost zero.
- Never call external HTTP services inside a database transaction. Dispatch
  work after commit and plan recovery for commit-to-dispatch gaps.
- Persist the local identity, immutable submission parameters, and stable
  provider idempotency key before submission; reuse them across retries.
- Verify webhook signatures against raw bytes before JSON interpretation.
  Durably deduplicate event IDs before acknowledging; prevent terminal-state
  regression and correlate webhook-before-response races through metadata.
- Separate provider status from local submission/recovery/settlement status
  where needed. A timeout is ambiguous, not proof of provider failure.
- Use bounded retries, documented Retry-After handling, and reconciliation.
  Read the official provider reference; never invent fields or guarantees.

## Security and product quality

- Authorize every user-owned resource on the server, including history/status
  reads; prevent IDOR and cross-user access. Protect admin endpoints explicitly.
- Validate untrusted inputs. Never expose credentials or signing secrets, and
  keep secrets, raw payloads, and sensitive user data out of logs.
- Preserve CSRF protection; exempt only the specifically signed webhook route.
- Reuse accessible shadcn-vue components and existing Vue/Tailwind conventions.
  Use Wayfinder for frontend routes and Inertia for pages. Keep the UI restrained,
  responsive, and clear about loading, failure, success, estimates, and charges.
- Ship required functionality before bonuses or speculative improvements.

## Tests and verification

- Use Pest, Pint, strict TypeScript, and the installed Vite+ lint/format tools.
  Keep existing static analysis; add no optional tools or infrastructure yet.
- Ordinary tests only use SQLite `:memory:`. `tests/bootstrap.php` checks actual
  loaded configuration before providers or destructive traits; `TestCase`
  repeats the check for every application boot. Do not bypass these guards,
  automatically clear development caches, or rely only on APP_ENV.
- PHPUnit forces isolated service drivers and dedicated test cache paths.
  Inertia SSR is disabled so page tests never contact a development server.
  Alternate database connections are removed after validation. No disposable
  integration database is authorized or supported in Phase 0. A future opt-in
  must validate its exact disposable target before any connection is opened.
- Use `Http::fake()` with specific URLs in provider tests; stray requests are
  blocked globally in Laravel tests. Control time/randomness when relevant.
- Test domain invariants, authorization, financial boundaries, concurrency,
  and failure paths with deterministic, isolated tests. SQLite does not prove
  PostgreSQL locking behavior; plan disposable integration coverage later.
- Commands: `composer test`, `composer lint:check`, `composer types:check`,
  `npm run types:check`, `npm run check`, `npm run build`. In Windows PowerShell
  use `npm.cmd` when script execution policy blocks `npm.ps1`. The frontend
  check bounds formatting to one thread to avoid worker resource exhaustion.
