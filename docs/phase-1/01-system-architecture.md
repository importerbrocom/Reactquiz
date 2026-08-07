# 01 — System Architecture & Technology Versions

## 1. System architecture

### 1.1 Topology (production, scalable target)

```
                            ┌──────────────────────────┐
   Student / Admin browser  │  React 19 PWA (static)   │
   (Android low-end, iOS,   │  Vite 8 build → S3/R2    │
    desktop)                │  served via CDN edge     │
                            └───────────┬──────────────┘
                                        │  HTTPS, HTTP/2+3
                                        │  XHR (Axios) + Service Worker
                            ┌───────────▼──────────────┐
                            │  CDN / Edge (Cloudflare) │
                            │  - static asset cache    │
                            │  - Brotli, HTTP/3        │
                            │  - WAF + rate limit L7   │
                            └───────────┬──────────────┘
                     api.example.com    │    app.example.com
                            ┌───────────▼──────────────┐
                            │      Load balancer       │
                            │   (TLS term, health cks) │
                            └─────┬───────────────┬────┘
                                  │               │
                    ┌─────────────▼───┐   ┌───────▼─────────┐
                    │ API node 1      │   │ API node N      │   stateless
                    │ Nginx + PHP-FPM │   │ Nginx + PHP-FPM │   php artisan *:cache
                    │ Laravel 13      │   │ Laravel 13      │   no local uploads
                    └───┬────────┬────┘   └───┬─────────┬───┘
                        │        │            │         │
        ┌───────────────▼──┐  ┌──▼────────────▼──┐  ┌───▼──────────────────┐
        │ MySQL 8.4        │  │ Redis 7.4         │  │ Object storage (S3/  │
        │ primary (+ read  │  │ - cache (db 0)    │  │ R2/Spaces)           │
        │ replica later)   │  │ - queues (db 1)   │  │ - private: PDFs,     │
        │ InnoDB, utf8mb4  │  │ - session/locks   │  │   reports (signed)   │
        └──────────────────┘  │ - Horizon metrics │  │ - public: images via │
                              └──┬────────────┬───┘  │   CDN                │
                                 │            │      └──────────────────────┘
                 ┌───────────────▼──┐  ┌──────▼────────────┐
                 │ Worker pool A    │  │ Worker pool B     │
                 │ critical,default │  │ pdf, reports      │
                 │ notifications    │  │ (high memory,     │
                 │ (low latency)    │  │  long timeout)    │
                 └──────────────────┘  └───────────────────┘
                 ┌──────────────────────────────────────────┐
                 │ Scheduler (single process, php artisan   │
                 │ schedule:work) → only dispatches jobs    │
                 └──────────────────────────────────────────┘
                 ┌──────────────────────────────────────────┐
                 │ Horizon dashboard (admin-gated) + Pulse  │
                 │ Sentry (API + browser), uptime monitor   │
                 └──────────────────────────────────────────┘
```

### 1.2 Component responsibilities

| Component | Owns | Must never |
|---|---|---|
| React PWA | Rendering, local quiz UI state, offline outbox, optimistic *display* only | Decide unlock/correctness/score; store correct answers; hold tokens in `localStorage` |
| Service Worker | Static asset cache, navigation fallback, background sync of outbox | Cache answer-evaluation responses, auth responses, admin data, private PDFs |
| IndexedDB | Pending answers, active attempt pointer, sync bookkeeping | Store correct answers pre-submission, store admin data |
| Laravel API | Authoritative rules, validation, authorisation, transactions, cache orchestration | Parse PDFs inline, generate large exports inline, send push inline |
| Redis | Cache, queues, locks, rate limits, Horizon metrics, idempotency ledger | Be the durable record for anything student-facing |
| MySQL | Durable truth for progress, answers, imports, audit | Be queried inside loops; be scanned for dashboard aggregates on every request |
| Workers | PDF extraction, push fan-out, report generation, aggregate rollups | Share a pool between `critical` and `pdf` |
| Object storage | PDFs (private), images (public/CDN), report exports (private + signed, expiring) | Hold the only copy of anything on a local app disk |

### 1.3 The three request paths that define the system

**Path A — Daily quiz load (must be fast, ≤ 700 ms uncached).**

```
GET /api/v1/student/courses/{course}/quiz-days/{day}
  → auth:sanctum (token) → ability:student → EnsureEnrolmentActive
  → QuizAccessPolicy@viewDay → QuizUnlockService::assertUnlocked()   [423 if locked]
  → DailyQuizService::deliver()
        Redis GET quiz:v1:course:{c}:day:{d}:questions       (question text/options, answer-free, 1 h TTL)
        MySQL 1 query: student_question_progress for this attempt (mastered / retry-required ids)
        MySQL 1 query: quiz_attempts current row
  → QuizDayResource  (no correct answers)
Total: 1 Redis hit + 2 indexed MySQL queries. No N+1. Payload target < 25 KB gzipped.
```

