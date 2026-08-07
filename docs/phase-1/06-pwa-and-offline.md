# 06 — PWA Caching & Offline Synchronisation

## 18. PWA caching strategy

### 1. Manifest

`web/public/manifest.webmanifest`

```json
{
  "name": "QuizPath — Daily Exam Practice",
  "short_name": "QuizPath",
  "description": "Complete 10 questions a day, master every one, and unlock the next day.",
  "id": "/?source=pwa",
  "start_url": "/?source=pwa",
  "scope": "/",
  "display": "standalone",
  "display_override": ["standalone", "minimal-ui"],
  "orientation": "portrait",
  "background_color": "#0B1120",
  "theme_color": "#4F46E5",
  "lang": "en",
  "dir": "ltr",
  "categories": ["education", "productivity"],
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-256.png", "sizes": "256x256", "type": "image/png" },
    { "src": "/icons/icon-384.png", "sizes": "384x384", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    { "src": "/icons/maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ],
  "shortcuts": [
    { "name": "Today's Quiz", "url": "/quiz/today", "icons": [{ "src": "/icons/shortcut-quiz.png", "sizes": "96x96" }] },
    { "name": "Review Mistakes", "url": "/mistakes" },
    { "name": "Progress", "url": "/progress" }
  ],
  "prefer_related_applications": false
}
```
iOS extras in `index.html`: `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`,
`apple-touch-icon`, and `apple-touch-startup-image` splash entries per device size (iOS ignores the manifest's icons
for splash screens).

### 2. Cache buckets and versioning

```
qp-static-<BUILD_ID>      versioned JS/CSS/fonts (immutable, content-hashed)   Cache First
qp-images-<BUILD_ID>      icons, illustrations, category/course thumbnails     Stale While Revalidate, cap 60 / 30 d
qp-shell-<BUILD_ID>       index.html, offline.html                             Network First (nav), fallback to cache
qp-api-safe-<CONTRACT>    categories, courses, course config, quiz-day questions  SWR / Network First (see table)
qp-api-user-<CONTRACT>    dashboard, day list, progress headline               Network First, max-age 5 min
```
`BUILD_ID` comes from the Vite build; `CONTRACT` is the API contract integer. **Every cache name embeds a version**, and
`activate` deletes any cache whose name is not in the current allow-list — that is the automatic old-cache cleanup.

### 3. Per-route strategy table

Implemented as an explicit, auditable table in `src/workers/sw-strategies.ts`. Anything not matched is
**Network Only** — a deny-by-default policy, so a new sensitive endpoint can never be accidentally cached.

| Request pattern | Strategy | TTL / entries | Rationale |
|---|---|---|---|
| `/assets/*.{js,css,woff2}` (hashed) | **Cache First** | 1 year, purged by build id | Immutable by content hash |
| `/icons/*`, `/splash/*`, local images | **Cache First** | 30 d, 60 entries | Static branding |
| Remote/CDN course & category images | **Stale While Revalidate** | 7 d, 80 entries | Nice-to-have freshness, instant paint |
| Navigation requests (`mode: navigate`) | **Network First** → cached `index.html` → `offline.html` | — | App shell always current when online |
| `GET /api/v1/bootstrap` | **Stale While Revalidate** | 5 min | Public config; instant boot |
| `GET /api/v1/exam-categories`, `/courses*` | **Stale While Revalidate** | 1 h | Safe, non-personal, rarely changes |
| `GET /api/v1/student/courses/*/quiz-days/{day}` | **Network First**, cache the **answer-free** payload | 24 h, only for days already unlocked | Enables offline resume of a started quiz. Contains no answers, so caching is safe |
| `GET /api/v1/student/dashboard`, `/days`, `/progress/*` | **Network First**, short-lived cache | 5 min | Fast repeat opens; always tries the network first |
| `GET /api/v1/student/final-test-attempts/*/questions*` | **Network First**, cache current window only | session only, cleared on submit | Allows refresh recovery without re-download |
| `GET /api/v1/student/mistakes` | **Network First** | 5 min | Explanations here are already revealed to this student |
| `POST /api/v1/student/**/answers*` | **Network Only** + Background Sync queue | — | Must reach the server; never cached |
| `POST .../complete`, `.../submit` | **Network Only**, **no** background sync | — | Completion must be a deliberate, online, foreground action (prevents surprise completions) |
| `/api/v1/auth/**` | **Network Only**, `no-store` | — | Never cache credentials or tokens |
| `/api/v1/admin/**` | **Network Only** | — | Admin data is never cached on a device |
| Private file/signed-URL downloads (PDFs, reports, certificates) | **Network Only** | — | Signed, expiring, and often large |

### 4. Never-cached list (enforced by the deny-by-default policy + a unit test on the strategy table)

- Any response containing `correct_option`, `correct_answer_text` or `explanation` **from a submission endpoint**
  (`Cache-Control: no-store` is also set server-side, and the SW checks for it before writing to any cache).
