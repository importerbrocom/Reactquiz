# 07 — Redis Caching Strategy & Queue Architecture

## 20. Redis caching strategy

### 1. Instance / database layout

| Purpose | Connection | Eviction | Why separate |
|---|---|---|---|
| Cache | `redis.cache` — db 0 (own instance in the scalable tier) | `allkeys-lru`, `maxmemory` set | Cache may be evicted freely |
| Queue | `redis.queue` — db 1 (own instance in the scalable tier) | **`noeviction`** | Eviction here means silently losing jobs |
| Locks / idempotency / rate limits | `redis.default` — db 2 | `volatile-ttl` | Correctness-critical but always TTL'd |
| Horizon metrics | db 3 | `allkeys-lru` | Disposable telemetry |

`REDIS_PREFIX=qp_{env}_` on every connection, so staging and production can share a managed Redis without collisions.

### 2. Key naming scheme

Every key is produced by `App\Support\CacheKeys` — never inline strings. Shape:

```
qp:{env}:{domain}:v{schemaVersion}:{scope...}
```

| Key | TTL | Contents | Invalidated by |
|---|---|---|---|
| `cat:v1:active` | 1 h | active categories, ordered | any category write |
| `cat:v1:{slug}:courses` | 1 h | active courses in a category | course/category write |
| `course:v1:{id}:config` | 6 h | quiz config (days, counts, final-test settings, unlock mode) | course update |
| `course:v1:{id}:days` | 6 h | day numbers + question counts + titles | day/question membership change |
| `quiz:v1:c{course}:d{day}:cv{contentVersion}:questions` | 1 h | **answer-free** question payload for a day | `content_version` bump makes the old key unreachable (no explicit delete needed) |
| `dash:v1:u{user}:c{course}` | 60 s | student dashboard summary | `DailyQuizCompleted`, enrolment change, explicit delete |
| `prog:v1:u{user}:c{course}:headline` | 5 min | progress headline numbers | completion events |
| `mist:v1:u{user}:c{course}:count` | 5 min | unresolved mistake count | answer submission (delete only) |
| `notif:v1:u{user}:unread` | 60 s | unread count | notification create / read |
| `admin:v1:dash:{from}:{to}:{course}:{cat}` | 5 min | dashboard payload built from `report_daily_metrics` | rollup job |
| `admin:v1:counts` | 10 min | headline counters | content writes (delete) |
| `final:v1:t{finalTest}:order` | 24 h | canonical question id order for a test version | new test version |
| `ftq:v1:t{test}:w{windowStart}` | 1 h | a 20-question answer-free window | new test version |
| `push:v1:timezones` | 1 h | distinct timezones with active subscribers | subscription create/expire |
| `settings:v1:public` | 30 min | public settings for `/bootstrap` | settings write |
| `lock:quiz:complete:{attempt}` | 10 s | mutex belt-and-braces around completion (the DB row lock is the real guarantee) | auto-expire |
| `idem:{user}:{route}:{key}` | 24 h | stored idempotent response | auto-expire |
| `notifdedupe:{type}:{user}:{date}` | 24 h | push dedupe guard | auto-expire |
| `throttle:*` | window | Laravel rate limiter | auto-expire |

### 3. What is cached vs never cached

**Cached (safe):** active categories/courses, course configuration, quiz-day question payloads *without answers*,
public settings, aggregated report data, per-user dashboard/progress summaries, unread counts, timezone lists.

**Never cached:**
- correct options, correct answer texts, explanations, or any answer-evaluation response;
- authentication responses, tokens, password-reset state;
- anything cross-user in a user-scoped key (see below);
- final-test results before `result_released_at`;
- admin PII lists (student exports are generated fresh and delivered via signed URLs).

### 4. Scoping rules (preventing cross-user leakage)

1. Any cached value derived from `auth()->id()` **must** include `u{user_id}` in the key. A `CacheKeys` unit test asserts
   that every method whose name starts with `student`/`user` produces a key containing `u{id}`.
2. User-scoped keys never appear in a `Cache::tags` group shared with global data.
3. HTTP-level caching of authenticated responses is disabled globally (`Cache-Control: private, no-store` on all
   `/student/**` and `/admin/**` responses) so no CDN or proxy can serve one student's data to another. Only
   `/bootstrap`, `/exam-categories` and `/courses*` are `public, max-age`.
4. Question payload keys include `cv{content_version}`, so editing content can never serve stale questions — the key
   simply changes.

### 5. Invalidation matrix

Owned by `CacheInvalidationService`, called from listeners (not scattered through controllers).