**Path B — Answer submission (must be correct and idempotent).**

```
POST /api/v1/student/quiz-attempts/{attempt}/answers
  headers: Idempotency-Key: <uuid v4 from client>
  body: { question_id, selected_option, client_answer_uuid, time_spent_ms, answered_at }
  → IdempotencyMiddleware (Redis SETNX idem:{user}:{key}, 24 h; replay → stored response)
  → QuizAccessPolicy@submitAnswer (attempt belongs to user, attempt in_progress, question in this day)
  → DB::transaction:
        AnswerEvaluationService::evaluate()   compares against questions.correct_option (server side)
        insert quiz_attempt_answers           (append-only, unique on client_answer_uuid)
        upsert student_question_progress      (attempts, correct/wrong counts, mastered_at)
        touch quiz_attempts.last_activity_at, counters
  → AnswerResultResource: { is_correct, correct_option?, correct_answer_text?, explanation?, progress_summary }
     Cache-Control: no-store
```
`correct_option` / `correct_answer_text` / `explanation` are included **only when `is_correct === false`**, plus
optionally on correct answers if the course enables `show_explanation_on_correct`.

**Path C — PDF import (must never touch the request path of A or B).**

```
POST /api/v1/admin/pdf-imports  (multipart, ≤ 50 MB, MIME + magic-byte checked)
  → store on private disk, compute sha256, dedupe by checksum
  → create pdf_imports row (status=uploaded) → dispatch ProcessPdfImportJob on queue "pdf"
  → 202 Accepted { import_id, status }
Worker (pool B): extract text page-batch by page-batch → parse → bulk insert pdf_import_items
  → status transitions: queued → processing → needs_review | partially_completed | failed
Admin polls GET /api/v1/admin/pdf-imports/{id}/progress (or subscribes to SSE stream)
Approval: POST /{id}/approve → validated bulk insert into questions (chunks of 500, one transaction per chunk)
```

### 1.4 Why this shape

- **Static frontend on a CDN, API on its own origin.** Gives the PWA sub-second cold loads worldwide on 3G, lets the
  API scale independently, and keeps deploys of UI and API decoupled. Cost: cross-origin auth, which is why we use
  token mode + a scoped refresh cookie rather than Sanctum's stateful cookie mode.
- **Stateless API nodes.** Session/cache/queue state lives in Redis, files in object storage, so nodes are
  disposable and autoscaling is trivial.
- **Two worker pools.** The single most likely production incident in this app is "admin uploaded a 500-page PDF and
  quiz submissions got slow." Physical pool separation, not just queue names, is what prevents it.
- **No WebSockets in v1.** The only real-time-ish need is PDF import progress, which SSE or 2-second polling handles at
  a fraction of the operational cost.

### 1.5 Simple version vs scalable version

| Concern | Simple v1 (single VPS, < 500 students) | Scalable (thousands of concurrent) |
|---|---|---|
| Hosting | One 4 GB VPS: Nginx + PHP-FPM + MySQL + Redis + Horizon | LB + 2..N API nodes, managed MySQL, managed Redis, 2 worker pools |
| Frontend | Served by the same Nginx from `/var/www/app/dist` | CDN (Cloudflare/CloudFront) + object storage |
| Files | Local `storage/app/private` (with nightly offsite backup) | S3/R2 with signed URLs |
| Aggregates | On-demand cached queries, 5 min TTL | Scheduled rollup into `report_*` summary tables |
| Read scaling | none | MySQL read replica for reports/dashboards |
| Cost | ~$20–30/month | ~$200–600/month depending on provider and traffic |

Both versions run **identical application code**; the difference is configuration (`FILESYSTEM_DISK`,
`CACHE_STORE`, queue worker layout, `DB_READ_HOST`). No forked codebase.

---

## 2. Recommended technology versions

Verified current as of **August 2026**. Pin exact versions in `composer.json` / `package.json`; use Renovate or
Dependabot for controlled bumps.

### 2.1 Backend