- Auth responses and tokens.
- All `/admin/**` responses.
- Old quiz evaluation results and final-test results (fetched fresh; a stale score is worse than a spinner).
- Private PDFs, report exports, certificates.

### 5. Preventing a stale service worker from breaking the app

Three mechanisms, because this is the most common way PWAs break in production:

1. **Build-id namespaced caches.** New build → new cache names → old code cannot be served alongside new HTML.
2. **Contract-version handshake.** Every API response carries `X-Api-Contract`. The bundle knows the contract it was
   built for. On mismatch (`server > client`), the app calls `registration.update()`, and once the new SW is
   `installed`, shows a non-dismissable "Update required to continue" prompt for quiz routes (dismissable elsewhere) and
   reloads via `skipWaiting` + `clients.claim()`.
3. **No `skipWaiting` on install by default.** The waiting worker activates only when the user accepts the update
   prompt, or automatically when there is no in-progress quiz attempt (checked in IndexedDB before auto-activating), so
   a reload never destroys unsynced local answers.

Additionally: `navigationPreload` is enabled to avoid the SW adding latency to navigations, and the SW never intercepts
requests it has no rule for (it calls `fetch(event.request)` untouched), so a SW bug degrades to "no offline support"
rather than "app broken".

### 6. Install prompt & update UX

- Capture `beforeinstallprompt`, store the event in `pwa.store`, and surface a subtle "Install app" card on the
  dashboard after the student completes their **first** quiz day (a earned-moment prompt converts far better than a
  first-load nag). Re-prompt at most once per 14 days; never on the quiz screen.
- iOS: detect `standalone === false` + iOS UA and show a one-time "Add to Home Screen" instruction sheet with the share
  icon illustrated.
- Update available → toast with "Reload to update"; forced when the API contract has moved.
- `offline.html` is a self-contained page (inline CSS, no JS) with the app logo, "You're offline", and the note that
  saved answers will sync automatically.

---

## 19. Offline synchronisation strategy

### 1. What is stored offline, and where

| Store | Contents | Lifetime |
|---|---|---|
| **IndexedDB** `qp-db-<userHash>` | see object stores below | until logout / completion / expiry |
| `localStorage` | theme, last course id, list density, install-prompt dismissals, "notification permission denied" flag — all tiny and non-sensitive | until cleared |
| In-memory only | access token, decrypted anything | tab lifetime |

`userHash = sha256(user.uuid).slice(0,16)` — the database name is per-user, so switching accounts on a shared device
cannot expose the previous student's data, and logout deletes the whole database.

