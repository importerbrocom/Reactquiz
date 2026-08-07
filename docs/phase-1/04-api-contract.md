# 04 — API Contract (`/api/v1`)

## 1. Response envelope

Every response — success or failure — uses the same shape, produced by `App\Support\ApiResponse`.

```json
{
  "success": true,
  "message": "Quiz answer submitted successfully.",
  "data": {},
  "errors": null,
  "meta": null
}
```

- `data` — object or array. `null` on error.
- `errors` — `null` on success; on 422 it is Laravel's field-keyed bag: `{"selected_option": ["The selected option is invalid."]}`; on other errors `{"code": "QUIZ_DAY_LOCKED", "detail": "..."}`.
- `meta` — only present when needed: pagination, cursors, rate-limit hints, `server_time`.
- `message` — always safe for direct display to a user. Internal detail goes to logs/Sentry, never here.

Paginated:

```json
{
  "success": true,
  "message": "Questions retrieved.",
  "data": [ ... ],
  "errors": null,
  "meta": {
    "pagination": { "current_page": 3, "per_page": 25, "total": 1840, "last_page": 74 }
  }
}
```

Cursor-paginated (large tables, infinite lists):

```json
"meta": { "cursor": { "next": "eyJpZCI6MTIzfQ", "prev": null, "per_page": 50 } }
```

### 1.1 Error codes and HTTP status mapping

| HTTP | `errors.code` | When |
|---|---|---|
| 400 | `MALFORMED_REQUEST` | Unparseable body / missing idempotency key on a required endpoint |
| 401 | `UNAUTHENTICATED` | Missing/invalid access token |
| 401 | `TOKEN_EXPIRED` | Access token expired → client silently calls `/auth/refresh` |
| 401 | `SESSION_REVOKED` | Refresh family revoked (logout-all, theft detection) → hard logout |
| 403 | `FORBIDDEN_ROLE` | Student hitting an admin route or vice versa |
| 403 | `EMAIL_NOT_VERIFIED` | Verified-only route |
| 403 | `ENROLMENT_INACTIVE` | No active enrolment for the requested course |
| 404 | `NOT_FOUND` | Resource does not exist **or** is not visible to this user (no existence leak) |
| 409 | `DUPLICATE_SUBMISSION` | Idempotency conflict with a *different* payload under the same key |
| 409 | `ATTEMPT_ALREADY_COMPLETED` | Submitting into a completed attempt |
| 410 | `EXPORT_EXPIRED` | Report download after `expires_at` |
| **423** | `QUIZ_DAY_LOCKED` | Sequential-unlock violation (the canonical locked response) |
| **423** | `FINAL_TEST_NOT_ELIGIBLE` | Fewer than 30 days at 10/10 |
| 422 | `VALIDATION_FAILED` | Form Request failure |
| 422 | `PDF_DUPLICATE` | Checksum already imported for this course |
| 429 | `RATE_LIMITED` | Throttle hit; `meta.retry_after` seconds |
| 500 | `SERVER_ERROR` | Unhandled; message is generic, `meta.reference` carries the Sentry event id |
| 503 | `MAINTENANCE` | Deploy window |

`423 Locked` is used rather than 403 so the frontend can distinguish "you may never do this" from "not yet — here's how
to unlock it", and so locked-day telemetry is easy to alert on.

### 1.2 Standard headers

| Header | Direction | Purpose |
|---|---|---|
| `Authorization: Bearer <access token>` | → | Sanctum PAT, in-memory only |
| `Idempotency-Key: <uuid v4>` | → | **Required** on all quiz/final-test writes |
| `X-Client-Build` | → | Frontend build id, for correlating errors to a release |
| `X-Api-Contract` | ← | Integer contract version; a mismatch with the bundled value triggers a service-worker update (see [06](./06-pwa-and-offline.md)) |
| `X-Idempotent-Replay: true` | ← | This response was replayed from the idempotency store |
| `X-RateLimit-Limit` / `-Remaining` | ← | Throttle visibility |
| `Cache-Control: no-store` | ← | On every auth response and every answer-evaluation response |
| `ETag` / `If-None-Match` | ↔ | Categories, courses, quiz-day question payloads (safe, versioned data) |

