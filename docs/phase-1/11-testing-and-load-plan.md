# 11 — Load-Testing Plan & Performance Acceptance Criteria

## 27. Load-testing plan

Tool: **k6** (scriptable in JS, good CI story, clean thresholds). Artillery is the fallback if the team prefers YAML.
Scripts live in `load-tests/`; they run against **staging with production-shaped data**, never against production.

### 1. Test data prerequisites

A dedicated seeder (`php artisan quiz:seed-load-test`) builds:

- 1 exam category, 2 courses (one with 30 days × 10 questions = 300 questions, one small control course)
- 6,000 students with a realistic progress distribution: 20% at day 1–3, 40% at day 4–15, 25% at day 16–29,
  10% at day 30 (final-test eligible), 5% finished
- 3 admin users
- Pre-issued tokens written to `load-tests/data/users.csv` (k6 reads them; login is exercised in its own scenario)
- 3 PDF fixtures: 20-page clean, 150-page messy, 400-page large

Every run starts from a snapshot restore so results are comparable across runs.

### 2. Scenario catalogue

| # | Scenario | Shape | What it proves |
|---|---|---|---|
| S1 | **Login storm** | 100 → 500 → 1,000 VUs ramping over 3 min, one login each | Auth + bcrypt cost don't melt CPU; throttling behaves; p95 login < 800 ms |
| S2 | **Dashboard load** | constant-arrival 200 rps for 5 min | Dashboard cache works; p95 < 300 ms cached |
| S3 | **Daily quiz journey (the flagship)** | 100 / 500 / 1,000 concurrent VUs, each: dashboard → day fetch → start attempt → 10 answers with 20–40 s think time (30% wrong on first try, then retry) → complete | End-to-end realism; p95 answer < 500 ms; zero 5xx; every VU ends with exactly 10/10 |
| S4 | **Concurrent submission on one attempt** | 50 VUs double-submitting the same answer with the same idempotency key | Exactly one DB row per logical answer; no 5xx; replays return the stored response |
| S5 | **Concurrent completion race** | 200 attempts, each completed by 3 parallel requests | One completion, one streak increment, one unlock per attempt |
| S6 | **Next-day unlock correctness under load** | after S3, 1,000 VUs request day N+1 | All succeed; no VU can fetch day N+2 (must be 423) |
| S7 | **Locked-day hammering** | 500 VUs requesting random future days and other students' attempt ids | 100% 423/404; no data leakage; DB load stays flat (rejections are cheap) |
| S8 | **Final-test progress saving** | 300 VUs, each: start → 15 windowed fetches → 30 batch syncs of 10 answers → submit | Batch upserts scale; p95 batch < 700 ms; grading queue drains |
| S9 | **Final-test submission surge** | 300 submissions within 60 s | `critical` queue wait < 30 s; all attempts reach `graded` |
| S10 | **Large PDF upload + extraction** | 3 concurrent 150-page + 1 × 400-page upload **while S3 runs at 500 VUs** | **Isolation proof**: quiz p95 must not degrade > 10% while PDFs process |
| S11 | **Push notification batch** | trigger reminders for 5,000 students across 12 timezones | Fan-out completes < 5 min; memory flat; no `User::all()`; dead endpoints pruned |
| S12 | **Admin question search** | 20 admin VUs, fulltext + filters + cursor paging over 100k questions | p95 < 700 ms; no full scans in the slow log |
| S13 | **Large report generation** | 5 concurrent 100k-row exports | Runs on the `reports` queue only; API latency unaffected; memory bounded (chunked writer) |
| S14 | **Offline sync storm** | 500 VUs each draining a 20-answer outbox batch simultaneously (simulating a network coming back) | No duplicates; p95 batch < 800 ms; idempotency ledger holds |
| S15 | **Soak** | 100 VUs mixed traffic for 4 hours | No memory growth in PHP-FPM/workers; no connection leaks; queue steady state |
| S16 | **Spike** | 50 → 1,500 VUs in 30 s, hold 2 min | Autoscaling/queueing behaviour; graceful 429s rather than 5xx |

### 3. k6 thresholds (fail the run, not just report)

```js
// load-tests/thresholds.js  (applied per scenario as relevant)
export const thresholds = {
  'http_req_failed': ['rate<0.005'],                                  // < 0.5% errors
  'http_req_duration{name:answer_submit}':   ['p(95)<500', 'p(99)<1000'],
  'http_req_duration{name:quiz_day_fetch}':  ['p(95)<700'],
  'http_req_duration{name:dashboard}':       ['p(95)<300'],
  'http_req_duration{name:final_batch_sync}':['p(95)<700'],
  'http_req_duration{name:complete_day}':    ['p(95)<800'],
  'http_req_duration{name:login}':           ['p(95)<800'],
  'checks': ['rate>0.995'],
  'group_duration{group:::daily_quiz_journey}': ['p(95)<90000'],
};
```
Plus **correctness checks inside the load test** (this is what distinguishes a useful load test from a traffic
generator):