**IndexedDB object stores** (schema v1, migrations via `idb`'s `upgrade` callback):

```
pending_answers        key: client_answer_uuid
                       { client_answer_uuid, idempotency_key, attempt_uuid, question_id,
                         selected_option, time_spent_ms, answered_at, was_offline,
                         attempts, next_retry_at, status: 'pending'|'inflight'|'failed' }
                       index: by_attempt, by_status

synced_answers         key: client_answer_uuid   { synced_at, is_correct }   (tombstones, 7-day TTL)

attempt_state          key: attempt_uuid
                       { course_id, day_number, current_position, mastered_question_ids[],
                         retry_required_question_ids[], updated_at }

final_test_state       key: attempt_uuid
                       { question_order_hash, current_position, answers: { [qid]: {option, flagged,
                         time_spent_ms, dirty} }, last_sync_at }

quiz_cache             key: `${course_id}:${day_number}`   answer-free question payload + fetched_at

meta                   key: 'sync'  { last_sync_at, pending_count, schema_version }
```

**Never stored offline:** correct options, correct answer texts, explanations *before* submission; auth tokens; admin
data; other users' data.

Explanations *after* a wrong answer are held in **memory only** for the current question so that a refresh does not
persist answer keys to disk. If the student refreshes, the question returns to "retry required" and the explanation is
re-fetched on the next submission — a deliberate trade of convenience for not writing answer keys to disk.

### 2. Write path (single answer)

```
student taps Submit
 1 reducer marks the option as "submitting" (spinner on that option only, rest of UI stays interactive)
 2 write to pending_answers with a fresh client_answer_uuid + idempotency_key   (atomic, before any network call)
 3 attempt POST /answers
      success → remove from pending_answers, add tombstone to synced_answers,
                apply server result (correct/incorrect + explanation) to the reducer
      network error / offline / timeout →
                keep the row, status stays 'pending', register Background Sync tag 'sync-answers',
                UI shows "Saved offline · will sync" on that question and the question is marked
                'pending' (NOT correct, NOT incorrect — we never guess)
      4xx (validation/authorisation) → mark 'failed', surface a specific error, never retry blindly
```
Because step 2 precedes the request, a crash between steps 2 and 3 loses nothing.

### 3. Drain path (coming back online)

Triggered by: `online` event, `visibilitychange → visible`, service-worker `sync` event, app start, and a 30 s timer
while any pending rows exist.

```
drainOutbox():
  if (!navigator.onLine || draining) return
  draining = true
  rows = pending_answers where status='pending' and next_retry_at <= now, ordered by answered_at, take 20
  group rows by attempt_uuid
  for each group:
      mark rows 'inflight'
      POST /answers/batch { batch_uuid, answers: rows }         # ONE request per 20 answers
      per-item response:
          ok            → delete row, tombstone, apply result
          replayed      → same as ok (X-Idempotent-Replay)
          410/409 stale → delete row, refetch authoritative attempt state (server wins)
          5xx / network → status back to 'pending', attempts++, next_retry_at = now + backoff(attempts)
                          backoff: 2s, 8s, 30s, 2m, 10m (cap), give up after 8 attempts → 'failed'
  refresh TanStack Query caches for the attempt (invalidate, don't patch, once the batch settles)
  draining = false
```

Concurrency guard: a `navigator.locks.request('qp-outbox')` web lock (with a Redis-free in-memory fallback) prevents two
tabs from draining the same rows simultaneously. Combined with server-side idempotency, a double drain is harmless
anyway — the lock just avoids wasted requests.

### 4. Duplicate prevention — the four layers

1. `client_answer_uuid` is generated **once**, when the answer is first recorded locally, and reused on every retry
   forever (it is the IndexedDB primary key).
2. `Idempotency-Key` is stored with the row, so a retry after an app restart replays the same key.
3. Redis idempotency ledger returns the stored response for a replay.
4. `UNIQUE (quiz_attempt_id, client_answer_uuid)` in MySQL is the durable backstop even if Redis is flushed.

Result: a student who answers offline on a plane, force-quits the app, reinstalls the PWA and comes back online records
exactly one answer row.

### 5. Conflict resolution — server always wins

| Conflict | Resolution |
|---|---|
| Local says "pending", server already recorded it | Server result applied; local row deleted (idempotent replay makes this automatic) |
| Local mastered set disagrees with server | Discard local; `attempt_state` is overwritten from the server's resume payload on every successful fetch |
| Attempt was completed on another device | Batch returns `409 ATTEMPT_ALREADY_COMPLETED`; client clears local state and navigates to the day-complete screen |
| Day was reset by an admin | Server returns a new `attempt_uuid`; the client purges the old attempt's local state |
| Final-test answers conflict | Upsert semantics: last write per `(attempt, question)` wins, ordered by `answered_at`; the client then reloads palette state from the server |
| Clock skew (client `answered_at` in the future) | Server clamps to `[started_at - 5m, now + 2m]` and records `recorded_at` as truth |

The client never merges; it **replaces** local derived state with server state after any sync. Local storage is a
buffer, not a replica.

### 6. Completion and the final test offline

- **Day completion requires connectivity.** If the 10th answer syncs while offline, the app shows
  "All 10 mastered — connect to finish Day N" and auto-calls `/complete` the moment the drain succeeds. This keeps
  streaks, unlocks and notifications derived from a single authoritative online transaction. `/complete` is
  deliberately excluded from Background Sync so it never fires while the app is closed and the UI can show the result.
- **Final test offline**: answers are written to `final_test_state` immediately and the batch sync retries with the same
  backoff. Submission requires connectivity; the review screen blocks the submit button when offline, with an explicit
  explanation and a retry indicator. If the timer expires while offline, the server's `expires_at` still governs — the
  attempt is graded from whatever answers reached the server, and the client warns about this before the test starts.

### 7. Cleanup rules

| Trigger | Action |
|---|---|
| Day completed | delete `attempt_state`, `quiz_cache` entry and tombstones for that attempt |
| Final test submitted | delete `final_test_state` and cached question windows |
| Course changed | purge everything except theme/preferences |
| Logout | `indexedDB.deleteDatabase('qp-db-<userHash>')`, clear all `qp-api-*` caches, unregister push subscription, drop the in-memory token. Static asset caches are kept (they are not private) |
| Session revoked (401 `SESSION_REVOKED`) | same as logout, plus a toast explaining why |
| Expiry sweep (on app start) | delete `quiz_cache` older than 24 h, `synced_answers` older than 7 d, `pending_answers` marked `failed` older than 30 d (after surfacing them once) |
| Storage pressure | `navigator.storage.estimate()`; if usage > 80% of quota, drop `quiz_cache` first, never `pending_answers` |

### 8. Network-status handling

`useNetworkStatus()` combines `navigator.onLine`, a lightweight `/api/v1/ping` heartbeat (only while the quiz screen is
open, every 20 s, aborted on unmount) and `navigator.connection.effectiveType`. It feeds:

- a top banner: "Offline — your answers are being saved" / "Back online — syncing (3)…" / silent when healthy;
- an `OfflineSaveIndicator` on the quiz screen showing the pending count;
- degraded-mode decisions: on `2g`/`slow-2g` the app skips prefetching the next question window and defers chart
  bundles.

Both the banner and the indicator use `aria-live="polite"` so screen-reader users are told about state changes without
losing focus.
