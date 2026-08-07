# 09 — Performance Architecture & Scalability Plan

## 23. Performance architecture

### 1. Performance budgets (the numbers we design against)

| Metric | Target | Hard ceiling | Measured by |
|---|---|---|---|
| Initial JS (gzip/brotli, entry + shared chunks) | ≤ 180 KB | 250 KB | `rollup-plugin-visualizer` + CI size check |
| Initial CSS | ≤ 25 KB | 40 KB | CI size check |
| LCP (mid-tier Android, 4G throttled) | ≤ 2.0 s | 2.5 s | Lighthouse CI + field RUM |
| Student dashboard interactive | ≤ 2.0 s | 2.5 s | Lighthouse CI (custom user flow) |
| INP | ≤ 150 ms | 200 ms | field RUM (`web-vitals`) |
| CLS | ≤ 0.05 | 0.1 | Lighthouse CI |
| Quiz question transition (after initial load) | ≤ 60 ms | 100 ms | React Profiler mark + a Playwright timing assertion |
| API p95, cached reads | ≤ 150 ms | 300 ms | k6 + Pulse |
| API p95, uncached reads | ≤ 350 ms | 700 ms | k6 + Pulse |
| API p95, answer submission | ≤ 250 ms | 500 ms | k6 |
| API p99, answer submission | ≤ 600 ms | 1,000 ms | k6 |
| Final-test batch sync p95 | ≤ 300 ms | 700 ms | k6 |
| Queries per quiz-day load | ≤ 4 | 6 | Pest query-count assertion |
| Queries per answer submission | ≤ 8 | 10 | Pest query-count assertion |

CI fails on bundle-size regressions > 5% and on query-count regressions. Budgets are enforced, not aspirational.

### 2. Backend performance measures

**Query discipline**
- `Model::preventLazyLoading()` outside production → N+1 becomes a test failure.
- Explicit `select()` on every hot query; the quiz-day query never selects `explanation`/`correct_*` at all (so a leak
  is impossible *and* the row is smaller).
- `with()` eager loading with column lists: `with('options:id,question_id,option_key,option_text,display_order')`.
- Set-based operations replace loops: `INSERT ... SELECT` for final-test materialisation, `upsert()` for batch answers,
  a single `UPDATE ... JOIN` for grading, bulk `insert()` in chunks of 200–500 for PDF approval.
- Cursor pagination (`cursorPaginate`) for admin question tables, mistake lists, activity logs and quiz history — no
  `COUNT(*)` over large tables, and stable results while rows are inserted.
- Offset pagination only where a page count is genuinely useful (small admin lists).
- `chunkById()` + `select()` in every job that walks students or answers.
- Counter caches (`courses.questions_count`, `exam_categories.courses_count`, `enrollments_count`) maintained
  transactionally, so list screens never `COUNT(*)` per row.
- Dashboard aggregates come from `report_daily_metrics`, never from live scans of `quiz_attempt_answers`.

**Caching** — see [07](./07-redis-and-queues.md). Key points: quiz payload cached by content version; per-user
summaries with short TTLs and jitter; `Cache::flexible` for SWR; locks against stampedes.

**Queues** — anything that can exceed ~500 ms of CPU is a job. Physical pool separation protects quiz latency.

**Response size and transport**
- API Resources return only screen-required fields; no timestamps the UI doesn't render; no nested course objects where
  `{id, title, slug}` suffices.
- Brotli/gzip at Nginx for JSON (`gzip_types application/json`, `brotli_comp_level 5`).
- ETag/`If-None-Match` on categories, courses and quiz-day payloads → 304s on repeat opens (a big win on slow mobile
  connections).
- Quiz-day payload target: **< 25 KB gzipped** for 10 questions with options.

**Runtime**
- OPcache with `validate_timestamps=0` in production + `opcache.jit=tracing`, `jit_buffer_size=128M`.
- `composer install --no-dev --optimize-autoloader --classmap-authoritative`.
- `config:cache`, `route:cache`, `event:cache`, `view:cache` on every deploy.
- Log level `warning` in production, JSON formatter, `daily` driver with 14-day rotation; no debug logging of payloads.
- Persistent Redis connections (phpredis), MySQL connection limits sized to `pm.max_children × nodes` + workers + headroom.
- `DB::listen` slow-query logging above 200 ms (sampled 10% in production to bound overhead).

### 3. Frontend performance measures

**Loading**
- Route-level `lazy()` for every page; the initial bundle contains only the shell, auth and dashboard-critical code.
- Hard-split heavy libraries: `recharts` (~90 KB) and `framer-motion` (~40 KB) are only reachable from lazy chunks; an
  ESLint rule prevents accidental static imports elsewhere. Admin code is a separate chunk tree that students never
  download.
- Manual chunk hints for `react`/`react-dom`/`react-router` (long-lived vendor chunk) so app updates don't invalidate them.
- `<link rel="preconnect">` to the API origin and CDN; `modulepreload` for the dashboard chunk after login.
- Route prefetch on intent: hovering/focusing "Start Today's Quiz" prefetches the quiz chunk **and** the quiz-day query.
- Fonts: one family, two weights (400/600), `woff2`, subset to Latin + the target script, `font-display: swap`,
  self-hosted (no third-party font origin).