### 1.3 Cross-cutting query parameters

`?page=`, `?per_page=` (max 100), `?cursor=`, `?sort=` (whitelisted, `-` prefix = desc), `?filter[field]=`,
`?search=`, `?include=` (whitelisted relations only), `?fields[resource]=` (sparse fieldsets on admin list endpoints).
Implemented via `spatie/laravel-query-builder` with explicit allow-lists — never raw column names from the request.

### 1.4 Rate limits (named limiters)

| Limiter | Applies to | Limit |
|---|---|---|
| `auth-login` | login, forgot-password | 5/min per email+IP, then progressive lockout (see [08](./08-security-architecture.md)) |
| `auth-register` | register | 3/hour per IP |
| `auth-refresh` | token refresh | 30/min per user |
| `quiz-write` | answer submit, retry, complete | 60/min per user (a human answers ≤ ~20/min; this allows offline bursts) |
| `final-test-sync` | batch answer sync | 30/min per attempt |
| `upload` | PDF + image uploads | 10/hour per admin |
| `reports` | export requests | 20/hour per admin |
| `api` | everything else authenticated | 180/min per user |
| `public` | categories/courses browse | 60/min per IP |

---

## 2. Authentication endpoints (`routes/api/v1/auth.php`)

| Method | Path | Auth | Body / notes | Returns |
|---|---|---|---|---|
| POST | `/auth/register` | public | `name, email, password, password_confirmation, timezone?, locale?` | user summary + tokens; fires email verification |
| POST | `/auth/login` | public | `email, password, device_name?, remember?` | `{ user, access_token, expires_in }` + refresh cookie |
| POST | `/auth/refresh` | refresh cookie | — | new access token, **rotated** refresh cookie |
| POST | `/auth/logout` | token | — | revokes current access + refresh pair |
| POST | `/auth/logout-all` | token | `password` (re-auth for safety) | revokes every token + refresh family for the user |
| GET | `/auth/me` | token | — | `{ user, role, permissions[], onboarding_state, active_enrolment_summary }` |
| PATCH | `/auth/me` | token | `name?, phone?, timezone?, locale?, avatar?` | updated user |
| POST | `/auth/forgot-password` | public | `email` | always 200 (no account enumeration) |
| POST | `/auth/reset-password` | public | `token, email, password, password_confirmation` | 200 + all sessions revoked |
| POST | `/auth/email/verify` | public (signed) | `id, hash` from the signed URL | 200 |
| POST | `/auth/email/resend` | token | — | 202, throttled 1/min |
| GET | `/auth/devices` | token | — | active refresh tokens: device label, last used, IP city |
| DELETE | `/auth/devices/{id}` | token | — | revoke one device |
| POST | `/auth/change-password` | token | `current_password, password, password_confirmation` | 200 + optionally revoke other devices |

`/auth/refresh`, `/auth/login`, `/auth/logout*` are the **only** endpoints that accept or set cookies; they live behind
`withCredentials: true` in Axios and a tightly scoped CORS allow-list.

---