```js
check(res, {
  'answer response never contains an answer key before submission':
      () => !dayFetchBody.includes('correct_option') && !dayFetchBody.includes('explanation'),
  'locked day rejected with 423': () => lockedRes.status === 423,
  'completion reports exactly 10/10': () => completeBody.data.score === 10,
  'idempotent replay flagged': () => replayRes.headers['X-Idempotent-Replay'] === 'true',
});
```

### 4. Server-side measurements captured for every run

| Layer | Metrics | Source |
|---|---|---|
| API | rps, avg/p95/p99 per endpoint, error rate, 429 count | k6 + Pulse |
| PHP-FPM | active/idle children, queue depth, slow requests, memory/child | FPM status page |
| MySQL | CPU, QPS, slow queries, `Innodb_row_lock_waits`, `Threads_connected`, buffer-pool hit ratio, temp tables on disk | `performance_schema` snapshots before/after |
| Redis | ops/s, memory, evicted keys (**must be 0** on the queue instance), hit ratio | `INFO` snapshots |
| Queues | wait time and length per queue, worker memory, failed count | Horizon metrics |
| System | CPU, load, memory, disk IO, network | node exporter / provider metrics |

Each run produces `load-tests/results/<date>-<scenario>.md`: thresholds pass/fail, the six tables above, the top 5 slow
queries with `EXPLAIN`, and a written conclusion with the next optimisation to try. Results are compared run-over-run;
a regression > 15% on any p95 is treated as a bug.

### 5. Analysis method

1. Run S3 at 100 VUs → establish the baseline.
2. Scale to 500, then 1,000. Find the first knee in the latency curve.
3. Identify the constrained resource at the knee (FPM saturation vs MySQL CPU vs row-lock waits vs Redis).
4. Apply **one** change; re-run the same scenario; keep or revert based on measurement.
5. Only then consider architectural changes (replica, Octane, more nodes).

Expected findings, stated in advance so we can check our own reasoning honestly:
answer-submission row-lock contention will appear first on a small DB instance; `pm.max_children` will be the binding
constraint on a 2 vCPU node before MySQL is; the push fan-out will be I/O-bound on external endpoints, not on us; the
admin question search will be the worst single query until the fulltext index is verified in use.

### 6. Frontend load/perf testing

