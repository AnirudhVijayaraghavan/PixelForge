# Pixelforge Studio assessment plan

## Required outcome

- Laravel + Vue + Inertia application with authenticated users.
- Submit prompts, size, quality, and reference URLs; show a server-calculated
  estimate before submission and the exact charge after success.
- Charge the provider's actual cost plus 25%; failures are free.
- User-owned generation history with live status and useful failure feedback.
- Signed webhooks, duplicate/out-of-order safety, bounded retry/recovery, and
  public deployment with working workers, scheduler, and webhook delivery.

## Bonuses, after the core works

- Explicitly protected admin reports reconciling provider cost and revenue.
- Credit wallets, fake purchases, and atomic insufficient-funds prevention.

## Success criteria and risks

User A cannot read or mutate user B's records. Replays and concurrent webhook/
polling completion settle once. Terminal states never regress. Ambiguous
submission failures recover without creating a second provider generation.
Provider cost matches usage at the generation's frozen rates; failed work has
zero charge. Tests make no live provider calls and cannot use the normal DB.

Main risks: rounding drift, stale pricing, duplicate settlement, lost jobs,
webhook-before-response races, missing webhooks, rate limits, and signed image
URLs expiring after 24 hours. Choose image retention behavior explicitly before
shipping history. SQLite tests alone cannot establish production concurrency.

## Milestones

0. Audit, engineering guidance, safe isolated tests, baseline quality checks.
1. Specify persistence, money/estimate contracts, and provider DTOs using the
   official documentation; prove invariants before adding network workflows.
2. Implement durable submission, stable idempotency, jobs, signed webhook inbox,
   atomic settlement, and reconciliation with failure-path coverage.
3. Build authorized generation/estimate/history flows and live status UX.
4. Verify production concurrency on a separately approved disposable database;
   deploy publicly and validate workers, scheduler, secrets, and delivery.
5. Add bonuses only after required acceptance criteria pass.

## Provider contract and current scope

Reviewed the [official API reference](https://pixelforge.promptedit.dev/docs)
on 2026-11-17. It documents asynchronous generation, usage plus frozen rates,
account-scoped permanent idempotency keys, and unordered/repeated webhooks.
Use it as the source of truth and recheck it during implementation.

Phase 0 implements no pricing, domain models/migrations, provider calls, pages,
webhook processing, wallets, reports, or deployment. Other non-goals: real
payments, speculative features, CQRS/event sourcing, custom infrastructure, and
new analysis packages. Existing starter authentication and tools are retained.
