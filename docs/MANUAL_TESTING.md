# Pixelforge Studio manual testing

## Scope and execution rules

Phase 4.5B is a human-led browser and integration session in
`U:/Projects/Laravel Projects/pixelforge`. The separate GitHub Desktop copy is
out of scope and must remain untouched.

At the human's request, detailed group-by-group QA is deferred in favor of a
walkthrough of the main user features. Group A is complete; composer results are
recorded below. Unexecuted/deferred checks retain NOT RUN or BLOCKED status.
No real provider generation is approved until the human explicitly approves it.

- Status values: **PASS**, **FAIL**, **BLOCKED**, **NOT RUN**. A PASS requires
  human confirmation or genuine execution evidence for that specific scenario.
  Infrastructure PASS results do not establish browser functionality.
- The historical 373 Pest tests / 1755 assertions are not current manual evidence.
- The human approves each real-generation case or bounded batch, including the
  prompt and count, before submission. No automatic provider creations, browser
  creation resends, paid pagination filler, or replacement idempotency keys.
- Local PostgreSQL is real development data. No migrations, resets, seeding,
  destructive SQL, direct financial-state edits, or database-config changes.
- Preserve APP_KEY, authentication, policies, financial constraints, safe automated
  test isolation, and the Git workflow. No features, dependencies, or deployment.
- Diagnose with source, recent sanitized logs, read-only queries, and actual
  network responses. Identify a concrete defect before proposing a targeted fix
  and regression test. Do not rewrite working code automatically.
- Before public tunnel access, verify **effective APP_DEBUG=false**, safe public
  errors, correct signing configuration, and the intended callback.
- Never save/share passwords, API keys, signing secrets, cookies, CSRF tokens,
  Authorization headers, signed verification/reset URLs, full HAR files, raw
  signatures, or complete sensitive request/log bodies. Redact before recording.
- Stable local UUIDs and provider/event IDs may be used as limited correlation
  evidence; avoid unnecessary prompts and personal data.

## Session baseline

Inspected on 2026-11-18 UTC (2026-11-17 America/New_York), before human browser
results. Source guidance: AGENTS.md, .ai/guidelines/pixelforge.md,
docs/ARCHITECTURE.md, docs/ASSESSMENT_PLAN.md, the Phase 4.5A audit, and the
actual routes/authentication/UI/domain code.

- PostgreSQL: `pgsql`, `127.0.0.1:5433`, `pixelforge`; one verified user.
- Generation/event/settlement/job/failed-job counts: all zero.
- All 10 migrations were applied in Phase 4.5A; no migration was run here.
- Laravel: existing PHP listener on 8002, workspace ownership confirmed.
- Vite: **5175**, as selected by this workspace's `public/hot`; ownership and
  served Studio source confirmed. Another listener exists on 5173 with ownership
  unconfirmed; it was not stopped and no competing process was started.
- Effective environment: local; configuration cached; APP_DEBUG=true.
- API key: **CONFIGURED**; live validity not verified.
- Signing secret: **MISSING**. Queue/cache/session use database; mail uses log.
- No provider API endpoint, generation submission, or browser authentication was
  executed by the guide. One local Vite static-source GET and read-only database
  diagnostics establish only the technical preflight.
- No application defects have been confirmed. Existing environment observations
  are not assumed to be application defects.