## 3. Shared / public endpoints

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/bootstrap` | public | Public settings, app version, VAPID public key, feature flags. Cached 5 min, ETag'd. One request instead of four on cold start. |
| GET | `/exam-categories` | public | Active only, ordered; `stale-while-revalidate` friendly |
| GET | `/exam-categories/{slug}/courses` | public | Active courses + summary config (days, daily count, final test) |
| GET | `/courses/{slug}` | public | Course detail for the onboarding confirmation screen |

---

## 4. Student endpoints (`routes/api/v1/student.php`)

All require `auth:sanctum` + `ability:student` + verified email (configurable) .

### 4.1 Onboarding

| Method | Path | Notes |
|---|---|---|
| GET | `/student/onboarding` | Current step + saved selections (resume support) |
| PUT | `/student/onboarding/category` | `{ exam_category_id }` |
| PUT | `/student/onboarding/course` | `{ course_id }` — validated to belong to the chosen category |
| PUT | `/student/onboarding/preferences` | `{ reminder_time, notifications_opt_in, language, timezone, study_goal }` |
| POST | `/student/onboarding/complete` | Creates the enrolment, generates/attaches daily quizzes, returns dashboard bootstrap. Idempotent. |
| POST | `/student/onboarding/reset` | Allows changing course subject to course rules (blocked once day ≥ 2 completed unless `allow_course_change`) |

### 4.2 Dashboard & course

| Method | Path | Notes |
|---|---|---|
| GET | `/student/dashboard` | **The single most important read.** One response: profile summary, enrolment, current day, completed/remaining, streaks, today's status, final-test eligibility, last 7 scores, counts. Server-cached 60 s per user, invalidated on completion. |
| GET | `/student/courses` | Enrolled courses (summary only) |
| GET | `/student/courses/{course}/days` | 31-row lightweight timeline: `day_number, status(locked/available/in_progress/completed), score, completed_at`. No question data. |
| GET | `/student/courses/{course}/summary` | Course config + progress for the "View Course" screen |

### 4.3 Daily quiz

| Method | Path | Notes |
|---|---|---|
| GET | `/student/courses/{course}/quiz-days/{day}` | Locked → **423** with `meta.unlock_hint`. Unlocked → questions **without answers**, plus attempt state (`current_position`, `mastered_question_ids`, `retry_required_question_ids`) |
| POST | `/student/courses/{course}/quiz-days/{day}/attempts` | Start **or resume** — idempotent; returns the in-progress attempt if one exists |
| GET | `/student/quiz-attempts/{attempt}` | Resume payload (no answers) |
| POST | `/student/quiz-attempts/{attempt}/answers` | `Idempotency-Key` required. `{ question_id, selected_option, client_answer_uuid, time_spent_ms, answered_at, was_offline }`. Returns `is_correct` + (only when wrong, or when the course allows) `correct_option`, `correct_answer_text`, `explanation`, plus `progress` counters. `Cache-Control: no-store` |
| POST | `/student/quiz-attempts/{attempt}/answers/batch` | Offline drain: up to 20 answers, each with its own `client_answer_uuid`; returns per-item results |
| POST | `/student/quiz-attempts/{attempt}/retry/{question}` | Explicitly re-opens a wrong question (clears local feedback, increments retry counter) |
| PATCH | `/student/quiz-attempts/{attempt}/position` | Debounced resume-pointer save (cheap, no body validation beyond an integer) |
| POST | `/student/quiz-attempts/{attempt}/complete` | `Idempotency-Key` required. Server re-derives 10/10 inside a locking transaction. Returns final score, unlocked next day, streak, and any newly unlocked final test. Rejects with 422 `QUIZ_NOT_COMPLETE` listing outstanding question ids |
| GET | `/student/quiz-attempts/{attempt}/result` | Post-completion summary (includes answers + explanations) |
| GET | `/student/quiz-history` | Cursor-paginated completed attempts |

### 4.4 Progress & mistakes

| Method | Path | Notes |
|---|---|---|
| GET | `/student/progress/{course}` | Headline numbers only — fast, cached |
| GET | `/student/progress/{course}/calendar` | 31 compact day cells |
| GET | `/student/progress/{course}/charts` | **Separate endpoint** so the dashboard never pays for chart data; lazily fetched with the lazily-loaded chart bundle |
| GET | `/student/progress/{course}/topics` | Weak/strong topic breakdown |
| GET | `/student/mistakes` | Filters: `course, day, topic, difficulty, status=unresolved|mastered`. Cursor-paginated. Includes explanation (already revealed) |
| POST | `/student/practice-sessions` | `{ course_id, scope: 'unresolved'|'day'|'topic', ... }` → creates a practice session of wrong questions |
| GET | `/student/practice-sessions/{session}` | Questions without answers |
| POST | `/student/practice-sessions/{session}/answers` | Same evaluation path; updates mastery but **never** affects daily-quiz completion or unlocking |

### 4.5 Final test

| Method | Path | Notes |
|---|---|---|
| GET | `/student/courses/{course}/final-test/eligibility` | `{ eligible, completed_days, required_days, missing_days[], attempts_used, attempts_allowed }` |
| POST | `/student/courses/{course}/final-test/attempts` | Start or resume. Returns attempt id, server-side `expires_at`, total count, and the **question id order** (not the questions) |
| GET | `/student/final-test-attempts/{attempt}/questions?position=41&limit=20` | **Windowed** question fetch — never all 300 at once. Answer-free |
| GET | `/student/final-test-attempts/{attempt}/state` | Palette state: answered/flagged/unanswered bitmaps + `current_position` + remaining time |
| POST | `/student/final-test-attempts/{attempt}/answers/batch` | `Idempotency-Key`. ≤ 20 `{question_id, selected_option, is_flagged, time_spent_ms}`. Pure upsert; **no correctness returned** |
| PATCH | `/student/final-test-attempts/{attempt}/pause` / `/resume` | Only if `allow_pause` |
| POST | `/student/final-test-attempts/{attempt}/submit` | `Idempotency-Key`. Finalises, dispatches `GradeFinalTestJob` on `critical`. Returns `202` + `result_url` when grading is async, `200` + result when inline (small tests) |
| GET | `/student/final-test-attempts/{attempt}/result` | Full breakdown: totals, %, correct/incorrect/unanswered, time, topic/category/difficulty performance, wrong-answer review with explanations, pass/fail, certificate status. `404`-style gate until `result_released_at` |
| GET | `/student/certificates/{certificate}/download` | 302 to a short-lived signed storage URL |

### 4.6 Notifications & profile

| Method | Path | Notes |
|---|---|---|
| GET | `/student/notifications` | Cursor-paginated inbox |
| GET | `/student/notifications/unread-count` | Tiny dedicated endpoint (polled) |
| POST | `/student/notifications/{id}/read` / `/read-all` | |
| GET | `/student/notification-settings` | Types + reminder time + opt-in state |
| PUT | `/student/notification-settings` | |
| POST | `/student/push-subscriptions` | `{ endpoint, keys:{p256dh, auth}, device_label, timezone }` — upsert by endpoint hash |
| DELETE | `/student/push-subscriptions` | `{ endpoint }` — on permission revoke / logout |
| POST | `/student/push-subscriptions/test` | Sends a test push to the current device (throttled 3/hour) |
| GET | `/student/profile` / PUT | Profile + avatar (image resized client-side, re-validated server-side) |
| POST | `/student/account/delete-request` | GDPR-style deletion request → queued anonymisation |

---

## 5. Admin endpoints (`routes/api/v1/admin.php`)

All require `auth:sanctum` + `ability:admin` + `role:admin` middleware + a matching Policy check per action.

### 5.1 Dashboard & reports

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/dashboard/summary` | Cards. Reads `report_daily_metrics` + a few counter caches. **Never** aggregates `quiz_attempt_answers` live |
| GET | `/admin/dashboard/charts?from=&to=&course_id=&category_id=` | Time series from the rollup table |
| GET | `/admin/dashboard/activity` | Recent student activity (cursor) |
| GET | `/admin/dashboard/difficult-questions` | Top-N by `difficulty_score` |
| GET | `/admin/dashboard/incorrect-questions` | Top-N by observed wrong rate |
| GET | `/admin/dashboard/pdf-status` | Import pipeline health |
| GET | `/admin/dashboard/push-stats` | Delivery success/failure by day |
| POST | `/admin/reports` | Queue an export: `{ type, format, filters }` → `202 { report_id }` |
| GET | `/admin/reports` / `/admin/reports/{id}` | Status list / single status |
| GET | `/admin/reports/{id}/download` | 302 → signed, expiring URL |
| GET | `/admin/activity-logs` | Filterable audit trail (cursor) |