| Component | Version | Note |
|---|---|---|
| PHP | **8.4.x** | Required minimum 8.3 by Laravel 13; 8.4 gives property hooks + better JIT/opcache behaviour. Sandbox default is 8.4. |
| Laravel | **13.x** | Current major since March 2026 ([release notes](https://laravel-news.com/laravel-13-released)); bug fixes through Q3 2027, security through Q1 2028. Choose 12.x only if the host is stuck on PHP 8.2. |
| laravel/sanctum | 4.x | Token abilities used for role scoping |
| laravel/horizon | 5.x | Requires Redis queue driver |
| laravel/pulse | 1.x | Lightweight prod telemetry |
| laravel/telescope | 5.x | `require-dev` only — never enabled in production |
| spatie/laravel-permission | 6.x | Roles/permissions (`model_has_roles`) |
| spatie/laravel-data | 4.x | DTOs + typed API payloads (optional but recommended) |
| spatie/laravel-query-builder | 6.x | Safe, whitelisted filtering/sorting for admin tables |
| minishlink/web-push | 9.x | VAPID Web Push |
| smalot/pdfparser | 2.x | Primary text extraction (pure PHP, no shell-out) |
| `pdftotext` (poppler-utils) | 24.x | Secondary extractor for layout-heavy PDFs; used via a guarded process runner |
| league/flysystem-aws-s3-v3 | 3.x | S3-compatible storage |
| maatwebsite/excel | 3.x | Queued Excel exports (chunked writer) |
| barryvdh/laravel-dompdf | 3.x | Certificates + PDF reports (queued only) |
| predis/predis **or** phpredis | 2.x / 6.x | Prefer **phpredis** extension in production (lower latency, less memory) |
| pestphp/pest | 3.x | Test runner (PHPUnit 11 underneath) |
| larastan/larastan | 3.x | Static analysis, level 6+ |
| laravel/pint | 1.x | Formatting |
| MySQL | **8.4 LTS** | `utf8mb4_0900_ai_ci`, InnoDB. (MariaDB 11 works but loses window-function parity in a few report queries.) |
| Redis | **7.4** | `maxmemory-policy allkeys-lru` for the cache instance, **`noeviction` for the queue instance** |
| Nginx | 1.26+ | HTTP/2, Brotli, HTTP/3 optional |
| Supervisor | 4.x | Horizon + scheduler process management (or systemd units) |

> **Redis instance separation:** use two logical databases at minimum (cache `db 0`, queue `db 1`) and two *instances*
> in the scalable tier. An LRU eviction policy on the queue database will silently drop jobs.

### 2.2 Frontend

| Component | Version | Note |
|---|---|---|
| Node.js | **22 LTS** (24 acceptable) | Build-time only |
| React + React DOM | **19.1.x** | `useOptimistic`, `useActionState`, improved Suspense used sparingly |
| TypeScript | 5.8+ | `strict: true`, `noUncheckedIndexedAccess: true` |
| Vite | **8.1.x** | Rolldown-based unified bundler; noticeably faster prod builds than Vite 7 |
| Tailwind CSS | **4.2.x** | CSS-first config (`@theme`), `@tailwindcss/vite` plugin — no PostCSS chain needed |
| React Router | 7.x (declarative/data mode, no framework mode) | Route-level `lazy()` code splitting |
| @tanstack/react-query | 5.99.x | + `@tanstack/react-query-devtools` in dev only |
| zustand | 5.x | Global client state only (auth user, theme, network, install prompt) |
| axios | 1.8+ | Interceptors: auth, refresh, retry, idempotency key, cancellation |
| react-hook-form | 7.x | + `@hookform/resolvers` |
| zod | 4.x | Shared schema module; also validates API responses at boundaries in dev |
| lucide-react | latest | Tree-shakeable icon imports only (`import { Lock } from 'lucide-react'`) |
| recharts | 3.x | **Lazily imported** on the progress/report routes only |
| framer-motion | 12.x | Imported via a thin `motion` wrapper that respects `prefers-reduced-motion` |
| idb | 8.x | Typed IndexedDB wrapper |
| workbox-* | 7.x | Via `vite-plugin-pwa` 1.x (`injectManifest` mode — we hand-write the SW) |
| @tanstack/react-virtual | 3.x | Final-test palette + admin question table virtualisation |
| vitest + @testing-library/react | 3.x / 16.x | Unit/component tests |
| msw | 2.x | API mocking in tests |
| k6 | 0.5x | Load testing (Artillery as the fallback if the team prefers YAML) |

### 2.3 Version policy

- Pin majors; allow patch/minor via automated PRs that must pass CI.
- The **service worker must be version-locked to the API contract** — see
  [06-pwa-and-offline.md](./06-pwa-and-offline.md#5-preventing-a-stale-service-worker-from-breaking-the-app): the build stamps
  `APP_BUILD_ID` and `API_CONTRACT_VERSION`; a mismatch reported by the API (`X-Api-Contract` header) forces the SW to
  self-update and reload rather than serve incompatible cached JS.
- `vite-plugin-pwa` runs in `injectManifest` mode: we own `src/workers/service-worker.ts` so that caching rules for
  answers and auth are explicit and auditable, instead of generated.
