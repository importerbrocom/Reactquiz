# 08 — Security Architecture

## 1. Threat model (what we are actually defending against)

Ranked by likelihood × impact for this specific product:

| # | Threat | Attacker | Impact | Primary control |
|---|---|---|---|---|
| T1 | **Answer extraction** — scraping correct answers from API responses before answering | Motivated student, script | Destroys the product's value; questions are the main asset | Answers never serialised into question payloads; separate whitelist Resource; leakage test in CI |
| T2 | **Unlock bypass** — accessing day 7 or the final test without earning it | Student with devtools | Breaks the core learning model | Server-side `QuizUnlockService` at 4 layers; 423 responses; no client-trusted state |
| T3 | **Score manipulation** — posting `is_correct: true`, or `score: 10`, or completing without answering | Student | Fake certificates, worthless results | Server evaluates and re-derives everything; client-supplied correctness/score fields are not in any validated request schema |
| T4 | **Duplicate/replay abuse** — replaying submissions to inflate progress or corrupt counters | Buggy client or attacker | Data corruption | Idempotency keys + DB unique constraints + append-only log |
| T5 | **Credential attacks** — password spraying, credential stuffing | Bots | Account takeover | Throttling, progressive lockout, breach-password check, no enumeration |
| T6 | **Malicious upload** — PHP/polyglot file disguised as PDF, zip bomb, XXE-style payload | Compromised admin account | RCE / worker DoS | MIME + magic bytes + size caps + private storage outside webroot + no execution path |
| T7 | **Stored XSS via question content** | Admin or PDF content | Session theft across all students | Sanitisation on write, escaping on render, strict CSP, no `dangerouslySetInnerHTML` without purification |
| T8 | **Privilege escalation** — student calling admin endpoints | Any authenticated user | Full content/PII exposure | Token abilities + role middleware + per-action policies; DB-backed role, never a JWT claim |
| T9 | **IDOR** — reading another student's attempt/result | Authenticated student | PII / answer leak | Ownership checks in every policy; UUIDs in URLs; 404 (not 403) for non-owned resources |
| T10 | **Signed-URL/PDF leakage** | Link sharing | Content theft | Short-lived signed URLs, private buckets, no directory listing, download audit |
| T11 | **PII exposure in exports/logs** | Misconfiguration | Compliance breach | Signed expiring exports, log scrubbing, no PII in Sentry, retention limits |
| T12 | **Stale SW serving broken code** | Deploy | Mass outage-like experience | Contract-version handshake (see [06](./06-pwa-and-offline.md#5-preventing-a-stale-service-worker-from-breaking-the-app)) |

---

## 2. Answer confidentiality (T1) — the control that matters most

Layered, because a single mistake here devalues the whole product:

1. **Separate Resources.** `QuestionForStudentResource` returns exactly
   `{ id, question_text, question_image_url, options: [{key, text, image_url}] }`. It is a positive whitelist — adding a
   column to `questions` cannot leak it. `QuestionAdminResource` is the only Resource that includes answer fields, and it
   is only reachable from `/admin/**`.
2. **Model-level hiding.** `Question::$hidden = ['correct_option', 'correct_answer_text', 'explanation']` so an
   accidental `->toArray()`/`response()->json($question)` cannot leak either. The admin resource reads via
   `makeVisible()` explicitly.
3. **Route-level assertion test.** `tests/Feature/Security/AnswerLeakageTest.php` walks **every** student GET route with
   a seeded course and asserts the raw response body contains none of: the correct option letter field name, the correct
   answer text string, the explanation string. New endpoints inherit the test via a route-provider dataset, so a new
   leaky endpoint fails CI.
4. **No answers in caches.** Redis quiz payloads are built from the student Resource; the SW never caches submission
   responses (`no-store` respected); IndexedDB stores no answer keys pre-submission.
5. **Reveal only on submission**, and only for the submitted question — never a bulk "here are all answers" response,
   even after completion (the result endpoint returns per-question review for questions the student actually answered).
6. **Anti-enumeration**: answers are not derivable from response shape or timing — the response payload is padded to a
   consistent structure and evaluation always performs the same work (no early return that could be timed).

Residual risk: a determined student can brute-force a 4-option question by retrying (which the product intentionally
allows) and could script that. Mitigations: `quiz-write` rate limit, `retry_count` and `wrong_submissions` recorded per
attempt, and an anomaly rule (`> 25 submissions on one day's quiz` or `< 800 ms average per submission`) that raises a
`SuspiciousQuizActivityDetected` event → `activity_logs` (severity `security`) → admin report. We flag, we don't block,
because false positives would punish genuine learners.

---

## 3. Authentication & session security

### 3.1 Why token mode and not cookie/SPA mode

Sanctum's stateful cookie mode requires the SPA and API to be same-site, and it depends on CSRF cookies plus session
state. Our frontend is a CDN-hosted PWA on a different subdomain, must work in standalone display mode, and needs to
survive service-worker-mediated requests. Cookie mode there means either same-site coupling we don't want, or
`SameSite=None` cookies, which is a worse CSRF posture than tokens.

**Chosen design:**

| Credential | Storage | Lifetime | Scope |
|---|---|---|---|
| Access token (Sanctum PAT) | **memory only** (a module-scope variable in `auth.store`, not persisted) | 15 min | `Authorization: Bearer` on all API calls |
| Refresh token | `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth` cookie | 30 days (sliding, rotating) | only the auth endpoints |

- XSS cannot read the refresh token (HttpOnly) and can only steal a 15-minute access token — a meaningful reduction
  compared with a long-lived token in `localStorage`.
- CSRF is not applicable to the Bearer-authenticated API (no ambient credentials). The only cookie-bearing endpoints are
  `/auth/refresh` and `/auth/logout`, which are `SameSite=Strict`, `POST`-only, and additionally require an
  `X-Requested-With`-style custom header that a cross-site form cannot set. Laravel's `VerifyCsrfToken` remains active
  for the small web surface (Horizon, password-reset pages).
- Page reload → no access token in memory → the app calls `/auth/refresh` once during boot; the refresh cookie yields a
  new access token. This costs one request on cold start and is why `/bootstrap` and `/auth/refresh` are fired in
  parallel.

### 3.2 Refresh-token rotation and theft detection

Every `/auth/refresh` issues a new refresh token in the same `family_id` and marks the old one `rotated_at`. If a token
that has already been rotated is presented again, that means it leaked: the **entire family is revoked**, all paired
access tokens are deleted, a `security` activity log is written, and the user is emailed. This is standard
refresh-rotation reuse detection and is why `refresh_tokens.family_id` exists.

### 3.3 Login protection

- Passwords: bcrypt cost 12 (or argon2id where the host supports it), `Password::min(10)->letters()->numbers()
  ->uncompromised()` (k-anonymity check against known breaches) on register/reset.
- Throttle `auth-login`: 5 attempts/min per `email+IP`, plus a per-account counter. Progressive lockout: 5 failures →
  1 min, 8 → 5 min, 12 → 30 min (`users.locked_until`). Lockout responses are identical in shape and timing to wrong
  passwords, and always say "Invalid credentials or account temporarily locked" — no enumeration.
- `login_attempts` records every attempt with a **hashed** email so failed logins for non-existent accounts don't create
  a plaintext email list.
- Timing: `Hash::check` is always executed (against a dummy hash when the user doesn't exist) so response time doesn't
  reveal account existence.
- Successful login from a new `device_hash` → email notification and an entry in the devices list.
- Admin accounts: TOTP 2FA scaffolded in Phase 2 and **required** for `role=admin` before production launch; admin
  sessions are 8 h max and admin routes additionally check an IP allow-list when `ADMIN_IP_ALLOWLIST` is set.
- Password reset: single-use tokens, 60 min expiry, invalidated on use, and **all** refresh families revoked on
  successful reset. Forgot-password always returns 200.
- Email verification: signed URLs (`sha1` of email + user id, Laravel's standard), 60 min expiry, resend throttled 1/min.

### 3.4 Session expiry & multi-device

- Access token expiry → transparent single-flight refresh in the Axios interceptor; concurrent 401s queue behind one
  refresh call rather than firing N refreshes.
- Refresh failure → hard logout: purge IndexedDB, clear caches, drop the push subscription, redirect to login with a
  `?returnTo=` so the student comes back to the quiz they were on.
- `/auth/devices` lists active refresh tokens (device label, last used, approximate location); a student can revoke one
  or all. `logout-all` requires the current password.

---

## 4. Authorisation (T8, T9)

Four independent gates, all server-side:

1. **Token abilities.** Student tokens are minted with `['student']`, admin with `['admin']`. Route groups apply
   `abilities:admin`. A stolen student token cannot call an admin route even if the role column were wrong.
2. **Role middleware.** `EnsureRole('admin')` reads `users.role` (DB), not the token, so revoking a role takes effect
   immediately on the next request without waiting for token expiry.
3. **Policies per action.** Every controller action calls `$this->authorize(...)`. Ownership is checked by comparing
   `resource.user_id` with `auth()->id()`, plus enrolment validity, plus unlock state for quiz resources.
4. **Query scoping.** Student queries always start from the authenticated user (`$user->quizAttempts()->…`), never from a
   global model with a user filter appended — so a forgotten `where` cannot expose another student's row.

Non-owned resources return **404**, not 403, so IDs cannot be probed for existence. Route parameters use UUIDs for
attempts, imports and exports.

Admin-only surfaces additionally gated: Horizon (`Gate::define('viewHorizon')` → role admin **and** 2FA-verified),
Pulse, Telescope (local only — `TelescopeServiceProvider` registers nothing when `APP_ENV !== 'local'`).

---

## 5. Input validation & injection defence

- **Every** write endpoint has a Form Request; there are no `$request->all()` mass assignments. Models declare
  `$fillable` explicitly and `Model::preventSilentlyDiscardingAttributes()` is on outside production.
- Sorting/filtering/includes use `spatie/laravel-query-builder` allow-lists; raw column names from the request never
  reach SQL. Any unavoidable `whereRaw` uses bindings and is reviewed in PR (a Larastan rule flags raw SQL).
- Eloquent/PDO parameter binding everywhere → SQL injection covered by construction, not by escaping discipline.
- **Question content sanitisation:** admins may use a small formatting subset (bold, italic, sub/sup, code, line
  breaks, inline math). Content is purified on **write** with an allow-list (HTMLPurifier-style config: no `script`,
  `iframe`, `on*` attributes, no `style`, no `javascript:` URLs) and stored sanitised. On the frontend, question text
  renders through a single `<RichText>` component that purifies again with DOMPurify before `dangerouslySetInnerHTML` —
  defence in depth, because an old unsanitised row could exist.
- Zod validates request payloads client-side (UX) and, in dev/test, validates response shapes (contract drift alarm).
  Client validation is never treated as a security control.

---

## 6. File upload security (T6)

| Control | Detail |
|---|---|
| Extension + MIME | `mimes:pdf`, `mimetypes:application/pdf` |
| Magic bytes | First 5 bytes must be `%PDF-`; verified in a `PdfFile` validation rule, not just trusting the client MIME |
| Size | `max:51200` (50 MB), enforced also at Nginx (`client_max_body_size 55m`) and PHP (`upload_max_filesize`) |
| Page cap | Reject > 1,500 pages at probe time (`pdf.max_pages`) to bound worker time |
| Storage | Private disk (S3 private bucket / `storage/app/private`), **never** `public/`; filenames are generated
  (`{uuid}.pdf`), original name kept only as metadata |
| Execution | Files are never included/executed; the worker only reads bytes. No `shell_exec` with user-controlled
  paths — when `pdftotext` is used, arguments are passed as an array via Symfony Process with an absolute binary path and
  a temp file the app created |
| Content risks | PDF parsing runs in the isolated `pdf` worker pool with a memory cap and 900 s timeout, so a malicious
  or pathological file degrades one worker, not the API |
| Images | Re-encoded server-side (Intervention/Imagick) — re-encoding strips embedded payloads and EXIF; SVG uploads are
  rejected outright (SVG is an XSS vector) |
| Download | Only via short-lived signed URLs (5 min for PDFs, 15 min for reports); every download writes an audit row |
| Antivirus | Optional ClamAV scan hook in `CreatePdfImportAction` (enabled via `PDF_AV_SCAN=true`) for deployments that
  require it |

---

## 7. Transport, headers and CSP

Applied by `SecurityHeaders` middleware (API) and by the CDN/host config (frontend):

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY                      (plus frame-ancestors 'none' in CSP)
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()
Cross-Origin-Opener-Policy: same-origin
Cross-Origin-Resource-Policy: same-site
Cache-Control: no-store            (auth + answer endpoints)
```

Frontend CSP (no `unsafe-inline`, no `unsafe-eval`; Vite production output needs neither):

```
default-src 'self';
script-src 'self';
style-src 'self' 'unsafe-inline';        # Tailwind injects a few inline styles for CSS vars; kept to styles only
img-src 'self' data: blob: https://cdn.example.com;
font-src 'self';
connect-src 'self' https://api.example.com https://*.ingest.sentry.io;
worker-src 'self';
manifest-src 'self';
frame-ancestors 'none';
base-uri 'self';
form-action 'self';
object-src 'none';
upgrade-insecure-requests;
report-uri /csp-report            # collected for a week after each release
```

HTTPS enforced everywhere (`URL::forceScheme('https')` in production, HSTS, HTTP→HTTPS redirect at the edge).
CORS: explicit origin allow-list (`app.example.com` + preview domains), `supports_credentials: true` only for the auth
paths, allowed headers limited to what we actually send.

---

## 8. Quiz-integrity controls (T2, T3, T4) — summary

| Rule | Control |
|---|---|
| Cannot access a future day | `EnsureQuizDayUnlocked` + `QuizAccessPolicy` + in-transaction re-check; 423 |
| Cannot answer a question that isn't in today's quiz | Membership check against `daily_quiz_questions` before evaluation |
| Cannot self-report correctness or score | Those fields exist in no request schema; server derives both |
| Cannot complete without 10/10 | `complete` recounts `quiz_attempt_questions.state='mastered'` under a row lock |
| Cannot double-complete / double-streak | `FOR UPDATE` + status check + idempotency |
| Cannot replay offline answers into extra progress | `client_answer_uuid` unique + idempotency ledger |
| Cannot extend a timed final test | `expires_at` computed and stored server-side at start; client timer is display-only; grading honours the server deadline |
| Cannot see final-test results early | `result_released_at` gate |
| Cannot forge a certificate | Certificates are server-generated from a graded attempt, unique per attempt, with a serial + verification endpoint |
| Admin overrides are traceable | Reopening a day / force-submitting requires a reason and writes `activity_logs` |

---

## 9. Privacy, PII and data retention

- PII stored: name, email, optional phone, avatar, timezone, locale, IP addresses (as `VARBINARY`, for security logs),
  device labels. No payment data, no precise location, no biometrics.
- Logs: a `LogScrubber` processor strips `password`, `password_confirmation`, `token`, `access_token`,
  `refresh_token`, `Authorization`, `Cookie`, `endpoint`, `p256dh`, `auth` from every log record and Sentry event.
  Sentry runs with `send_default_pii = false`; the frontend Sentry init scrubs `localStorage`/breadcrumb URLs containing
  tokens.
- Retention: `activity_logs` 12 months (security severity 24 months), `notifications` 90 days,
  `notification_deliveries` 90 days, `login_attempts` 90 days, `report_exports` files 7 days, expired
  `push_subscriptions` 30 days, `quiz_attempt_answers` kept for the life of the account (it is the audit trail for
  results). All enforced by scheduled prune jobs.
- Account deletion: `POST /student/account/delete-request` → queued job that anonymises `users` (name → "Deleted user",
  email → `deleted+{uuid}@invalid`, phone/avatar null), deletes push subscriptions, notifications and onboarding, and
  **retains** anonymised attempt rows for aggregate integrity. Documented in the privacy policy.
- Data export: a student can request their own data as JSON (queued, signed URL) — the same machinery as admin reports.

---

## 10. Secret management

- No secrets in the repository; `.env.example` lists every key with a safe placeholder (see
  [13-environment-variables.md](./13-environment-variables.md)).
- Production secrets live in the platform's secret store (Forge env, AWS Secrets Manager, Doppler…), injected as env
  vars; `config:cache` is run **after** injection.
- `APP_KEY`, `VAPID_PRIVATE_KEY`, DB and S3 credentials rotated on a documented schedule; rotation runbook in
  `docs/runbooks/secret-rotation.md`.
- `APP_DEBUG=false` and `APP_ENV=production` verified by a deploy-time smoke check that fails the release if
  `/api/v1/__debug-probe` (a route that only exists in non-production) responds, or if a deliberate 500 returns a stack
  trace.
- Frontend: only `VITE_*` values are bundled, and a build-time assertion fails the build if any bundled string matches a
  secret-looking pattern (`sk_`, `-----BEGIN`, 40+ char hex).
- CI: dependency audit (`composer audit`, `npm audit --production`), secret scanning (gitleaks), Larastan + ESLint
  security rules on every PR.

---

## 11. Safe error handling

- Exception rendering produces the standard envelope with a **generic** message for 5xx, plus
  `meta.reference = <sentry event id>` so support can correlate without exposing internals.
- Validation errors are field-specific (safe and necessary); authorisation errors are deliberately vague.
- No stack traces, SQL, file paths, or class names in any API response in production; a feature test asserts this by
  triggering a forced exception with `APP_DEBUG=false`.
- Frontend `ErrorState` components show a friendly message + retry; technical detail goes to Sentry only.

---

## 12. Security testing checklist (executed in Phase 11, automated where possible)

- [ ] `AnswerLeakageTest` passes across every student route (automated, CI)
- [ ] Locked-day access returns 423 on every quiz route incl. batch and attempt-id paths (automated)
- [ ] Student token rejected on all `/admin/**` routes; admin token rejected on student-only mutations (automated)
- [ ] IDOR sweep: another student's attempt/result/certificate/import ids → 404 (automated)
- [ ] Idempotency replay produces exactly one DB row (automated, incl. a 50-way concurrent test)
- [ ] Concurrent completion produces one streak increment and one unlock (automated)
- [ ] Score/correctness fields injected into request bodies are ignored (automated)
- [ ] Upload: `.php` renamed to `.pdf`, PDF with a JS action, 60 MB file, 2,000-page file → all rejected (automated)
- [ ] XSS payload in question text/explanation renders inert in the student UI (automated component test + manual)
- [ ] Rate limits return 429 with `Retry-After` and do not leak account existence (automated)
- [ ] Refresh-token reuse revokes the family (automated)
- [ ] Lockout behaviour and timing parity for unknown vs known emails (automated)
- [ ] CSP has no `unsafe-eval`; no console CSP violations on any route (manual + Lighthouse)
- [ ] Security headers verified on API and frontend responses (automated smoke test post-deploy)
- [ ] Signed URLs expire; expired URL → 403/410 (automated)
- [ ] `APP_DEBUG=false`, Telescope absent, Horizon gated, `/telescope` 404 in production (deploy check)
- [ ] Logs and Sentry contain no PII/tokens after a full user journey (manual review)
- [ ] Dependency audit clean; no known-critical CVEs (CI)
- [ ] Logout clears IndexedDB, caches and push subscription (automated browser test)
- [ ] External review: an independent pentest or at minimum an OWASP ASVS L1 self-assessment before public launch