| Event | Invalidates |
|---|---|
| Category created/updated/deleted/reordered | `cat:*`, `admin:v1:counts` |
| Course created/updated/status change | `cat:v1:{slug}:courses`, `course:v1:{id}:*`, `admin:v1:counts` |
| Question created/updated/deleted/bulk op | bump `courses.content_version` (→ quiz keys become unreachable), delete `course:v1:{id}:days`, `admin:v1:counts`, mark the final test for re-materialisation |
| Day membership changed | bump `content_version`, delete `course:v1:{id}:days` |
| `DailyQuizCompleted` | `dash:v1:u{user}:c{course}`, `prog:v1:u{user}:*`, `mist:*:count` |
| Answer submitted | `mist:v1:u{user}:c{course}:count` only (dashboard TTL of 60 s absorbs the rest — deleting it on every answer would defeat the cache) |
| Enrolment created/changed | `dash:*u{user}*`, `admin:v1:counts` |
| Notification created / read | `notif:v1:u{user}:unread` |
| Rollup job finishes | `admin:v1:dash:*` (pattern delete via a tracked key set, **not** `KEYS *`) |
| Settings updated | `settings:v1:public` |
| New final-test version | `final:v1:*`, `ftq:v1:*` for that test |

Pattern deletes use Laravel cache **tags** (Redis-backed) or a maintained set of keys per group. `KEYS`/`SCAN`-based
wildcard deletes in a request path are forbidden — they are O(keyspace) and have caused production stalls in systems
like this.

### 6. Stampede protection