- Images: WebP/AVIF with `<picture>`, explicit `width`/`height` (CLS), `loading="lazy"` + `decoding="async"` for
  non-critical, `srcset` for category/course thumbnails, and server-side resizing at upload (max 1600 px, thumbnails at
  400 px).

**Rendering**
- Quiz screen renders exactly one `QuestionCard`; option state lives in a reducer, so selecting an option re-renders the
  option list only.
- Final test: one question mounted, palette virtualised with `@tanstack/react-virtual` (≈30 of 300 cells in the DOM).
- Admin question table virtualised above 100 rows.
- Skeletons with **fixed dimensions** matching the loaded content, so there is no layout shift.
- Memoisation applied only where measured: the palette cell component, chart data transforms, and the option list.
  No blanket `React.memo`. (Rule: add memoisation only with a Profiler screenshot in the PR.)
- Framer Motion limited to: question enter/exit (150 ms), correct/incorrect feedback, sheet/modal transitions, progress
  bar fill. All wrapped so `prefers-reduced-motion` collapses them to instant.
- `content-visibility: auto` on long list sections (mistake review, question tables).

**Data**
- TanStack Query defaults: `staleTime` 60 s (30 s for dashboard), `gcTime` 15 min, `retry: 2` with exponential backoff,
  `retry: 0` for mutations that the outbox owns, `refetchOnWindowFocus: false` for quiz screens (a refetch mid-question
  would be jarring) and `true` for the dashboard.
- Query-key factories in one file; invalidation is targeted (`queryKeys.quiz.day(courseId, day)`), never
  `queryClient.invalidateQueries()` with no key.
- Next-question prefetch: the quiz payload already contains all 10 questions (one request), so transitions are pure
  client-side state changes — no network on "Next". Images inside questions are preloaded one question ahead.
- Debounced search (300 ms) with `AbortSignal` cancellation; debounced position saves (1.5 s) and batch answer syncs.
- No duplicate requests: TanStack Query deduplicates by key; the outbox holds a single in-flight drain via a web lock.

### 4. Where the bottlenecks will actually be (ranked prediction)

| Rank | Bottleneck | Symptom | Mitigation already designed | Escalation |
|---|---|---|---|---|
| 1 | **Answer-submission write contention** at peak evening hours | p99 on `POST /answers` climbs; row-lock waits on `quiz_attempts` | Locks are per-attempt (never global); 7 single-row statements; no aggregate reads | Increase PHP-FPM children; move to a larger DB instance; if lock waits dominate, drop the state-row `FOR UPDATE` in favour of an atomic conditional `UPDATE` |
| 2 | **Admin dashboard aggregates** | Slow admin page, DB CPU spikes at odd times | Rollup tables + 5-min cache; never live scans | Move reporting reads to a replica |
| 3 | **PDF extraction CPU/memory** | Worker OOM, long queue waits | Separate pool, page batching, memory cap, page limit, timeouts | Dedicated worker box; split by page ranges into parallel jobs |
| 4 | **Push fan-out at a popular reminder time** (e.g. 19:00 for 5,000 students) | notifications queue backlog | Timezone slots, 5-min granularity, 500-row chunks, 100-user jobs, HTTP/2 batching in the push library | More notification workers; spread reminder times ±5 min per student |
| 5 | **Final-test batch sync storms** (many students submitting near a deadline) | Spikes on `/answers/batch` | Debounced client batching, upserts only, idempotent | Raise batch size, add a jittered flush interval |
| 6 | **MySQL connection exhaustion** | "Too many connections" under autoscale | Sized limits documented; workers use fewer connections than API nodes | Add ProxySQL/RDS Proxy for pooling |
| 7 | **Cache stampede after a deploy** (cold Redis) | Latency spike for 30–60 s | Jittered TTLs, locks, `flexible` SWR | Warm the top keys in the deploy script |
| 8 | **Frontend bundle creep** as admin features land | LCP regression on student devices | Admin chunk tree separated; CI size budget | Route-level analysis; consider a separate admin build/domain |

### 5. Quiz-loading performance (the flagship path)

Detailed in [05](./05-flows-and-algorithms.md). Summary of why it is fast:
1 Redis read for question content (or one indexed range scan on miss) + 2 indexed single-row reads for attempt state.
All 10 questions arrive in **one** response, so navigating between questions costs zero network. Answer submission is 7
single-row statements. Resume needs no extra queries because `current_position`, mastered ids and retry ids are part of
the same payload.

### 6. Final-test performance

One mounted question component, windowed fetches of 20, virtualised palette, local-first answer writes, debounced
batch upserts, two-statement grading on a background worker. A 300-question test therefore costs ~15 windowed fetches
and ~30 batch syncs over an hour — not 300 requests, and never 300 mounted components.

---

## 24. Scalability plan

### 1. Scaling axes and order of action