The [official provider documentation](https://pixelforge.promptedit.dev/docs)
was read as public documentation only. Its fault directives establish the planned
cases: [succeed] suppresses random failure; [fail] reaches accepted failure;
[blocked] refuses creation for policy; [overloaded] refuses creation for overload;
[slow] takes 60-90 seconds; [duplicate] repeats each webhook delivery.
Recheck the contract before live execution. Documentation reads are not provider
API validation or permission to submit.

## Test matrix

Actual results, evidence, defects, and resolutions are updated after each human
report. A dash means no observation/defect/resolution has been established yet.

### Group A — Application foundation

| ID  | Action                                                                         | Expected result                                                                                                                                              | Actual result                                                                                                                                  | Status | Evidence / logs                                                                                                             | Defect | Resolution                                                                                                                               |
| --- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------ | --------------------------------------------------------------------------------------------------------------------------- | ------ | ---------------------------------------------------------------------------------------------------------------------------------------- |
| A01 | Inspect the listener on 8002 and its parent process chain.                     | The existing Laravel server belongs to the U: development folder.                                                                                            | PHP PID 33888 references this workspace; no Desktop-copy ownership found.                                                                      | PASS   | Process metadata inspected this session.                                                                                    | -      | -                                                                                                                                        |
| A02 | Inspect public/hot, its listening server, and the served Studio module.        | The hot-server URL serves this workspace's Vue code.                                                                                                         | public/hot uses IPv6 loopback port 5175; PID 28156 belongs to this workspace. Static Studio GET returned 200 and this workspace's source path. | PASS   | Local static GET /resources/js/pages/studio/Index.vue on 5175; process ancestry.                                            | -      | -                                                                                                                                        |
| A03 | Read the actual PostgreSQL identity and baseline counts.                       | Use local pixelforge at 127.0.0.1:5433; establish an untouched generation baseline.                                                                          | One verified user; zero generations, webhook events, settlements, jobs, and failed jobs.                                                       | PASS   | Boost SELECT current_database(), server address/port, and aggregate counts.                                                 | -      | -                                                                                                                                        |
| A04 | Visit Studio in a signed-out private browser window.                           | Redirect to the login page; no private Studio data is shown.                                                                                                 | User confirmed signed-out Studio access redirects to login.                                                                                    | PASS   | Human reply: "all passed" for the first A04-A07 browser batch; no raw network/log export supplied.                          | -      | -                                                                                                                                        |
| A05 | Log in using the existing verified test account.                               | Successful POST /login redirects to the intended Studio or dashboard; the Studio renders.                                                                    | User confirmed login with the existing verified account renders Studio.                                                                        | PASS   | Human reply: "all passed" for the first A04-A07 browser batch; no raw network/log export supplied.                          | -      | -                                                                                                                                        |
| A06 | Navigate between Studio and My Generations using the sidebar.                  | Both pages load; Studio has its composer and summary; history initially shows an empty state.                                                                | User confirmed Studio/history navigation and the initial empty gallery work.                                                                   | PASS   | Human reply: "all passed" for the first A04-A07 browser batch; no raw network/log export supplied.                          | -      | -                                                                                                                                        |
| A07 | Refresh Studio and open it in another tab in the same browser session.         | The authenticated user remains signed in; no generation is created.                                                                                          | User confirmed refresh and a second tab retain authentication; read-only counts remain zero for generations/jobs/settlements.                  | PASS   | Human reply: "all passed" for the first A04-A07 browser batch; no raw network/log export supplied.                          | -      | -                                                                                                                                        |
| A08 | Use the account menu's Log out action, then request Studio again.              | The session loses authenticated access; a fresh protected request redirects to login.                                                                        | User confirmed logout removes authenticated access and Studio redirects to login.                                                              | PASS   | Human reply: "all passed" for A08-A11; no raw credential/network export supplied.                                           | -      | -                                                                                                                                        |
| A09 | Try one incorrect password, then correct credentials.                          | A safe field error appears without login; corrected credentials work. Do not induce a lockout by repetition.                                                 | User confirmed an incorrect password is rejected and correct credentials still authenticate.                                                   | PASS   | Human reply: "all passed" for A08-A11; no raw credential/network export supplied.                                           | -      | -                                                                                                                                        |
| A10 | Try invalid registration fields and mismatched confirmation.                   | Accessible errors appear; no user is created for invalid input.                                                                                              | User confirmed the instructed password-confirmation validation case rejects registration.                                                      | PASS   | Human reply: "all passed" for A08-A11; no raw credential/network export supplied.                                           | -      | -                                                                                                                                        |
| A11 | Register a second QA account through the UI with privately chosen credentials. | Exactly one new user is created with a hashed password; registration signs the user in. Never reuse the existing user's password.                            | User confirmed registration reaches the email-verification screen. Independent Laravel CLI SELECT confirms two users, one verified.            | PASS   | Human: "all passed" and "Email verification screen"; independent CLI count: 2 users / 1 verified.                           | -      | -                                                                                                                                        |
| A12 | Follow the second account's real email-verification flow.                      | With log mail, inspect the verification link privately; dashboard requires verification and works afterward. No signed link is copied into this file.        | Initial attempt returned 403. User confirmed the fresh-email retry passed; independent read-only SQL now shows 2 users / 2 verified.           | PASS   | Human reply: "all passed" after the targeted retry; daily logging is debug; generations, jobs, and settlements remain zero. | ENV-03 | Resolved by the human's local logging/configuration refresh and a newly resent verification link. Initial failure is retained in ENV-03. |
| A13 | Inspect Studio, gallery, login, and the mobile menu around 390 px and 768 px.  | Controls remain readable and usable with no horizontal overflow or clipped primary actions.                                                                  | User confirmed the instructed mobile layouts, menu, and login-page checks passed.                                                              | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |
| A14 | Navigate the foundation pages using Tab, Shift+Tab, Enter, Space, and Escape.  | Focus is visible; navigation/menu controls are operable; no keyboard trap occurs.                                                                            | User confirmed keyboard navigation, visible focus, and menu controls passed.                                                                   | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |
| A15 | Use Settings > Appearance to try light, dark, and system.                      | Theme remains usable and respects the chosen preference after refresh.                                                                                       | User confirmed Light/Dark/System selection and refresh persistence passed.                                                                     | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |
| A16 | Open Profile and Security settings without changing credentials.               | Existing settings pages load, ownership is correct, and Studio navigation remains available.                                                                 | User confirmed Profile and Security pages, including password confirmation where applicable, loaded correctly.                                 | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |
| A17 | Inspect Console and Network throughout A04-A16.                                | No unexplained app errors, failed required assets, CORS/HMR failures, or unexpected 5xx responses; expected validation/redirect responses are distinguished. | User confirmed no unexplained Console/Network failures in the final foundation batch; the earlier verification issue is resolved in ENV-03.    | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |
| A18 | Exercise Remember me with a deliberate browser-session restart.                | The selected persistence behavior works; logout still revokes access. Record browser/session-restore behavior before interpreting results.                   | User confirmed Remember me restart/session-cookie-removal reauthentication and final logout protection passed.                                 | PASS   | Human reply: "all passed" for A13-A18; no private credentials, cookies, or raw network export supplied.                     | -      | -                                                                                                                                        |

### Group B — Generation composer

| ID  | Action                                                                                      | Expected result                                                                                                    | Actual result                                                                                                                                                              | Status  | Evidence / logs                                                                                                                                | Defect                                                   | Resolution                                                            |
| --- | ------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------- | --------------------------------------------------------------------- |
| B01 | Try an empty prompt and whitespace-only prompt without generating.                          | Generate remains unavailable; no generation POST is sent.                                                          | User confirmed empty and spaces-only prompts show zero characters and keep Generate disabled.                                                                              | PASS    | Human reply: "all passed" for B01/B02/B05/B06; read-only SQL confirms zero generations/jobs/settlements.                                       | -                                                        | -                                                                     |
| B02 | Edit leading/trailing whitespace and normal text.                                           | Character count and submitted normalization agree with Laravel trimming.                                           | User confirmed a space-padded hello counts as 5 and the quote request contains normalized prompt hello.                                                                    | PASS    | Human reply: "all passed" for B01/B02/B05/B06; read-only SQL confirms zero generations/jobs/settlements.                                       | -                                                        | -                                                                     |
| B03 | Exercise ASCII prompts around 3999, 4000, and 4001 characters.                              | 4000 is supported; an over-limit value is clearly rejected or prevented without submission.                        | User confirmed ASCII counts at 3999/4000/4001 and the over-limit error/disabled Generate.                                                                                  | PASS    | Human reply: "all passed now what ?" for B03/B04/B07/B08. Last read-only SQL: zero generations/jobs/settlements; no raw private data supplied. | -                                                        | -                                                                     |
| B04 | Exercise emoji and multibyte Unicode around the same boundary.                              | The count uses Unicode code points, not UTF-16 units or UTF-8 bytes; server and browser agree.                     | User confirmed single-code-point emoji counts at the same boundaries; 4000 emojis do not count as 8000.                                                                    | PASS    | Human reply: "all passed now what ?" for B03/B04/B07/B08. Last read-only SQL: zero generations/jobs/settlements; no raw private data supplied. | -                                                        | -                                                                     |
| B05 | Choose square, landscape, and portrait.                                                     | All three documented sizes can be selected, including with a keyboard; current selection is clear.                 | User confirmed all three size values, selected states, and keyboard changes work.                                                                                          | PASS    | Human reply: "all passed" for B01/B02/B05/B06; read-only SQL confirms zero generations/jobs/settlements.                                       | -                                                        | -                                                                     |
| B06 | Choose low, medium, and high quality.                                                       | Each selection works and higher-quality cost guidance is visible.                                                  | User confirmed all three qualities and keyboard selections work.                                                                                                           | PASS    | Human reply: "all passed" for B01/B02/B05/B06; read-only SQL confirms zero generations/jobs/settlements.                                       | -                                                        | -                                                                     |
| B07 | Add/remove zero, one, and four reference URLs; attempt a fifth.                             | Zero through four are supported; a fifth is prevented or rejected; removal and labels remain correct.              | User confirmed zero/one/four references, disabled fifth addition, and preserving remaining values after removal.                                                           | PASS    | Human reply: "all passed now what ?" for B03/B04/B07/B08. Last read-only SQL: zero generations/jobs/settlements; no raw private data supplied. | -                                                        | -                                                                     |
| B08 | Try malformed URLs and file:, javascript:, data:, and ftp: schemes.                         | Invalid references are rejected with useful errors; valid HTTP(S) references are accepted.                         | User confirmed malformed and unsupported-scheme references show validation errors; correcting/removing the field clears the issue.                                         | PASS    | Human reply: "all passed now what ?" for B03/B04/B07/B08. Last read-only SQL: zero generations/jobs/settlements; no raw private data supplied. | -                                                        | -                                                                     |
| B09 | Cause server-side quote validation errors using a bounded request.                          | Errors attach to the appropriate inputs and preserve the draft; no generation exists.                              | User reported the final server-validation check passed, including the expected field error and preserved inputs.                                                           | PASS    | Human reply: "all passed" to the final Group B batch; no raw private network capture supplied.                                                 | -                                                        | -                                                                     |
| B10 | Edit a draft, refresh/navigate away and back, and switch QA accounts.                       | Same-tab draft recovery works and saved drafts remain scoped to the user; reuse inputs do not create a generation. | User reported refresh/navigation draft restoration and same-tab account isolation passed.                                                                                  | PASS    | Human reply: "all passed" to the final Group B batch.                                                                                          | -                                                        | -                                                                     |
| B11 | Review labels, descriptions, focus order, error announcements, and reduced-motion behavior. | Composer controls and validation feedback are accessible without mouse-only interactions.                          | User reported all passed, but supplied no requested aria-invalid value. Specific server-error accessibility state remains unverified; detailed retest deferred by request. | NOT RUN | General human pass report recorded; source currently binds aria-invalid only to client URL validity, so DOM confirmation is still needed.      | Accessibility follow-up; not a confirmed browser defect. | Deferred during feature walkthrough; no application-code change made. |

### Group C — Live estimated pricing

| ID  | Action                                                                                             | Expected result                                                                                                                                     | Actual result                                                                                                                                                                 | Status  | Evidence / logs                                                                                                              | Defect     | Resolution                                                                        |
| --- | -------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------- | ---------- | --------------------------------------------------------------------------------- |
| C01 | Enter a valid prompt and inspect the authenticated quote request.                                  | The server obtains public pricing without bearer authentication; the browser contacts only its same-origin quote endpoint.                          | Human confirms pricing now works after the requested browser refresh/option-change retest. The cache-hit 500 was reproduced and fixed, and backend repeated quotes also pass. | PASS    | Human reply: "works, now what ? lets continue with the usage handover"; serialized-cache regression and full PHP gates pass. | PRICING-01 | RESOLVED after human browser retest; continue to one human-controlled generation. |
| C02 | Compare returned token estimates/rates and exact quote with GenerationPricing.                     | The deterministic estimate follows the provider parameters and Unicode input; decimal strings remain exact.                                         | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C03 | Compare low, medium, and high with other inputs fixed.                                             | Quotes respond to quality using server-side output/reasoning parameters.                                                                            | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C04 | Compare square and rectangular sizes with other inputs fixed.                                      | Quotes reflect the documented size-dependent output tokens.                                                                                         | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C05 | Compare zero, one, and four references.                                                            | Reference-token estimates and prices change consistently with provider parameters.                                                                  | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C06 | Type quickly and inspect request timing.                                                           | Text editing is debounced about 450 ms; obsolete work does not flood the endpoint.                                                                  | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C07 | Throttle quote responses, then change size/quality/prompt quickly.                                 | An older response cannot replace the current input's quote.                                                                                         | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C08 | Review estimate disclosures and creation's independent repricing path.                             | Estimate, bounds, accepted-rate provenance, and actual charge remain distinct; a bound is not advertised as a guaranteed cap.                       | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C09 | Compare generation/job/settlement counts before and after quotes.                                  | All three counts stay unchanged. Ordinary session/cache writes are allowed.                                                                         | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C10 | Add client-controlled price, rate, metadata, identity, usage, or status fields to a quote request. | Unsupported fields cannot control billing; shared validation rejects them.                                                                          | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C11 | Use DevTools request blocking on the local quote endpoint, then restore it.                        | The UI shows unavailable pricing, preserves the draft, and disables Generate until a valid quote returns. This proves client failure handling only. | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C12 | Exercise a genuine or separately approved controlled provider-pricing outage.                      | The backend returns sanitized 503 pricing_unavailable with no generation/job/settlement; do not infer this server path from C11.                    | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |
| C13 | Inspect the quote payload and frontend rendering.                                                  | Exact money is supplied as strings, the 25% markup is explained, and Vue performs no monetary arithmetic.                                           | Awaiting human execution.                                                                                                                                                     | NOT RUN | -                                                                                                                            | -          | -                                                                                 |

### Group D — Real Pixelforge connection

| ID  | Action                                                                                              | Expected result                                                                                                                                     | Actual result                                                                                                                                                              | Status  | Evidence / logs                                                                                                                | Defect | Resolution                                                                                                                |
| --- | --------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------ | ------ | ------------------------------------------------------------------------------------------------------------------------- |
| D01 | Before any tunnel, verify effective debug configuration and public error exposure.                  | APP_DEBUG is false in resolved Laravel configuration; public errors disclose no stack/configuration/secrets.                                        | User disabled debug; effective CLI config confirms false. Local anonymous JSON 404 exposes no trace, exception class, or file path. Public-tunnel recheck remains pending. | PASS    | Human configuration confirmation, whitelisted runtime inspection, and genuine local GET 404 probe.                             | -      | -                                                                                                                         |
| D02 | Make an approved private authenticated read-only provider check and capture a usage baseline.       | Confirm the API key through a successful authenticated endpoint such as documented GET /usage; no key enters browser code, history, or shared logs. | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |
| D03 | Obtain this API key's signing secret and configure it privately when directed.                      | The correct signing secret is configured in effective Laravel configuration after an intentional cache refresh/restart.                             | Human reports successful configuration and dashboard-signed ping HTTP 204; the callback's mandatory signature verification accepted it. No secret value shared.            | PASS    | Genuine human-provided dashboard delivery result; independent local event-row confirmation was interrupted and is not claimed. | -      | Signed connection check complete; this does not verify outbound generation authentication or actual financial settlement. |
| D04 | Configure an existing approved HTTPS tunnel to local port 8002 and the matching dashboard callback. | Only after D01, the provider can reach the correct U: application; callback path is /webhooks/pixelforge.                                           | Human's public callback first returned 405 in the browser, then accepted the provider's signed ping with HTTP 204.                                                         | PASS    | Genuine human-provided dashboard delivery result; independent local event-row confirmation was interrupted and is not claimed. | -      | Signed connection check complete; this does not verify outbound generation authentication or actual financial settlement. |
| D05 | Send a dashboard-signed ping.                                                                       | HTTP 204; one processed ping event, no associated generation or financial settlement.                                                               | Human reports ping evt_01M7VGWGDCFXVVM458BBV0R93B, key label local, attempt 1 succeeded, HTTP 204 in 2301 ms, dashboard timestamp Nov 19 2026 10:44:31 PM.                 | PASS    | Genuine human-provided dashboard delivery result; independent local event-row confirmation was interrupted and is not claimed. | -      | Signed connection check complete; this does not verify outbound generation authentication or actual financial settlement. |
| D06 | Send unsigned, malformed, or wrong-signature local webhook requests.                                | HTTP 400/401 as appropriate; no event, generation, or settlement mutation.                                                                          | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |
| D07 | Replay with modified raw bytes, invalid digest, expired timestamp, and excessive future skew.       | Authentication fails before JSON processing and before all writes; valid Unicode raw bodies verify.                                                 | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |
| D08 | Test signed malformed envelopes, type/status inconsistency, and convenience-header disagreement.    | Safe non-2xx responses; no false event processing or billing. Missing local generation is retryable rather than silently acknowledged.              | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |
| D09 | Inspect tunnel/dashboard/log evidence and callback configuration privately.                         | No signing secret, bearer token, complete sensitive body, or sensitive debug dump is exposed or saved.                                              | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |
| D10 | Confirm machine clock, API key/callback association, and the app's normal CSRF protection.          | The correct environment's signatures can verify; only the webhook is exempt from browser CSRF.                                                      | Awaiting human execution.                                                                                                                                                  | NOT RUN | -                                                                                                                              | -      | -                                                                                                                         |

### Group E — First real generation

| ID  | Action                                                                                                     | Expected result                                                                                                                                              | Actual result                                                                                                                                                  | Status  | Evidence / logs                                                                                                   | Defect | Resolution                                                           |
| --- | ---------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------- | ------ | -------------------------------------------------------------------- |
| E01 | Obtain explicit human approval for one named [succeed] prompt and its estimated cost.                      | Approve exactly one creation before clicking Generate; no automatic provider submissions or resends.                                                         | Human confirms the deliberately chosen first Square/Low/no-reference [succeed] generation completed after reviewing the quote.                                 | PASS    | Human: yes all passed; one local generation, one provider identity, one submission attempt.                       | -      | No generation POST was executed by the guide.                        |
| E02 | Identify/start the intended database worker on queue pixelforge and the independent scheduler.             | A real async worker uses the U: configuration; no competing worker/server is started inadvertently.                                                          | Human confirms the pixelforge queue job worked; persisted submission_attempts=1 and reconciliation_attempts=0. Independent scheduler startup is not confirmed. | NOT RUN | Live worker behavior verified; complete worker-plus-scheduler scenario remains partially unverified.              | -      | Confirm scheduler separately before deployment.                      |
| E03 | Click Generate exactly once and capture the local response.                                                | HTTP 202 contains a local UUID, local status, and server-computed decimal-string estimate; duplicate clicks are prevented.                                   | Human confirms successful browser submission and detail flow. A raw browser HTTP 202 response was not retained.                                                | NOT RUN | Browser success is human-reported; the exact response-capture scenario remains unverified.                        | -      | Do not claim a captured Network response.                            |
| E04 | Inspect the committed local generation and durable queue dispatch, using an agreed worker pause if needed. | Local identity, immutable payload, key, quote, and recovery lease exist before provider POST; a fast-consumed jobs row need not remain visible.              | The completed local record has one provider identity and one settlement. No worker-paused observation of the pre-submission jobs row was performed.            | NOT RUN | Read-only inspection after completion; no deliberate queue pause.                                                 | -      | -                                                                    |
| E05 | Observe submission acceptance and inspect safe correlation fields.                                         | Exactly one provider ID attaches to the local generation; metadata.local_generation_id and original idempotency key correlate; accepted rates are preserved. | One submitted local generation has a provider ID. A read-only authenticated provider GET confirms matching local_generation_id, usage, and accepted rates.     | PASS    | Provider GET comparison: identity/usage/rates match; submission_attempts=1.                                       | -      | Retry identity behavior remains a separate deferred case.            |
| E06 | Observe processing/success webhook delivery in the dashboard and local event records.                      | Verified deliveries return 204 and processed events associate with this generation.                                                                          | Generation-associated processing and succeeded events are both marked processed. The prior dashboard ping independently returned HTTP 204.                     | PASS    | Local processing event processed 2026-11-20 04:26:20 UTC; succeeded event processed 04:26:24 UTC.                 | -      | No image-event dashboard latency or raw response capture is claimed. |
| E07 | Watch the detail page through success.                                                                     | The local state reaches succeeded, image renders, and expiry metadata is shown without invented progress percentages.                                        | Human confirms the image/detail walkthrough passed; persisted provider status is succeeded and image metadata is present.                                      | PASS    | Human browser confirmation plus read-only local/provider state.                                                   | -      | -                                                                    |
| E08 | Read the generation and its settlement after success.                                                      | Exactly one settlement contains exact integer nanodollars; succeeded state, usage, rates, completion time, and settled_at agree.                             | Exactly one settlement: provider cost 0.017515000 USD and customer charge 0.021893750 USD; settled_at 2026-11-20T04:26:24+00:00.                               | PASS    | Local totals: 1 generation, 1 settlement, 0 queued jobs, 0 failed jobs; exact total markup independently matches. | -      | -                                                                    |
| E09 | Compare the actual provider generation/usage and local Cost Inspector.                                     | Provider identity, confirmed category usage, accepted rates, image metadata, and exact actual charge reconcile.                                              | Authenticated read-only provider GET matches persisted identity, confirmed usage, accepted rates, and both exact financial amounts.                            | PASS    | Existing GenerationPricing recalculation agrees with GenerationSettlement; no provider POST by the guide.         | -      | Account-wide GET /usage comparison remains NOT RUN.                  |

### Group F — Failure and reliability

| ID  | Action                                                                                                        | Expected result                                                                                                                                                   | Actual result                                                                                                                                                     | Status  | Evidence / logs                                                                                                                                | Defect | Resolution                                                   |
| --- | ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------------------------------------- | ------ | ------------------------------------------------------------ |
| F01 | Approve a bounded live-case budget before fault prompts or operational interruptions.                         | Record each approved creation; retries reuse the same saved request/key and do not create new local requests.                                                     | Human deliberately completed the single proposed [succeed] case, then the single proposed [fail] case. No additional cases or automated submissions are approved. | PASS    | Human confirmation after each bounded browser exercise; local counts show one success and one failure.                                         | -      | Every further generation requires a deliberate human choice. |
| F02 | Submit one separately approved [fail] prompt.                                                                 | Provider accepts and later reports failure; local provider state is failed, actual charge is zero, and no successful settlement exists.                           | Human reports all passed for the instructed [fail] example. Read-only PostgreSQL shows one submitted provider-failed generation with zero successful settlements. | PASS    | One failed generation / zero settlements; prior succeeded generation still has exactly one settlement. Zero-charge display confirmed by human. | -      | No provider POST executed by the guide.                      |
| F03 | Submit one separately approved [blocked] prompt.                                                              | Submission is rejected with content_policy_violation before a provider generation is created; local rejection is distinct from provider failure and is uncharged. | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F04 | Submit one separately approved [overloaded] prompt.                                                           | 503 overload follows bounded delayed retries with Retry-After; no provider generation or settlement is created for rejected attempts, and exhaustion is explicit. | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F05 | Submit one separately approved [slow] prompt, optionally combined with [succeed] after contract confirmation. | A 60-90 second provider job remains active rather than falsely failing; polling/recovery does not create a replacement request.                                   | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F06 | Submit one separately approved [duplicate] case.                                                              | Each documented webhook delivery is repeated but event IDs deduplicate and the generation has at most one settlement.                                             | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F07 | Replay an authentic completed generation event with valid current signing authentication.                     | The duplicate returns 204 and financial facts/event-processing timestamps are not duplicated or replaced.                                                         | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F08 | Deliver an authentic stale processing snapshot after a terminal event.                                        | The stale event is processed without regressing success/failure or clearing usage, image, timestamps, or settlement.                                              | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F09 | Observe browser/network uncertainty during one approved creation; inspect history before any new click.       | The UI retains an uncertainty warning, never auto-resubmits creation, and never claims a confirmed free failure.                                                  | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F10 | Interrupt only the identified submission worker at an agreed point, then restart it.                          | Durable leases/recovery rescue eligible work with its original key and payload; bounded budgets survive restarts.                                                 | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F11 | Temporarily withhold webhook delivery for an approved generation using an agreed provider/tunnel control.     | Scheduled GET reconciliation reaches the correct terminal state and invokes the same settlement action once.                                                      | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F12 | Observe webhook-before-POST-response and replay the later accepted queued snapshot, if safely reproducible.   | Webhook settlement wins; a late queued response cannot regress the terminal generation. Mark BLOCKED if no safe reproducible timing exists.                       | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F13 | Overlap genuine success delivery/reconciliation or two distinct success events for the same generation.       | One immutable settlement survives concurrent processing; use real events, no invented usage/rates or direct financial writes.                                     | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F14 | Observe an exhausted retry budget or a documented permanent provider error.                                   | Automatic attempts stop with diagnosable state; uncertainty remains unresolved, not fabricated provider failure; no infinite recovery cycle.                      | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |
| F15 | Redeliver after a genuinely observed transient transaction failure, if available safely.                      | The failed transaction leaves no partial event/settlement and the next valid delivery can succeed. No fault hooks or production-table tampering are introduced.   | Awaiting human execution.                                                                                                                                         | NOT RUN | -                                                                                                                                              | -      | -                                                            |

### Group G — Financial correctness

| ID  | Action                                                                                                     | Expected result                                                                                                                                      | Actual result                                                                                                                                           | Status  | Evidence / logs                                                                                                  | Defect | Resolution                                                              |
| --- | ---------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ---------------------------------------------------------------------------------------------------------------- | ------ | ----------------------------------------------------------------------- |
| G01 | Compare stored nanodollars with exact nine-decimal detail-page values.                                     | Provider and customer amounts convert exactly using 1 USD = 1000000000 nanodollars, without cent rounding.                                           | Saved integer amounts convert to provider 0.017515000 USD and customer 0.021893750 USD; human confirms the Cost Inspector walkthrough.                  | PASS    | Existing Money conversion and actual local settlement; browser values confirmed in the guided all-passed report. | -      | -                                                                       |
| G02 | Independently verify confirmed category counts times accepted rates per million.                           | The category sum equals the stored provider total exactly; no binary-float money arithmetic is used.                                                 | Confirmed provider usage and accepted rates recalculate to the exact persisted provider and customer totals.                                            | PASS    | Read-only authenticated generation GET plus existing deterministic GenerationPricing.                            | -      | -                                                                       |
| G03 | Verify the total markup and its documented rounding.                                                       | Customer charge equals total provider cost times 1.25, with one HALF_UP rounding to nine decimals only when required.                                | 0.017515000 multiplied by exactly 1.25 equals 0.021893750 USD.                                                                                          | PASS    | Independent BigDecimal comparison using scale 9 and HALF_UP returned true.                                       | -      | This example needs no fractional-nanodollar rounding.                   |
| G04 | Compare original estimate, actual charge, and displayed difference.                                        | Actual billing uses confirmed usage, not estimated tokens or current public rates; the difference is exact.                                          | Original estimate 0.018000000 USD; actual customer charge 0.021893750 USD; actual is 0.003893750 USD higher.                                            | PASS    | Persisted quote/settlement and human-confirmed Cost Inspector walkthrough.                                       | -      | The estimate is not a guaranteed cap.                                   |
| G05 | Compare accepted generation rates across DTO, persistence, and Cost Inspector.                             | Accepted rates are unchanged by later snapshots and authoritative for settlement.                                                                    | The accepted snapshot matches the provider GET. Immutability under later changed snapshots was not manually exercised.                                  | NOT RUN | One real accepted rate snapshot verified; adversarial replay remains deferred.                                   | -      | -                                                                       |
| G06 | Observe legitimately different provider rates across generations when available.                           | Each generation settles at its own accepted snapshot. Do not edit live financial snapshots to manufacture a rate change.                             | Awaiting human execution.                                                                                                                               | NOT RUN | -                                                                                                                | -      | -                                                                       |
| G07 | Inspect failed and rejected records plus repeated success/replay records.                                  | Failed/rejected work has no successful settlement; successful work has one immutable settlement, supported by catalog-visible unique/FK constraints. | Failed generation has no settlement; previous success retains one. Rejection, replay, and catalog inspection portions have not been manually completed. | NOT RUN | Read-only grouped counts: failed 1 / settlements 0; succeeded 1 / settlements 1.                                 | -      | Partial financial evidence retained without claiming the full scenario. |
| G08 | Make a private documented GET /usage and reconcile its completion-day totals with local data and baseline. | Use the same API key and UTC interval; account for prior/external activity and provider completion timing. No admin reporting feature is added.      | Awaiting human execution.                                                                                                                               | NOT RUN | -                                                                                                                | -      | -                                                                       |
| G09 | Compare completion timestamps and UTC-day grouping near a real boundary when practical.                    | Reporting attribution follows provider completion in UTC, not browser timezone, submission date, or settlement date.                                 | Awaiting human execution.                                                                                                                               | NOT RUN | -                                                                                                                | -      | -                                                                       |
| G10 | Aggregate actual stored provider/customer nanodollars across approved generations.                         | Totals sum exact stored values without per-generation cent rounding or duplicate settlement inclusion.                                               | Awaiting human execution.                                                                                                                               | NOT RUN | -                                                                                                                | -      | -                                                                       |

### Group H — History and Cost Inspector

| ID  | Action                                                                                            | Expected result                                                                                                                                                            | Actual result                                                                                                                                         | Status  | Evidence / logs                                                                                                   | Defect | Resolution                                   |
| --- | ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------------------- | ------ | -------------------------------------------- |
| H01 | View My Generations under each QA account.                                                        | Only owned records appear with deterministic newest-first ordering.                                                                                                        | Human confirms the populated owner history flow. Viewing the same records from the second QA account has not been confirmed.                          | NOT RUN | Owner history covered; the complete two-account scenario remains pending.                                         | -      | -                                            |
| H02 | Exercise page 1/page 2 with more than 12 legitimate owned records when available.                 | Pagination is server-side and bounded; ties are deterministic. Do not create paid filler automatically.                                                                    | Awaiting human execution.                                                                                                                             | NOT RUN | -                                                                                                                 | -      | -                                            |
| H03 | Watch active visible cards and detail; hide/restore the tab and navigate away.                    | Local status polling is non-overlapping, about four seconds apart, paused while hidden, cleaned up on navigation, and stopped at terminal states.                          | Human confirms the live success flow. Hidden-tab pause, overlap timing, navigation cleanup, and request cadence were not specifically captured.       | NOT RUN | Foreground flow reported passed; detailed polling behavior not claimed.                                           | -      | -                                            |
| H04 | Inspect succeeded images and handle a genuine missing/broken result URL.                          | Useful alt text, image link/expiry, and load-error fallback work without rehosting images.                                                                                 | Human confirms the real succeeded image renders. A missing or broken image URL was not manually exercised.                                            | NOT RUN | Live rendering verified; fallback-specific behavior remains unverified.                                           | -      | -                                            |
| H05 | Revisit a naturally expired provider image after its documented lifetime.                         | The UI explains expiry and retains generation/cost history; no database expiry is rewritten to fake this result.                                                           | Awaiting human execution.                                                                                                                             | NOT RUN | -                                                                                                                 | -      | -                                            |
| H06 | Inspect failed, rejected, pending, retrying, and uncertain cards/details.                         | Free confirmed failures differ from acceptance uncertainty; no fabricated usage, zero-cost success, or false failure is shown.                                             | Human confirms failed-generation status and zero-charge display. Rejected, retrying, and uncertain presentation have not all been manually exercised. | NOT RUN | Controlled [fail] browser result and local failed/no-settlement record verified.                                  | -      | Other states remain deferred.                |
| H07 | Inspect the full Cost Inspector.                                                                  | Four categories, tokens, total tokens, accepted rates, category costs, provider total, 25% markup, actual total, original estimate, and difference are readable and exact. | Human confirms the instructed Cost Inspector walkthrough; provider 0.017515000 USD, customer 0.021893750 USD, estimate 0.018000000 USD.               | PASS    | Exact usage/rates/costs independently agree with provider GET and unique local settlement.                        | -      | -                                            |
| H08 | Choose Reuse Prompt from a record, edit the Studio inputs, then leave without generating.         | Only the owned original inputs are prefilled; no creation, settlement, or automatic charge occurs.                                                                         | Human confirms the instructed Reuse Prompt prefill walkthrough; local count remains one generation and one settlement.                                | PASS    | Human all-passed response covers the preceding reuse-without-generation instruction; read-only counts remain one. | -      | No second generation submitted by the guide. |
| H09 | Inspect history/status SQL activity and query counts during representative reads.                 | Settlements are eager-loaded, history is paginated, and read endpoints neither call the provider nor perform billing mutations.                                            | Awaiting human execution.                                                                                                                             | NOT RUN | -                                                                                                                 | -      | -                                            |
| H10 | Inspect an actually observed successful record with missing/inconsistent financial data, if any.  | The UI treats this as unavailable/invariant failure, never free success. Do not delete or alter a settlement to fabricate this case.                                       | Awaiting human execution.                                                                                                                             | NOT RUN | -                                                                                                                 | -      | -                                            |
| H11 | Inspect dates, prompt text, responsive cards, price alignment, loading, and network-error states. | UTC labels and precise strings are clear; unsafe HTML is never rendered; errors preserve the last known state.                                                             | Awaiting human execution.                                                                                                                             | NOT RUN | -                                                                                                                 | -      | -                                            |

### Group I — Security

| ID  | Action                                                                                                 | Expected result                                                                                                                                              | Actual result             | Status  | Evidence / logs | Defect | Resolution |
| --- | ------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------- | ------- | --------------- | ------ | ---------- |
| I01 | Use separate browser profiles/private sessions for the two QA users.                                   | Authentication/session context is separate and the visible identity is correct.                                                                              | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I02 | Request another user's generation detail and reuse URL.                                                | HTTP 404/denial with no other user's prompt, image, or financial data.                                                                                       | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I03 | Poll a foreign ID or a mixed owned/foreign bounded list.                                               | The request is denied as a whole with no partial cross-user result.                                                                                          | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I04 | Request Studio/history/detail/quote/status while signed out.                                           | Authentication is required; private data and quote access are not exposed.                                                                                   | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I05 | Send a harmless quote POST without a valid CSRF context using an agreed local method.                  | Normal web CSRF checks reject it; webhook-only exemption does not bypass user-route protection. Check actual Laravel request-forgery behavior.               | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I06 | Send unknown fields, malformed IDs, invalid enums, excessive references, and more than 12 polling IDs. | Bounded safe validation failures occur; no client-supplied financial/provider state is accepted.                                                             | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I07 | Review webhook HMAC/raw-body/timestamp tests from Group D.                                             | Invalid authentication cannot mutate events, generations, or settlements; signed headers agree with the canonical body.                                      | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I08 | Inspect browser props/assets/network and sanitized application/provider diagnostics.                   | No API key, webhook secret, Authorization header, full sensitive diagnostics, or another user's financial data is exposed.                                   | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I09 | Enter harmless HTML-like prompt text, without generating unless separately approved.                   | Prompt content is rendered as text rather than executable HTML; no v-html-based injection.                                                                   | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I10 | Use a controlled HTTP(S) reference URL while quoting and inspect the destination if available.         | The application validates URL syntax without fetching arbitrary references or implementing uploads; unsafe schemes remain rejected.                          | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I11 | Inspect temporary image links and cross-user financial details.                                        | Only the owner receives relevant result/cost data; expired URLs are handled as temporary, not permanent storage.                                             | Awaiting human execution. | NOT RUN | -               | -      | -          |
| I12 | Exercise existing password-reset, email-verification, 2FA, and passkey screens as separately agreed.   | Starter security flows remain intact; hardware/mailer-dependent steps are recorded honestly, and existing user credentials are not reset without permission. | Awaiting human execution. | NOT RUN | -               | -      | -          |

### Group J — Final acceptance

| ID  | Action                                                                             | Expected result                                                                                                                                                             | Actual result             | Status  | Evidence / logs | Defect | Resolution |
| --- | ---------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- | ------- | --------------- | ------ | ---------- |
| J01 | Repeat one explicitly approved complete happy path after earlier groups and fixes. | One request progresses from quote to accepted generation, signed delivery/recovery, visible image, and one exact settlement.                                                | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J02 | Review every defect and retest each approved targeted fix.                         | Root cause, approved minimal change, relevant regression coverage, and human observed retest are recorded; failures are not hidden by weakened safeguards.                  | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J03 | Run composer test and composer lint:check after confirming isolation.              | Pint, PHPStan/Larastan, and Pest pass on safe isolated tests; current counts/results are recorded rather than copied from history.                                          | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J04 | Run npm.cmd run types:check, npm.cmd run check, and npm.cmd run build.             | Strict TypeScript, frontend checks, and build pass; no quality setting is weakened.                                                                                         | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J05 | Review routes/schedule/queue settings and Git diff in the U: folder.               | All intended checks/configuration are understood; no unrelated feature changes, secret leaks, or Desktop-copy changes occur.                                                | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J06 | Produce the manual verification summary with redacted evidence.                    | Every scenario has its actual status; skipped/blocked cases and live-provider costs/limits are explicit.                                                                    | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J07 | Review public deployment and operations acceptance requirements.                   | Deployment remains an explicitly unverified requirement and blocker; no deployment, cloud provisioning, wallets, payments, or admin features are performed in this session. | Awaiting human execution. | NOT RUN | -               | -      | -          |
| J08 | Review production concurrency, Windows limits, and remaining operational risks.    | Local/manual evidence is distinguished from production row-lock, worker supervision, and public deployment validation; no development DB is used destructively.             | Awaiting human execution. | NOT RUN | -               | -      | -          |

## Group A: first browser batch

Use the existing Laravel and Vite processes. Do not launch another `composer dev`,
HTTP server, Vite instance, worker, or scheduler for this batch. Do not expose a
tunnel or click Generate. A restored valid draft may request a quote; that is
distinct from POST /generations and must not create a generation.

### Terminal QA: location and version

Open a new PowerShell terminal and run:

```powershell
Set-Location -LiteralPath 'U:/Projects/Laravel Projects/pixelforge'
Get-Location
php artisan --version
```

Expect the U: path and Laravel Framework 13.43.0. The guide already verified
server ownership. These commands do not launch servers or migrate data.

### Optional terminal Logs: new Laravel log entries only

In a second PowerShell terminal:

```powershell
Set-Location -LiteralPath 'U:/Projects/Laravel Projects/pixelforge'
Get-Content -LiteralPath './storage/logs/laravel.log' -Tail 0 -Wait
```

This is a human-started log viewer, not a queue worker. Stop it with Ctrl+C.
Inspect new entries locally; share only sanitized error messages and correlation
information. No new Laravel exception is expected from the happy path. Browser
JavaScript/HMR errors may appear only in DevTools.

### A04-A05: guest boundary and existing-account login

1. Open a private/incognito browser window. Open DevTools before navigation.
2. In Network, enable Preserve log. In Console, enable Preserve log.
3. Open [Studio](http://localhost:8002/studio).
4. Expect redirect to [Log in](http://localhost:8002/login).
5. Enter the existing verified account's email and password privately; leave
   Remember me unchecked for this initial test; click **Log in** once.
6. Expect POST /login, a normal redirect (302/303), and a successful Studio page.
   The intended Studio URL or the dashboard URL may both display Studio.
7. Expect the heading **Create with intention.**, navigation for **Studio** and
   **My Generations**, prompt/settings controls, and a pricing summary area.
   Do not enter a generation prompt or click Generate in this batch.

PASS: guest access is denied, correct credentials authenticate, and Studio
renders without a blank screen or unexpected error. FAIL: unauthenticated private
data, login loop, unexpected 4xx/5xx, or a rendering failure. Report unexpected
results before changing configuration or trying repeated credentials.

### A06-A07: navigation and session persistence

1. Click **My Generations**. Expect **A space for your next idea** and no cards.
2. Click **Studio**, then refresh the page normally.
3. Open Studio in another tab in the same private window; remain signed in.
4. Inspect Network for failed required JS/CSS/HMR requests and Console for errors.
   The app's hot-server assets currently use port 5175, not 5173.

PASS: both pages load and refresh/new-tab access preserves login.
Expected DB effects: session/cache activity may occur; user count stays one;
generations, settlements, and submission jobs remain zero. Do not assume an exact
sessions row-count delta because Laravel may rotate session identities.

Stop here and report A04-A07 plus any new Console/Network/Laravel errors.
Registration, logout, mobile checks, and the rest of Group A follow after these
observations; Group B remains unstarted.

## Group B: first browser batch

Sign in to Studio with either verified QA account. Keep DevTools Network open;
do not click Generate image or press Enter to submit the composer. No new server,
worker, scheduler, migration, or provider generation is needed.

- B01: clear the prompt, then enter spaces only. Expect count zero, disabled
  Generate, and no creation POST.
- B02: enter two spaces, hello, then two spaces. Expect a normalized count of 5
  and prompt hello in any POST /generations/quote payload. A quote request is
  allowed; POST /generations is not part of this batch.
- B05: select Square, Landscape, and Portrait; expect the selected state and
  1024x1024, 1536x1024, and 1024x1536 values respectively. Test arrow-key radio
  navigation as well as clicks, without submitting the form.
- B06: select Low, Medium, and High and confirm each selected state. Prices may
  refresh, but exact pricing verification remains in Group C.

A valid prompt may enable Generate only after a valid quote. If pricing is
unavailable, record its message/status rather than treating a disabled Generate
as proof of a composer validation bug. Draft/session/pricing-cache activity is
normal; no generation, settlement, or submission job should be created.
Report B01/B02/B05/B06 before the next Group B batch.

## Group B: Unicode and reference inputs

Continue on Studio without clicking Generate. In the existing QA PowerShell
terminal, prepare exact-length clipboard text with Set-Clipboard, then paste it
into the prompt manually. Run each case separately; no form submission is needed.

- B03: use Set-Clipboard -Value ('a' * 3999), then repeat with 4000 and 4001.
  Counts must match; 4001 must show the length error and disable Generate.
- B04: use Set-Clipboard -Value ([char]::ConvertFromUtf32(0x1F600) * 3999),
  then repeat with 4000 and 4001. The single-code-point emoji must count once,
  with the same accepted/over-limit boundary. Server-boundary rejection is
  tested separately in B09; client blocking alone does not prove it.
- B07: restore a short valid prompt. Add zero, one, and four HTTP(S) references
  such as https://example.com/reference-1.jpg through reference-4.jpg. At four,
  Add reference URL must be disabled; removing a middle item preserves the rest.
- B08: try not-a-url, ftp:, file:, javascript:, and data: examples in a reference
  input only. Expect the inline HTTP(S) URL message and disabled Generate.
  Replacing the value with valid HTTP(S), or removing it, clears that problem.

Example reference URLs need not contain actual images; neither this application
nor the documented provider fetches them. Only quotes may occur in this batch.
Report B03/B04/B07/B08 and any new error before proceeding within Group B.

## Group B: final browser batch

Group A and B01-B08 are confirmed. B09-B11 remain unconfirmed. Continue in the
browser; do not click Generate or submit a generation.

### B09: server-side field validation

Keep a short valid prompt and exactly one reference field. Enter this malformed
reference value (a backslash before image.jpg):

    https://example.com\image.jpg

The current browser helper accepts the URL parser's interpretation; the installed
Laravel URL validator rejects the original value. This was checked locally without
network requests. Wait for the normal quote request: expect HTTP 422 and an error
key reference_images.0. The UI must show the error next to the reference and
preserve the prompt/settings. No reference URL is fetched.

While that error is still visible, perform B11's aria-invalid check below before
correcting the URL. Then change the backslash to /; the field error should clear
and normal quoting should resume.

### B10: per-user tab-local draft recovery

In the same browser tab, use one verified account as Account A. Set prompt
"Draft A: purple robot", Landscape, High, and one valid reference. Refresh, then
visit My Generations and return to Studio. Expect all draft inputs restored.

Log out in the same tab and sign in as the other verified account (Account B).
Account B may restore its own earlier draft but must not receive this new Draft A.
Enter "Draft B: silver moon" with Square, Low, and no references. Log out and
return as Account A in that same tab. Expect Draft A unchanged. Do not use separate
profiles for this check, since separate storage would not exercise same-tab
per-user isolation. No generation is created.

### B11: composer accessibility and reduced motion

- During B09's server-side reference error, right-click the reference input and
  Inspect. Its aria-invalid attribute should be true while the server error is
  displayed. Report the actual value; do not infer it from the visible error.
- Check the input's accessible name and aria-describedby association with its
  visible validation message. Tab/Shift+Tab should reach prompt/options/reference
  add/remove controls with visible focus; use arrow keys for radio groups and
  Space for non-submit reference buttons. Never activate Generate.
- In Chromium DevTools, open the Rendering panel and emulate
  prefers-reduced-motion: reduce. Trigger a harmless quote refresh by changing
  quality. Loading motion should respect the preference. Restore the emulation
  afterward. If this check cannot be performed, record it as untested.
- Capture only safe status/error details, not cookies, credentials, or signed URLs.

Report B09's HTTP status and field error, B10's draft behavior, B11's actual
aria-invalid value and other accessibility observations. Group C remains deferred.

## Main user features walkthrough

The human requested a practical feature tour instead of completing every detailed
QA group now. This changes execution order, not acceptance evidence.

| User feature                        | What the application provides                                                                                                                         |
| ----------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Studio                              | Prompt composer, three sizes, three qualities, up to four reference URLs, and user-scoped tab drafts.                                                 |
| Price preview                       | Exact server-calculated estimate, expected range, and clear 25% platform markup before creation.                                                      |
| Create an image                     | A saved local request is submitted by an asynchronous worker; live statuses show progress without fabricated percentages.                             |
| My Generations                      | Personal paginated image history with status, original estimate, actual charge when settled, and failure feedback.                                    |
| Generation details / Cost Inspector | Image/result metadata, confirmed token usage, accepted rates, category costs, provider total, markup, final charge, and difference from the estimate. |
| Reuse prompt                        | Prefill original settings for editing; a new generation is created only by an explicit Generate action.                                               |
| Account settings                    | Profile, password/security settings, and light/dark/system appearance.                                                                                |

Images use temporary provider URLs and expire after the documented 24 hours;
the app does not permanently store/rehost them. Uploads, wallets, payment
collection, admin reports, public sharing, and deployment are not implemented.

The next proposed product demonstration is one expressly approved successful
generation, then its history and exact Cost Inspector. Current checked readiness:
API key CONFIGURED, webhook secret MISSING, APP_DEBUG=false, database queue
connection configured, and zero local generations/settlements/jobs. Before a
public tunnel, the human must disable effective APP_DEBUG and configure the
matching provider signing secret/callback. Provider key validity, live generation,
webhook delivery, settlement, and recovery remain unverified.

No provider generation/API operation was triggered by the guide during this
scope change. Deferred pricing/reliability/security cases remain documented;
a successful demonstration will not establish all of them as passed.

## Live connection: public callback address

The human confirmed disabling debug. Resolved APP_DEBUG=false and a real local
anonymous JSON 404 without trace/exception/file fields passed the local safety
preflight. The U: server still listens on port 8002. Public error verification
will be repeated once a tunnel exists.

VS Code is installed; no ngrok/cloudflared/devtunnel CLI was found. Its
[built-in port forwarding](https://code.visualstudio.com/docs/debugtest/port-forwarding)
can provide the temporary callback address without another dependency.

In VS Code, open the U: project. Use Ctrl+Shift+P, Ports: Focus on Ports View,
Forward a Port, and enter 8002. Complete the editor's GitHub authentication if
prompted. Right-click the forwarded port and choose Port Visibility > Public,
then copy its HTTPS Forwarded Address. Forward only the Laravel port and keep
the local Laravel/Vite processes running.

The provider callback will be the public origin plus /webhooks/pixelforge.
PIXELFORGE_BASE_URL remains https://pixelforge.promptedit.dev/v1 and APP_URL stays
http://localhost:8002 for the local browser. The public callback goes in the
same provider API key's webhook configuration. The corresponding signing secret
must be copied into private PIXELFORGE_WEBHOOK_SECRET by the human and effective
configuration refreshed before a signed ping.

Current API key is CONFIGURED but live API authentication is unverified;
signing secret remains MISSING. Ask for the public origin only, never keys,
tokens, or signing secrets. No tunnel process, provider API call, signed ping,
or real generation was started by the guide. Await the human's tunnel result.

## Supplied callback and next connection check

The human supplied https://z5hhlc4f-8002.use.devtunnels.ms/. The intended callback
is https://z5hhlc4f-8002.use.devtunnels.ms/webhooks/pixelforge. Hostname DNS resolves,
but the guide's public GET probes failed without an HTTP response and another
reader could not access the origin. This does not prove a Laravel defect or a
working tunnel. Do not claim external reachability PASS.

Ask the human to open the callback in a private browser without tunnel-provider
authentication. A Laravel 405 is expected for GET because the webhook accepts POST.
An authentication/interstitial/unavailable page is not that result; diagnose it
before proceeding. Once reachable, the human can save this callback on the same
provider API key, copy its signing secret privately into PIXELFORGE_WEBHOOK_SECRET,
refresh local configuration intentionally, and send one dashboard test event.
Expect a signed ping response of 204 and one processed event, without a generation
or settlement. Do not request/record secret values or submit a real image.

## Resumed hands-on usage: first image, history, and cost details

The human explicitly resumed the usage handover instead of stopping. Keep this
focused on the product's main journey. PRICING-01 remains open: one backend quote
succeeded, which does not establish reliable browser quoting or resolve the bug.

Preflight observed zero generations/jobs/settlements and no explicit pixelforge
worker or scheduler. Queue connection database uses retry_after 90; jobs use queue
pixelforge rather than the default queue. The guide started no processes.

The human can run these in separate terminals from the U: folder:

    php artisan queue:work database --queue=pixelforge --timeout=30 --tries=10
    php artisan schedule:work

In Studio, use the previously proposed [succeed] small purple robot prompt,
Square, Low, and no references. Check pricing once. The backend estimate observed
was 0.018000000 USD at 2026-11-20T03:58:06Z; the current UI quote must still be
reviewed, since creation independently reprices and the estimate is not a cap.

If the human accepts that current estimate, deliberately clicking Generate once
constitutes approval for that single image. The guide must not POST a generation.
Expect local HTTP 202, background processing, a success image, one settlement,
and the detailed usage/rate/25%-markup breakdown. Visit My Generations and Details,
then Reuse prompt to inspect prefill without generating a second image.

If pricing remains unavailable, capture only the local quote HTTP status and
sanitized response code/message. Do not bypass the disabled button. If creation
has an uncertain outcome, inspect history before any new creation click. No
automatic resubmission or fabricated successful training result.

## Confirmed pricing-cache defect and focused repair

PRICING-01 was diagnosed from recent production TypeError metadata:
PixelforgeClient::pricing expected PixelforgePricingData but received
__PHP_Incomplete_Class. The database cache has serializable_classes=false, while
the client cached the DTO object itself. A cache miss returned a valid object;
a warm cache read could not restore it, producing HTTP 500 until expiry.

A focused HTTP regression in GenerationQuoteTest uses the database cache backed
by the existing guarded SQLite :memory: test connection, with Http::fake and the
object-deserialization restriction intact. It reproduced a successful initial
quote followed by a failing updated quote. The fix caches only validated provider
arrays, reconstructs the immutable DTO after reads, and versions the pricing key
so legacy object entries can expire without a global cache clear. TTL, rates,
decimal precision, queue configuration, and security restrictions are unchanged.

Verification completed:

- Focused regression: 1 test / 6 assertions passed after reproducing the original
  failure. It covers changed options and repeated warm quotes with one fake
  provider pricing request.
- Real backend quote-only checks using the database cache succeeded consecutively:
  Square/Low 0.018000000 USD; Landscape/High 0.392906250 USD; Portrait/Medium
  0.099781250 USD. Generation/job/settlement counts remained zero.
- composer test --no-interaction passed: Pint, PHPStan (0 errors), and all
  374 Pest tests / 1761 assertions. The full run also exposed a dated image test:
  the missing-image case accidentally became expired as real time advanced.
  A one-line test-only travelTo makes its pre-expiry intent deterministic;
  no image-presentation application behavior changed.
- Modified application/test files: app/Integrations/Pixelforge/PixelforgeClient.php,
  tests/Feature/GenerationQuoteTest.php, and one line in
  tests/Feature/GenerationExperienceTest.php. The manual report is updated here.
- Exact source diffs were reviewed against the separate Desktop copy. That copy
  was read only and still needs these fixes incorporated through the human's
  chosen Git workflow before deployment.
- No frontend source changed. Frontend quality/build gates were not rerun for
  this PHP-only fix. No provider generation was submitted.

C01 is now PASS: the human confirmed pricing works after the browser retest.
PRICING-01 is resolved. The subsequent first real generation, exact settlement,
and hands-on history/Cost Inspector/reuse walkthrough have now passed as recorded
below. Controlled failure has also passed; the detailed deferred cases remain
unverified.

The correct worker command remains queue:work database --queue=pixelforge.
The database argument names the connection; pixelforge is the named queue used
by both app jobs. QUEUE_CONNECTION and PIXELFORGE_QUEUE_CONNECTION stay database.
Pricing is synchronous quote work and is independent of this worker.

## First real generation: confirmed evidence

The human reports: "yes all passed. i see queue job also worked", following the
single [succeed] creation, history/Cost Inspector, and reuse-without-resubmission
instructions. Read-only local inspection confirms one succeeded Square/Low
generation, one submission attempt, zero reconciliation attempts, and exactly
one settlement. Both generation.processing and generation.succeeded webhook
records are processed. No queued or failed jobs remain at inspection time.

- Original estimated customer charge: 0.018000000 USD.
- Exact provider cost: 0.017515000 USD.
- Exact customer charge: 0.021893750 USD = provider cost times 1.25.
- Difference from estimate: +0.003893750 USD.
- Settlement timestamp: 2026-11-20T04:26:24+00:00.

One authenticated GET through the existing Pixelforge client confirmed the
provider's succeeded status and matching local metadata, actual usage, accepted
rates, and calculated totals. It created no generation. No image URL, prompt,
credential, or signed header was printed. The guide executed no generation POST.
This proves the observed happy path, not all replay/recovery/security scenarios.
The raw browser 202 response and independent scheduler startup were not captured.

The human subsequently completed the proposed [fail] example and confirmed all
passed. Read-only PostgreSQL shows one submitted provider-failed generation with
zero settlements and the previous succeeded generation with exactly one
settlement. Zero-charge presentation is confirmed by the human. The guide
submitted neither generation.

## Ordinary prompts and provider testing keywords

Source inspection found no [succeed]/[fail]/[blocked]/[overloaded]/[slow]/[duplicate]
branches in application PHP, frontend, routes, or configuration. CreateGeneration
places the validated prompt directly in the immutable provider payload. The app
reacts to provider statuses, errors, webhooks, and confirmed usage through the
same normal workflow. No tag is required for users to create a generation.

The official provider docs define those keywords as provider-side controls for
deterministic integration tests. Ordinary prompts are supported; without the
keywords, the provider sometimes simulates failures, overload, or duplicates.
Pixelforge itself is an assessment simulator: placeholder images, no real AI
model, and no actual monetary charges. Live-provider evidence in this report
means real HTTP/database/queue/webhook behavior with simulated images and costs.
See https://pixelforge.promptedit.dev/docs, Introduction and Testing sections.
A normal prompt without keywords has not been separately submitted in this
manual session; no further submission was made to answer the question.

## Source inspection map

| Concern                                                | Actual source                                                                                                                                                                                               |
| ------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Auth-protected Studio/history and verified dashboard   | routes/web.php                                                                                                                                                                                              |
| Login/register/verification views and throttles        | app/Providers/FortifyServiceProvider.php                                                                                                                                                                    |
| Guard, redirect destination, enabled auth features     | config/fortify.php                                                                                                                                                                                          |
| Registration validation/persistence                    | app/Actions/Fortify/CreateNewUser.php; app/Concerns/ProfileValidationRules.php; app/Concerns/PasswordValidationRules.php                                                                                    |
| Login/register/verification browser forms              | resources/js/pages/auth/Login.vue; Register.vue; VerifyEmail.vue                                                                                                                                            |
| Account menu and logout                                | resources/js/components/NavUser.vue; UserMenuContent.vue                                                                                                                                                    |
| Navigation and settings                                | resources/js/components/AppSidebar.vue; resources/js/layouts/settings/Layout.vue                                                                                                                            |
| Studio defaults and rendering                          | app/Http/Controllers/StudioController.php; resources/js/pages/studio/Index.vue                                                                                                                              |
| Composer, Unicode, draft, quote, uncertainty           | resources/js/components/generations/GenerationComposer.vue; resources/js/composables/useGenerationComposer.ts; resources/js/lib/generations.ts                                                              |
| Quote/create and shared validation                     | app/Http/Controllers/QuoteGenerationController.php; CreateGenerationController.php; app/Http/Requests/CreateGenerationRequest.php; app/Actions/Generations/QuoteGeneration.php; CreateGeneration.php        |
| Provider submission/recovery                           | app/Integrations/Pixelforge/PixelforgeClient.php; app/Jobs/SubmitGeneration.php; ReconcileGeneration.php; app/Console/Commands/ReconcilePixelforgeGenerations.php; routes/console.php                       |
| Signed webhook processing and settlement               | app/Http/Controllers/PixelforgeWebhookController.php; app/Integrations/Pixelforge/PixelforgeWebhookSignatureVerifier.php; app/Actions/Generations/ProcessPixelforgeWebhook.php; ApplyGenerationSnapshot.php |
| Exact calculations and authoritative financial record  | app/Domain/Billing/GenerationPricing.php; Money.php; app/Models/GenerationSettlement.php                                                                                                                    |
| Authorized history/detail/status and cost presentation | app/Http/Controllers/GenerationIndexController.php; GenerationShowController.php; GenerationStatusesController.php; app/Http/Resources/GenerationResource.php; app/Policies/GenerationPolicy.php            |
| Browser history/detail and polling                     | resources/js/pages/generations/Index.vue; Show.vue; resources/js/composables/useGenerationPolling.ts                                                                                                        |

Authentication note: /dashboard has verified middleware; Studio/generation routes
have auth middleware without verified. New registration may land on verification
while direct Studio access follows the current authenticated-only policy. Record
observed behavior; do not silently change this policy during diagnosis.

## Evidence and defect ledger

| Entry    | Evidence / interpretation                                                                                                                                                                                                                                                                                               |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PRE-01   | Process ownership and local static-source GET support A01-A02 only; browser rendering has not been observed.                                                                                                                                                                                                            |
| PRE-02   | Read-only PostgreSQL identity/counts support A03; no rows were inserted or removed by the guide.                                                                                                                                                                                                                        |
| ENV-01   | Actual Vite hot port is 5175. The additional 5173 listener is unassigned. Existing processes remain unchanged.                                                                                                                                                                                                          |
| ENV-02   | Effective debug-off preflight passed. Human subsequently configured the webhook connection and received a real signed ping HTTP 204. A subsequent real image and exact settlement were verified; see the first-generation evidence below.                                                                               |
| ENV-03   | Initial info-level logging filtered verification mail and an old logged link had expired. Resolved after the human's debug-level logging/fresh-resend retry; 2 users are now verified. Use the dated daily log.                                                                                                         |
| HUMAN-01 | Human confirms browser pricing and the first-image/history/Cost Inspector/reuse walkthrough all passed, including the queue job. Read-only evidence: 1 succeeded generation, 1 exact settlement, 0 queued jobs, 0 failed jobs; provider GET matches identity, usage, rates, and costs. No generation POST by the guide. |

| Defect ID | Scenario | Observed defect                                            | Root cause                                                                                                                                                       | Proposed/approved fix                                                                                                                                      | Retest evidence                                                                                | State                                                   |
| --------- | -------- | ---------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------- |
| ENV-03    | A12      | Fresh log email is absent; user reports verification 403s. | Effective LOG_LEVEL=info filters LogTransport debug messages. The correctly signed old URL inspected has expired; the user's exact attempted URL was not shared. | Human applied the local logging retry instructions; effective daily log level is now debug. Fresh verification succeeded without application-code changes. | Human: "all passed"; independent CLI: 2 users / 2 verified, zero generations/jobs/settlements. | RESOLVED after manual retest; initial failure retained. |

Registration evidence note: the first Boost count after the second browser report
still showed one verified user. An independent Laravel CLI connection to the same
named local PostgreSQL target then showed two users, one verified, matching the
human's verification-screen report. The cause of the differing observations has
not been established; it is not evidence of a confirmed registration defect.
No user row, database configuration, or verification flag was changed by the guide.

### Current pricing blocker

| Defect ID  | Scenario                           | Observed defect                                                                                                             | Root cause                                                                                                                                                                              | Proposed/approved fix                                                                                                                                                    | Retest evidence                                                                                                                                                       | State                                        |
| ---------- | ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| PRICING-01 | C01 / first live-image preparation | Valid-input quotes frequently show unavailable pricing; user estimates about 90% of attempts fail, with occasional success. | Confirmed: a serialized PixelforgePricingData DTO is read under cache.serializable_classes=false, becoming __PHP_Incomplete_Class and violating the client's return type on cache hits. | Implemented validated scalar-array pricing cache, reconstructing DTOs on read, with a new cache-key version. No deserialization policy relaxation or global cache clear. | Regression reproduced the cache-hit 500 before the fix, then passed; all 374 PHP tests / 1761 assertions pass; repeated backend quotes and human browser retest pass. | RESOLVED; confirmed by human browser retest. |

## Submission handoff and usage-training status

The human reports about one day remaining before submission and initially planned
to stop, but explicitly resumed the main usage handover. Follow the focused
hands-on workflow above instead of expanding the full checklist. Safe pricing
diagnostics are allowed; image creation stays under deliberate human control.
No automated image submission, deployment, or secret change is authorized.

- Manual foundation and the instructed composer checks passed by human report.
  The email-verification logging/expired-link issue was resolved and two users
  were independently confirmed verified. One server-error accessibility detail
  remains unverified in B11; detailed deferred cases retain their existing status.
- A real provider-signed ping succeeded: event
  evt_01M7VGWGDCFXVVM458BBV0R93B, key label local, attempt 1, HTTP 204 in 2301 ms.
  This proves signed callback delivery by dashboard evidence, not real image
  generation or outbound bearer-key authentication. Independent local event-row
  confirmation was interrupted and is not claimed.
- PRICING-01 is diagnosed and repaired locally: database cache prohibited restoring
  the cached pricing DTO. It now caches validated arrays. Serialized-cache
  regression, repeated backend quotes, and the human's browser retest all pass.
  The original 90% failure frequency was the human's estimate.
- Two human-controlled live-provider cases are verified: one succeeded with
  exactly one settlement, and one failed with no settlement and zero charge.
  Successful provider cost is 0.017515000 USD and customer charge is 0.021893750
  USD; an authenticated read-only generation GET agrees. The failed case left
  the successful settlement intact. Account-wide usage totals remain unverified.
- Core happy-path usage training is COMPLETE by human confirmation: accounts,
  Studio options/references/drafts, quoting, creation, queue execution, live
  image, owner history, Cost Inspector, and prompt reuse. The controlled failure
  and zero-charge example also passed. Rejection and detailed deferred QA cases
  are not yet complete.
- Fresh PHP quality results after the cache fix: composer test passed Pint,
  PHPStan (0 errors), and 374 Pest tests / 1761 assertions. Tests use guarded
  SQLite memory and fake provider responses. Frontend gates were not rerun in
  this backend-fix session; real image generation was verified separately.
- Deployment has not started. The temporary VS Code public tunnel is local-test
  infrastructure, not the assessment's public deployment. The active folder is
  U:/Projects/Laravel Projects/pixelforge; the separate committed GitHub Desktop
  copy was not modified. Confirm the final deploy source includes approved fixes.

Next: the focused success/failure usage handover is complete. Normal prompts
without testing keywords are supported by the source but have not yet been
separately demonstrated live in this session. Final quality gates, source
synchronization through the chosen Git workflow, and separately authorized
deployment remain outstanding. No further generation is authorized automatically.
Other deferred tests remain unverified, rather than silently marked passed.

## Deferred-case honesty

- Group G usage comparisons require a private approved read-only provider request,
  a baseline, identical API-key scope, and UTC completion-period accounting.
  Existing external usage may prevent a simple equality with all local records.
- Pagination needs more than 12 owned records; natural expiry needs a real expired
  result; rate-change behavior needs legitimate changed rates or a separately
  approved disposable test environment. Do not manufacture live billing facts.
- Some race/rollback cases cannot be forced safely by browser timing. Record
  BLOCKED/NOT RUN and retain automated evidence as separate evidence, never as
  a claimed manual or PostgreSQL concurrency PASS.
- Read-only catalog checks plus real replay behavior inspect settlement uniqueness
  without attempting fake financial INSERT/UPDATE/DELETE statements.
- Windows lacks PCNTL hard worker timeouts. Explicit provider HTTP timeouts remain
  configured; local success does not establish production worker supervision.
- Public deployment is a mandatory final assessment requirement but remains
  unverified and outside this session's execution authorization.

## Final report template (Group J only)

Record completed groups and scenario counts, actual approved provider submissions,
exact observed financial totals, confirmed defects/fixes/retests, quality commands
and their current results, blocked/not-run scenarios, remaining live-provider and
production concurrency checks, and deployment blockers. Do not produce a final
application acceptance claim while material requirements remain unverified.