### 5.2 Catalogue

| Method | Path |
|---|---|
| GET/POST | `/admin/exam-categories` (list w/ search + status filter; create) |
| GET/PUT/DELETE | `/admin/exam-categories/{id}` (soft delete) |
| POST | `/admin/exam-categories/{id}/restore`, `/toggle-status`, `/icon` |
| PUT | `/admin/exam-categories/reorder` — `{ order: [{id, display_order}] }`, single bulk update |
| GET/POST | `/admin/courses` |
| GET/PUT/DELETE | `/admin/courses/{id}` |
| POST | `/admin/courses/{id}/toggle-status`, `/thumbnail`, `/duplicate` |
| GET/PUT | `/admin/courses/{id}/quiz-config` (days, daily count, final-test settings, unlock mode, retry mode) |
| GET/PUT | `/admin/courses/{id}/notification-config` |
| POST | `/admin/courses/{id}/generate-daily-quizzes` | Idempotent; returns per-day fill report |
| GET | `/admin/courses/{id}/readiness` | Pre-publish check: short days, missing explanations, duplicate hashes, final-test count |

### 5.3 Questions

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/questions` | Cursor pagination + fulltext search + filters (course, day, difficulty, status, topic, has_explanation, source). Never `Model::all()` |
| POST | `/admin/questions` | Creates question + options in one transaction |
| GET/PUT/DELETE | `/admin/questions/{id}` | Soft delete |
| POST | `/admin/questions/{id}/restore`, `/duplicate`, `/image` |
| POST | `/admin/questions/bulk` | `{ action: delete|activate|deactivate|assign_day|move_day|set_difficulty|set_topic, ids[]|filter{}, payload{} }` — chunked bulk update, one query per chunk of 500, returns affected count |
| PUT | `/admin/questions/reorder` | Position updates within a day |
| GET | `/admin/questions/duplicates?course_id=` | Hash-collision report |
| POST | `/admin/questions/import` | CSV/XLSX import (queued, same review flow as PDF) |
| POST | `/admin/questions/export` | Queued export |
| GET | `/admin/questions/{id}/preview` | Renders exactly what a student would see |
| GET | `/admin/courses/{id}/days/{day}/questions` | Day composition view |

### 5.4 PDF imports

| Method | Path | Notes |
|---|---|---|
| POST | `/admin/pdf-imports` | multipart `file` (≤ 50 MB), optional `course_id`, `exam_category_id`, `target_quiz_day`, `parser_profile`. Validates MIME + magic bytes, stores privately, dedupes by checksum, dispatches to the `pdf` queue → `202` |
| GET | `/admin/pdf-imports` | List with status filter |
| GET | `/admin/pdf-imports/{id}` | Header + counters |
| GET | `/admin/pdf-imports/{id}/progress` | Tiny polling endpoint (`status`, `progress_percent`, `pages_processed`, counters) |
| GET | `/admin/pdf-imports/{id}/stream` | SSE alternative to polling (same payload, pushed) |
| GET | `/admin/pdf-imports/{id}/items` | Paginated preview, sortable by `confidence` so the worst come first |
| PUT | `/admin/pdf-imports/{id}/items/{itemId}` | Admin correction of a parsed item |
| POST | `/admin/pdf-imports/{id}/items/{itemId}/reject` | |
| POST | `/admin/pdf-imports/{id}/assign` | Bulk set course / category / quiz day on selected items |
| POST | `/admin/pdf-imports/{id}/approve` | `{ item_ids?: [], assign_strategy }` → bulk insert into `questions` (+ options) in chunks, inside per-chunk transactions → `202` with a job batch id |
| POST | `/admin/pdf-imports/{id}/retry` | Re-queue a failed extraction |
| DELETE | `/admin/pdf-imports/{id}` | Soft delete + queued file cleanup |
| GET | `/admin/pdf-imports/{id}/download` | Signed URL to the original file (audit) |

### 5.5 Students, enrolments, quizzes

| Method | Path | Notes |
|---|---|---|
| GET | `/admin/students` | Cursor + search + filters (course, status, activity window, progress band) |
| GET | `/admin/students/{id}` | Profile + enrolments + progress summary |
| GET | `/admin/students/{id}/progress` | Day-by-day detail |
| GET | `/admin/students/{id}/attempts` | Attempt history (cursor) |
| POST | `/admin/students/{id}/suspend` / `/activate` / `/reset-password-link` | |
| POST | `/admin/students/{id}/revoke-sessions` | Kill all devices |
| GET/POST | `/admin/enrolments` | List / manual enrol |
| PATCH | `/admin/enrolments/{id}` | Change status, pause, transfer course |
| POST | `/admin/enrolments/{id}/reset-day/{day}` | Support action: reopen a day (audited, requires reason) |
| GET | `/admin/courses/{id}/daily-quizzes` | Structure overview |
| PUT | `/admin/daily-quizzes/{id}` | Title, status |
| PUT | `/admin/daily-quizzes/{id}/questions` | Set membership + order for a day |
| GET/PUT | `/admin/courses/{id}/final-test` | Final-test settings + materialisation status |
| POST | `/admin/courses/{id}/final-test/materialise` | (Re)build the 300-question snapshot → new version |
| GET | `/admin/final-test-attempts` | Monitor attempts, force-submit a stuck attempt (audited) |

### 5.6 Notifications

| Method | Path |
|---|---|
| GET/POST | `/admin/notification-campaigns` (create draft / schedule) |
| POST | `/admin/notification-campaigns/{id}/send` → `202`, batched fan-out |
| POST | `/admin/notification-campaigns/{id}/cancel` |
| GET | `/admin/notification-campaigns/{id}/stats` (target/sent/failed, per-batch progress) |
| GET | `/admin/push-subscriptions/health` (active, expired, failure clusters by browser) |
| POST | `/admin/push-subscriptions/prune` (queue cleanup of dead endpoints) |

---

## 6. Idempotency contract

Applies to: `POST /answers`, `POST /answers/batch`, `POST /complete`, `POST .../final-test/attempts`,
`POST /final-test-attempts/{id}/answers/batch`, `POST /final-test-attempts/{id}/submit`,
`POST /admin/pdf-imports`, `POST /admin/pdf-imports/{id}/approve`, `POST /admin/reports`.

1. Client generates a UUID v4 per logical operation and **reuses it across retries** (stored in IndexedDB alongside the
   pending answer, so a retry after an app restart uses the same key).
2. Middleware computes `idem:{user_id}:{route}:{key}` in Redis.
   - `SETNX` succeeds → request proceeds; on success the serialised response (status + body) is stored with a 24 h TTL.
   - Key exists with a **stored response** → return it verbatim with `X-Idempotent-Replay: true` (HTTP 200).
   - Key exists but is still **in flight** → `409 DUPLICATE_SUBMISSION` with `Retry-After: 1` (the client waits and
     re-reads state rather than resubmitting).
   - Key exists with a **different request fingerprint** (sha256 of the body) → `409` — a genuine client bug, logged.
3. Redis is a fast path, not the guarantee. The durable guarantee is the DB unique constraint
   (`quiz_attempt_answers(quiz_attempt_id, client_answer_uuid)`), which holds even if Redis is flushed: the insert
   fails, the service catches the duplicate-key exception and returns the existing row's evaluation.

## 7. Request cancellation & timeouts

- All GETs are cancellable: Axios passes an `AbortSignal` from TanStack Query, so navigating away or typing in a search
  box aborts in-flight requests instead of racing.
- Client timeouts: 10 s for reads, 20 s for writes, 60 s for uploads. On timeout, writes go to the outbox rather than
  surfacing an error.
- Server: PHP-FPM `request_terminate_timeout` 30 s for the API pool; uploads have their own location block with a
  longer timeout. Any operation that can exceed ~2 s is a queued job by definition.

## 8. Contract versioning

- URL major version (`/api/v1`) changes only for breaking changes.
- `X-Api-Contract` (an integer, e.g. `7`) increments on **additive but client-relevant** changes. The frontend bundles
  the contract number it was built against; the interceptor compares and, on mismatch, asks the service worker to
  update and prompts a reload. This is the mechanism that stops a cached old bundle from talking to a newer API.
- OpenAPI 3.1 spec generated in Phase 3 (`api/storage/api-docs/openapi.json`) and used to generate the frontend's
  `types/models.ts`, so the TS types cannot drift from the Resources.