- `Cache::flexible()` (Laravel's SWR helper) for the dashboard and category lists: serve slightly stale data instantly
  while one request refreshes in the background.
- `Cache::lock()` around expensive rebuilds (admin dashboard payload, final-test order) so 200 simultaneous requests
  after an expiry trigger **one** rebuild, not 200.
- Jittered TTLs (`ttl ± 10%`) so thousands of per-user dashboard keys created at the same moment do not all expire in the
  same second.

### 7. Session & other Redis uses

- `SESSION_DRIVER=redis` — only relevant for the tiny stateful surface (password reset flow, Horizon dashboard login).
  The student/admin API itself is token-based and stateless.
- Rate limiting, `ShouldBeUnique` job locks, `WithoutOverlapping` scheduler locks, Horizon metrics.
- **Not** used as a primary store for anything student-facing. Losing Redis entirely degrades performance and delays
  queued work; it never loses a student's answer.

---

## 21. Queue architecture

### 1. Queues, priorities and pools

| Queue | Contents | Pool | Workers (scalable tier) | Timeout | Tries / backoff |
|---|---|---|---|---|---|
| **critical** | `GradeFinalTestJob`, quiz-completion follow-ups, certificate issuance, security events, account operations | A | 4–10 (autoscale) | 60 s | 5 / 5,15,30,60 s |
| **default** | progress recalculation, image optimisation, misc background work | A | 2–6 | 120 s | 3 / 30,120,300 s |
| **notifications** | push batches, reminder dispatch, announcements | A | 2–8 | 120 s | 3 / 60,300,900 s |
| **mail** | verification, password reset, report-ready mail | A | 1–3 | 60 s | 3 / 60,300,900 s |
| **pdf** | PDF extraction, page batches, approval bulk inserts, (future) OCR | **B** | 1–3 | **900 s** | 3 / 60,300,900 s |
| **reports** | CSV/XLSX/PDF exports, analytics rollups | **B** | 1–2 | **600 s** | 2 / 120,600 s |

**Pool A** = low-latency workers: `--queue=critical,default,notifications,mail`, `memory=256`, `max-jobs=1000`,
`max-time=3600`. **Pool B** = heavy workers: `--queue=pdf,reports`, `memory=1024`, `timeout=900`, `max-jobs=50`.

Two separate Horizon supervisors on (ideally) separate machines or containers. This is the structural guarantee behind
business rule 24: **a 400-page PDF cannot delay a student's quiz completion**, because the PDF job is not merely lower
priority — it is running on different CPUs with a different memory budget.

Within pool A, Horizon's `balance=auto` with queue ordering `critical,notifications,default,mail` ensures a burst of
reminder pushes cannot starve final-test grading.

### 2. Horizon configuration sketch

```php
'environments' => [
    'production' => [
        'supervisor-latency' => [
            'connection' => 'redis-queue',
            'queue' => ['critical', 'notifications', 'default', 'mail'],
            'balance' => 'auto', 'autoScalingStrategy' => 'time',
            'minProcesses' => 2, 'maxProcesses' => 20,
            'balanceMaxShift' => 3, 'balanceCooldown' => 3,
            'tries' => 3, 'timeout' => 120, 'memory' => 256, 'nice' => 0,
        ],
        'supervisor-heavy' => [
            'connection' => 'redis-queue',
            'queue' => ['pdf', 'reports'],
            'balance' => 'simple',
            'minProcesses' => 1, 'maxProcesses' => 4,
            'tries' => 3, 'timeout' => 900, 'memory' => 1024, 'nice' => 10,
        ],
    ],
],
'waits' => ['redis-queue:critical' => 30, 'redis-queue:notifications' => 300,
            'redis-queue:pdf' => 3600, 'redis-queue:reports' => 1800],
'trim' => ['recent' => 60, 'pending' => 60, 'completed' => 60, 'failed' => 10080],
```
`nice 10` on the heavy pool means that even on a single-server deployment (the low-cost tier, where both pools share a
box) the OS scheduler favours latency-sensitive work.

### 3. Job design rules

- **Idempotent by construction.** Every job can run twice without harm: grading re-derives from stored answers,
  materialisation upserts, notifications check a Redis dedupe key, rollups upsert on `(metric_date, course_id)`.
- **`ShouldBeUnique`** on `ProcessPdfImportJob` (key: import id, 1 h), `GenerateDailyQuizzesJob` (course id),
  `RefreshDashboardAggregatesJob` (date), `GradeFinalTestJob` (attempt id) — prevents duplicate work from double clicks
  or scheduler overlap.
- **Small payloads.** Jobs carry IDs, never Eloquent models with loaded relations (`SerializesModels` re-fetches, and a
  fat payload bloats Redis).
- **`failed()` hook on every job that owns a user-visible status** (`pdf_imports`, `report_exports`,
  `final_test_attempts`) so a crash always produces a terminal state and an actionable admin message — never an eternal
  spinner.
- **Batches** (`Bus::batch`) for fan-out work: push campaigns and PDF approval, so progress percentages and
  `then/catch/finally` callbacks are free.
- **Chunked DB access** inside jobs: `chunkById(500)` with explicit `select()`. No `all()`, no queries in loops
  (`ProcessPdfImportJob` builds arrays and calls `insert()` once per 200 items).
- **Backoff with jitter** on external calls (push endpoints, S3) to avoid synchronised retry storms.

### 4. Scheduler (dispatch only)

`routes/console.php`, single scheduler process, all with `withoutOverlapping()` and `onOneServer()`:

| Cadence | Command / job | Notes |
|---|---|---|
| every 5 min | `DispatchDailyRemindersJob` | timezone slot matching; dispatches batches only |
| every 15 min | `PruneStaleAttemptsJob` | `in_progress` with no activity for 12 h → `abandoned` |
| hourly | `RefreshDashboardAggregatesJob(today)` | incremental rollup for the current day |
| hourly | `CleanupIdempotencyArtifactsJob` | trims tracked key sets |
| daily 02:00 | `RefreshDashboardAggregatesJob(yesterday, full)` | authoritative recompute |
| daily 02:30 | `RecalculateQuestionDifficultyJob` | updates `questions.difficulty_score` from the answer log |
| daily 03:00 | `PruneExpiredSubscriptionsJob` | push hygiene |
| daily 03:15 | `CleanupAbandonedImportsJob` | fail stuck imports, delete old files |
| daily 03:30 | `PurgeExpiredReportExportsJob` | delete expired export files + rows |
| daily 04:00 | `quiz:verify-question-integrity` | denormalisation drift check → alerts |
| daily 09:00 (local slots) | `DispatchMissedQuizRemindersJob` | chunked |
| weekly | `PruneOldActivityLogsJob`, `horizon:snapshot` trimming, `PruneNotificationsJob` (> 90 d) | retention |
| every minute | `horizon:snapshot` | metrics |

The scheduler process itself is **never** where work happens — every entry above dispatches a job. This keeps the
scheduler restartable at any moment without losing work.

### 5. Failure handling & dead letters

- `failed_jobs` is the dead-letter table; Horizon surfaces it, and a Sentry alert fires when the failed count increases
  by more than 5 in 10 minutes.
- Retryable vs terminal is explicit: `PdfExtractionException` subclasses declare `$retryable`; terminal ones call
  `$this->fail()` immediately instead of burning three attempts (a password-protected PDF will never succeed on retry).
- `queue:retry` is an admin-visible action for PDF imports and report exports (`POST /retry` endpoints), which re-queues
  rather than asking an operator to SSH in.
- Alert thresholds: `critical` wait > 30 s, `notifications` wait > 5 min, `pdf` wait > 1 h, any queue length > 5,000,
  worker memory > 90% of limit, failed jobs > 10/hour.

### 6. Why not Octane (yet)

Octane would cut per-request bootstrap (~15–30 ms) but introduces state-leak risk in exactly the classes that hold quiz
authority (singleton services, static caches) — the highest-consequence place for a subtle bug. Decision: **ship
without Octane**, run the k6 suite from [11](./11-testing-and-load-plan.md), and only adopt it if p95 for the quiz
endpoints is bottlenecked on PHP bootstrap rather than MySQL/Redis. If adopted, the checklist is: audit every singleton
in `AppServiceProvider`, forbid static request state, reset `auth()` per request (Octane does this, but verify),
memory-growth soak test for 24 h, and package compatibility review. FrankenPHP would be the first choice for its
simpler ops story.