- Lighthouse CI on 4 routes (login, dashboard, quiz day, progress) with mobile throttling; budgets from
  [09](./09-performance-and-scalability.md#1-performance-budgets-the-numbers-we-design-against).
- WebPageTest run on a real low-end Android profile (Moto G-class, 4G) before launch; record filmstrip for the quiz flow.
- Playwright timing assertions: question transition < 100 ms, palette open < 150 ms on a CPU-throttled (4×) profile.
- Bundle analysis committed per release (`stats.html` artifact) so growth is visible in review.

---

## 28. Performance acceptance criteria (pre-production gate)

Every item is a checkbox with a defined verification method. Launch is blocked until each is either **passed** or
explicitly **waived in writing** with a reason and an owner.

### Database & queries
- [ ] No N+1 queries on any student route — verified by `preventLazyLoading` in tests plus Pest query-count assertions
- [ ] No queries inside loops anywhere in `app/` — verified by code review checklist + Larastan custom rule where feasible
- [ ] Daily quiz load ≤ 4 queries; answer submission ≤ 8 statements — automated assertions
- [ ] `EXPLAIN` captured for the 8 baseline queries; none shows a full table scan of a hot table or `Using temporary; Using filesort` on a paginated list
- [ ] No `Model::all()` on `questions`, `users`, `quiz_attempt_answers`, `student_question_progress` — grep + review
- [ ] All admin list endpoints paginated (cursor where the table is large)
- [ ] Bulk operations use `insert`/`upsert` in chunks; no per-row queries in imports or grading
- [ ] `sys.schema_unused_indexes` reviewed; no index without a named query justifying it

### Quiz correctness & security (performance-adjacent but non-negotiable)
- [ ] Correct answers never present in any pre-submission response (automated `AnswerLeakageTest` green)
- [ ] Locked quiz URLs rejected by the API on every route shape (423) — automated
- [ ] Concurrent completion is safe: 3-way race yields one completion, one streak, one unlock — automated
- [ ] Offline sync of a 20-answer outbox creates exactly 20 rows, replays create 0 extra — automated
- [ ] Idempotency ledger survives a Redis flush (DB unique constraint catches it) — automated

### Caching
- [ ] `quiz:*` cache hit ratio > 80% under S3 load
- [ ] Cache invalidation verified for: question edit, day membership change, course config change, completion
- [ ] No user-specific data served from a shared cache key — key-shape unit test green
- [ ] Redis queue instance shows **0 evicted keys** under all scenarios

### Queues
- [ ] `critical` queue wait p95 < 30 s during S9 and S10
- [ ] Quiz p95 degrades < 10% while three large PDFs process (S10) — the isolation proof
- [ ] Push fan-out for 5,000 students completes < 5 min with flat worker memory (S11)
- [ ] Every job has explicit tries/backoff/timeout; every status-owning job has a `failed()` hook
- [ ] Failed-job alerting verified by deliberately failing a job in staging

### Final test
- [ ] Only one question component mounted at any time — asserted in a React test by counting rendered question nodes
- [ ] Palette renders ≤ 40 cells for a 300-question test (virtualisation working)
- [ ] Batch sync sends ≤ 1 request per 10 answers or 3 s, never one per selection — verified with a network-log assertion
- [ ] Refresh mid-test restores position and answers with no data loss — Playwright test
- [ ] Grading a 300-question attempt uses ≤ 4 queries and completes < 10 s (p95)

### Frontend
- [ ] Initial JS ≤ 250 KB compressed (target 180 KB); CI budget green
- [ ] `recharts`, `framer-motion` and all admin code absent from the student initial chunk — verified in `stats.html`
- [ ] LCP ≤ 2.5 s and dashboard interactive ≤ 2.5 s on throttled mobile (Lighthouse CI)
- [ ] INP ≤ 200 ms, CLS ≤ 0.1 on all four audited routes
- [ ] Question transition < 100 ms on a 4× CPU-throttled profile
- [ ] Images served as WebP/AVIF with dimensions set; no image > 200 KB on student routes
- [ ] Skeletons prevent layout shift (CLS contribution ≈ 0 on quiz and dashboard)
- [ ] No unnecessary refetch on mount for dashboard/quiz (verified via network log)

### PWA & offline
- [ ] Lighthouse PWA audit passes (installable, offline-capable, correct manifest/icons)
- [ ] Old caches removed on activate (verified by inspecting `caches.keys()` after two deploys)
- [ ] Offline: previously visited safe screens open; quiz answers queue and sync with no duplicates
- [ ] Logout clears IndexedDB, `qp-api-*` caches and the push subscription — browser test
- [ ] Contract-version mismatch triggers an update prompt and reload — staged test with a bumped header
- [ ] No answer keys, tokens or admin data found in IndexedDB/localStorage after a full journey — manual inspection

### Reports & files
- [ ] All exports run asynchronously; no export generated in an HTTP request
- [ ] 100k-row export memory stays under the worker limit (chunked writer verified)
- [ ] Private files only reachable via short-lived signed URLs; expired URLs rejected
- [ ] Report files deleted after expiry by the prune job

### Operations
- [ ] Production caches enabled (`config`, `route`, `event`, `view`) and verified by a post-deploy probe
- [ ] `APP_DEBUG=false`; Telescope not installed/registered; Horizon gated; `/telescope` returns 404
- [ ] Monitoring dashboards live; alerts tested end-to-end (page + ticket paths)
- [ ] Backup **restore drill** completed and documented
- [ ] Log rotation configured; no PII/tokens in logs after a full journey
- [ ] Load-test report for 100 / 500 / 1,000 concurrent students committed with conclusions and follow-ups
- [ ] Runbooks written for the nine scenarios listed in [10 §5](./10-deployment-and-monitoring.md#5-runbooks-written-in-phase-10-listed-here-so-they-arent-forgotten)

### Accessibility (gated alongside performance)
- [ ] Full quiz flow completable with keyboard only, including submit and retry
- [ ] Correct/incorrect conveyed by icon + text, not colour alone
- [ ] Answer results announced via `aria-live`; focus moves sensibly between questions
- [ ] Modals trap focus, close on Esc, and restore focus to the trigger
- [ ] Contrast ≥ 4.5:1 for text (≥ 3:1 for large text/UI) in both themes; `axe` clean on audited routes
- [ ] Touch targets ≥ 44 × 44 px on all interactive quiz elements
- [ ] `prefers-reduced-motion` removes all non-essential animation