| Load signal | First action | Second | Third |
|---|---|---|---|
| API CPU > 70% sustained | add API nodes behind the LB (stateless, trivial) | tune PHP-FPM children / opcache | consider Octane (only with evidence) |
| DB CPU > 70% | verify indexes/slow log; cache more | add a read replica for reports/dashboards | vertical upgrade, then table archival/partitioning |
| Queue wait above thresholds | scale the relevant worker pool | split the queue further (e.g. `pdf-ocr`) | dedicated worker hosts |
| Redis memory pressure | raise `maxmemory`, verify TTLs | split cache vs queue instances | Redis cluster (only if a single node is genuinely saturated) |
| Storage/egress cost | CDN in front of object storage | image format/size review | lifecycle rules to cheaper tiers |

### 2. Statelessness requirements (non-negotiable for horizontal scale)

- No local session files (Redis sessions; API is token-based anyway).
- No uploads on local disk in production (`FILESYSTEM_DISK=s3`).
- No in-process caches that assume a single node (all shared state in Redis).
- No cron on multiple nodes — exactly **one** scheduler process, plus `onOneServer()` guards.
- Sticky sessions are not required and must not be enabled (they mask state bugs and unbalance load).
- Health endpoints: `/up` (liveness, no DB) and `/health/ready` (readiness: DB + Redis + storage reachable) so the LB
  drains a node that lost its DB connection instead of serving errors.

### 3. Capacity model (rough, to be replaced by k6 numbers)

Assume a student answers ~10 questions in ~6 minutes, so a peak-hour concurrent student generates ~0.05 writes/s plus
~0.02 reads/s. At **1,000 concurrent students**: ~50 write req/s and ~20 read req/s, i.e. ~70 req/s.

- One 2 vCPU PHP-FPM node with 12 children at ~80 ms average API time serves ~150 req/s theoretical, ~100 req/s
  comfortably. **Two nodes** cover 1,000 concurrent students with headroom for admin traffic and retries.
- MySQL: ~50 write transactions/s of 7 tiny statements ≈ 350 statements/s — well inside a 2 vCPU managed instance
  (which handles thousands), provided indexes are right. The binlog/IO, not CPU, is the first thing to watch.
- Redis: ~200 ops/s — negligible.
- Verified against these assumptions in [11](./11-testing-and-load-plan.md); the load tests exist precisely because this
  arithmetic must be validated, not trusted.

### 4. Growth staging

| Stage | Students | Topology | Notable additions |
|---|---|---|---|
| S0 pilot | < 200 | 1 VPS (all-in-one), local storage + offsite backup | Nothing exotic; same code |
| S1 launch | < 2,000 | 1 API node + managed MySQL + managed Redis + S3 + CDN; 2 worker pools on one box | Object storage, CDN, Horizon |
| S2 growth | < 10,000 | LB + 2–3 API nodes; separate worker host; rollup tables mandatory | Autoscaling, read replica if reports bite |
| S3 scale | 50,000+ | Autoscaling API group, dedicated worker groups per queue, read replica, RDS/DB proxy, archival of old answers | Consider Octane, partitioning, separate analytics store |

Each stage is a **configuration** change plus infrastructure, not a rewrite. That is the point of the stateless design.

### 5. Multi-region and read replicas

Not in v1. When needed: keep writes in one region (quiz correctness depends on strong consistency for the
completion transaction), serve static assets and images globally from the CDN, and put read replicas behind
report/dashboard queries only. Laravel's `read`/`write` connection split will be configured with **`sticky => true`** so
a student never reads stale data immediately after their own write — this matters enormously here: reading a replica
right after completing day 6 could show day 7 as still locked.

### 6. Data growth management

- `quiz_attempt_answers` and `notification_deliveries` are the growth drivers. Retention/archival policy:
  `notification_deliveries` pruned at 90 days; answers retained but archivable to a cold table after a course is
  completed and results are final (a documented, reversible job — not automatic in v1).
- Monthly `ANALYZE TABLE` on hot tables (or rely on InnoDB's persistent stats) and a quarterly index-usage review
  (`sys.schema_unused_indexes`) so the index set doesn't silently accumulate write cost.

### 7. When to partition

Only when `quiz_attempt_answers` exceeds ~100 M rows or its index no longer fits comfortably in the buffer pool. Plan:
`RANGE` partition by `recorded_at` (monthly), keep 13 partitions hot, archive older ones. Every hot query already filters
by `quiz_attempt_id`, so partition pruning by date would help maintenance (dropping old partitions is instant) more than
query speed — which is exactly the reason to defer it.

### 8. Deliberately avoided complexity

- **No microservices.** One Laravel app with clear service boundaries; the queue already gives us async decoupling.
- **No event sourcing / CQRS.** Rollup tables give report performance without the operational cost.
- **No GraphQL.** The screens are known and dedicated REST endpoints ship smaller payloads with simpler caching.
- **No Kubernetes for S0–S2.** Supervisor + systemd on VMs (or a managed platform) is less to operate.
- **No WebSockets.** Polling and SSE meet the only real-time need (import progress).
- **No Octane until measured.** See [07 §6](./07-redis-and-queues.md#6-why-not-octane-yet).

Each of these can be added later; none is cheaper to add now than the complexity it would cost today.
