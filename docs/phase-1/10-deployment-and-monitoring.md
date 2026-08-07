# 10 — Deployment Architecture & Monitoring

## 25. Deployment architecture

Two concrete options are specified. Both run the **same code**; only configuration and infrastructure differ.

### Option A — Low-cost single-server (pilot / < ~500 active students)

```
┌──────────────────────────────────────────────────────────────┐
│ 1 × VPS (Hetzner CPX31 / DO 4 GB / 4 vCPU, 8 GB RAM, 80 GB)  │
│                                                              │
│  Nginx ── app.example.com  → /var/www/quizpath/web/dist      │
│        └─ api.example.com  → PHP-FPM (Laravel public/)       │
│  PHP 8.4-FPM  (pm=dynamic, 12 children)                      │
│  MySQL 8.4    (innodb_buffer_pool_size = 2G)                 │
│  Redis 7.4    (maxmemory 512M, cache db0 / queue db1)        │
│  Supervisor:  horizon (both pools), schedule:work            │
│  Storage:     storage/app/private + nightly rclone → B2/R2   │
│  Cloudflare in front (free tier): TLS, caching, WAF basics   │
│  Backups:     mysqldump + gzip hourly local, 3×/day offsite  │
└──────────────────────────────────────────────────────────────┘
Cost: ~$18–30/month (VPS) + ~$1–3 (object storage/backups) + $0 CDN.
```
Deploy with **Laravel Forge** (~$12/mo, saves days of setup) or a plain `deploy.sh` over SSH. Suitable for the pilot;
migrating to Option B later requires no code change — set `FILESYSTEM_DISK=s3`, point `DB_HOST`/`REDIS_HOST` at managed
services, and add nodes.

Honest limitations: a single point of failure, backups are the only recovery path, PDF processing competes for CPU with
the API (mitigated by `nice 10` on the heavy worker pool), and there is no zero-downtime capacity headroom for a viral
spike.

### Option B — Scalable production (recommended for launch / thousands of students)

```
Cloudflare (DNS, CDN, WAF, HTTP/3, Brotli)
   │
   ├── app.example.com  →  Static hosting of web/dist
   │                       (Cloudflare Pages / S3+CloudFront / Vercel static)
   │                       immutable /assets/* (1y), no-cache index.html + SW
   │
   └── api.example.com  →  Load balancer (health check /up)
                             ├── API node 1  (2 vCPU, 4 GB): Nginx + PHP-FPM 8.4
                             ├── API node 2  (identical, autoscale 2..6)
                             │
                             ├── Worker host A (2 vCPU, 4 GB):
                             │     horizon supervisor-latency
                             │     (critical, notifications, default, mail)
                             ├── Worker host B (2 vCPU, 8 GB):
                             │     horizon supervisor-heavy (pdf, reports)
                             └── Scheduler (tiny 1 vCPU box or A with onOneServer)

  Managed MySQL 8.4 (2 vCPU, 8 GB, automated backups + PITR, optional replica)
  Managed Redis 7.4 (1 GB, persistence on for the queue instance)
  Object storage: S3 / R2 / Spaces — private bucket (pdfs, exports, certificates)
                                     public bucket + CDN (images)
  Sentry (API + browser), Better Stack / UptimeRobot, Horizon + Pulse dashboards
```
Indicative cost: DigitalOcean/Hetzner class — 2 app nodes $24–48, 2 worker hosts $24–48, managed MySQL $60,
managed Redis $15, storage+CDN $5–20 → **~$150–200/month**, scaling with traffic. On AWS the equivalent
(ALB + 2 EC2 + RDS + ElastiCache + S3 + CloudFront) lands nearer $300–450/month.

**Platform choice guidance**

| Platform | Choose when | Watch out for |
|---|---|---|
| Laravel Forge + Hetzner/DO | Best value; team is comfortable with servers; full control | You still own OS patching |
| Laravel Cloud / Vapor | Want managed Laravel with autoscaling; serverless-friendly | Long PDF jobs need the queue tier, not Lambda-style limits; per-request pricing |
| Render / Railway | Smallest ops burden, good DX, easy previews | Higher cost at scale; watch worker pricing for long PDF jobs |
| AWS (ECS/Fargate) | Enterprise requirements, existing AWS estate, compliance | Most complexity; needs a dedicated DevOps owner |
| Cloudflare (Pages + R2 + Workers for edge bits) | Frontend + storage; cheapest global delivery | The Laravel API still needs a container/VM host |

Recommendation: **Cloudflare Pages (frontend) + Laravel Forge on Hetzner (API/workers) + managed MySQL/Redis + R2**.
Lowest cost per unit of reliability for an India/APAC-heavy student audience, and no vendor lock-in.

### Deployment pipeline (both options)

```
git push → GitHub Actions
  ├─ ci.yml            lint (Pint, ESLint), Larastan, tsc --noEmit,
  │                    Pest (parallel, MySQL + Redis services), Vitest,
  │                    bundle-size budget, query-count assertions
  ├─ deploy-api.yml    (on main, after CI)
  │     composer install --no-dev --optimize-autoloader --classmap-authoritative
  │     php artisan down --render=maintenance --retry=15   (only if migrations are breaking)
  │     php artisan migrate --force --step
  │     php artisan config:cache route:cache event:cache view:cache
  │     php artisan storage:link
  │     php artisan horizon:terminate      (workers restart with new code)
  │     php artisan queue:restart
  │     php artisan up
  │     post-deploy smoke: /up, /health/ready, security headers, debug-off probe
  └─ deploy-web.yml    npm ci && npm run build (VITE_APP_BUILD_ID=$GITHUB_SHA)
        upload dist → static host; purge CDN for index.html + sw.js ONLY
        (hashed assets are immutable and must never be purged)
```

**Zero-downtime rules**
- Migrations are additive-first: add columns/indexes, deploy code that tolerates both shapes, backfill via a job, then
  remove the old column in a later release. Never `DROP`/rename in the same deploy as the code change.
- Large index additions use `ALGORITHM=INPLACE, LOCK=NONE` and run in a maintenance window if the table is hot.
- `horizon:terminate` (not `kill`) so in-flight jobs finish.
- Frontend and API deploy independently; the `X-Api-Contract` handshake covers the window where a user has an old bundle.
- Deploy order for breaking API changes: **API first** (backwards compatible), then frontend, then remove compatibility
  shims in a later release.

### Environment matrix

| Env | Purpose | Data | Notes |
|---|---|---|---|
| local | development | seeded demo data | Sail/Docker compose: mysql, redis, mailpit, minio |
| ci | tests | fresh per run | MySQL + Redis services; `array` mail; fake S3 |
| staging | pre-prod verification | anonymised copy or seeded | Identical config to production, `APP_DEBUG=false`, real queues, test VAPID keys |
| production | live | real | Debug off, Telescope absent, Horizon gated, backups + monitoring on |

### Backups & disaster recovery

| Asset | Method | Frequency | Retention | RPO / RTO |
|---|---|---|---|---|
| MySQL | managed automated backup + PITR (Option B) / `mysqldump` + offsite (Option A) | continuous / hourly | 14–30 days | RPO 5 min (B) / 1 h (A); RTO < 1 h |
| Object storage | bucket versioning + lifecycle | continuous | 30 days | RPO ~0 |
| Redis | queue instance persistence (AOF); cache is disposable | — | — | Job loss window < 1 s |
| Secrets | secret manager with versioning | on change | — | — |
| Code | git + tagged releases | per deploy | forever | Rollback = redeploy previous tag |

**Restore drill is mandatory before launch**: restore the latest backup into a scratch database, run
`php artisan quiz:verify-question-integrity`, and confirm a seeded student's day-15 progress is intact. A backup that
has never been restored is not a backup. Documented in `docs/runbooks/restore-drill.md`.

### Server performance configuration (`deploy/`)

```ini
; deploy/php/opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0        ; production only; requires reload on deploy
opcache.save_comments=1              ; Laravel annotations/attributes need this
opcache.jit=tracing
opcache.jit_buffer_size=128M
realpath_cache_size=4096K
realpath_cache_ttl=600
```
```ini
; deploy/php/php-fpm.d/www.conf  (4 vCPU / 4 GB node, ~40 MB per request average)
pm = dynamic
pm.max_children = 24
pm.start_servers = 6
pm.min_spare_servers = 4
pm.max_spare_servers = 10
pm.max_requests = 500
request_terminate_timeout = 30s
; upload pool (separate location) allows 120s and larger bodies
```
```nginx
# deploy/nginx/api.conf (essentials)
client_max_body_size 55m;
keepalive_timeout 65;
http2 on;                                  # http3 on; where supported
gzip on; gzip_comp_level 5; gzip_min_length 512;
gzip_types application/json application/javascript text/css text/plain;
brotli on; brotli_comp_level 5; brotli_types application/json application/javascript text/css;
fastcgi_read_timeout 35s;
fastcgi_buffers 16 16k; fastcgi_buffer_size 32k;
location ~* \.(?:js|css|woff2|avif|webp|png|svg)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}
location = /index.html { add_header Cache-Control "no-cache"; }
location = /sw.js      { add_header Cache-Control "no-cache"; }
```
```ini
; MySQL essentials (managed instances: set equivalents)
innodb_buffer_pool_size = 60-70% of RAM
innodb_flush_log_at_trx_commit = 1        ; durability matters for answers
innodb_log_file_size = 512M
max_connections = 200                     ; sized to FPM children × nodes + workers + 20
slow_query_log = 1; long_query_time = 0.2
```
Log rotation: `logrotate` daily, 14 days, compressed, for Nginx, PHP-FPM and Laravel `daily` channel.

---

## 26. Monitoring strategy

### 1. The four questions monitoring must answer

1. Are students able to submit answers and complete days right now? (business KPI, not just CPU)
2. Is anything queued and not being processed?
3. Did the last deploy make things worse?
4. Is any single student/admin hitting a repeated error we can fix?

### 2. Tooling and what each owns

| Tool | Scope | Notes |
|---|---|---|
| **Laravel Pulse** | API request rates/durations, slow queries, slow jobs, cache hit ratio, exceptions, server CPU/memory | Lightweight enough for production; admin-gated |
| **Laravel Horizon** | Queue throughput, wait times, failed jobs, worker memory | Gated to admin + 2FA |
| **Sentry** (API + browser) | Exceptions, traces, release health, frontend JS errors, service-worker errors | `traces_sample_rate` 0.1 API / 0.05 browser; PII off |
| **web-vitals → Sentry/analytics** | Field LCP, INP, CLS, TTFB by route and device class | Sampled 20% |
| **Better Stack / UptimeRobot** | External uptime on `/up`, `app.example.com`, and a synthetic login+dashboard check every 5 min | Alerts to email + Slack/Telegram |
| **Lighthouse CI** | Per-PR performance/PWA/a11y budgets on 4 key routes | Fails the PR on budget regression |
| **MySQL slow log + `sys` views** | Query regressions, unused indexes | Weekly review ritual |
| **Laravel Telescope** | Local development only | Provider not registered outside `local` |

### 3. Custom application metrics (the ones that actually matter here)

Emitted as Pulse cards / Sentry metrics and reviewed on one dashboard:

| Metric | Alert threshold |
|---|---|
| `quiz.answer.submitted` rate + p95 latency | p95 > 500 ms for 5 min |
| `quiz.answer.failed` count by reason | > 1% of submissions in 10 min |
| `quiz.day.completed` count (hourly, vs 7-day baseline) | drops > 50% vs baseline → likely a functional outage |
| `quiz.locked_access.rejected` (423s) | sudden spike → possible frontend bug or scripted probing |
| `quiz.idempotent_replay` rate | > 5% → a client retry bug |
| `final_test.sync.failed` | any sustained failure |
| `final_test.grading.duration` | p95 > 10 s |
| `offline.outbox.drain_failed` (reported from the client) | > 2% of drains |
| `pdf.import.duration` / `pdf.import.failed` by reason | any `worker_failure`, or > 20% failure rate |
| `push.delivery.failed` ratio by browser | > 20% for a browser family (usually expired subscriptions) |
| `queue.wait` per queue | critical > 30 s, notifications > 5 min, pdf > 1 h |
| `cache.hit_ratio` for `quiz:*` | < 80% → invalidation is too aggressive |
| `auth.login.failed` per IP/account | spike → credential stuffing |
| `db.connections.used / max` | > 80% |
| Certificate/report job failures | any |

### 4. Logging

- Structured JSON logs with a correlation id (`X-Request-Id`, generated at the edge, echoed in responses and attached to
  every log line and Sentry event) so one student's report can be traced across API and workers.
- Levels: `error` for actionable failures, `warning` for degraded-but-handled (push endpoint gone, PDF partial),
  `info` only for domain events worth auditing (`quiz.completed`, `pdf.approved`, `admin.override`).
  **No request/response body logging** in production.
- `activity_logs` is the business audit trail (queryable by admins); application logs are for engineers. Separate on
  purpose.

### 5. Runbooks (written in Phase 10, listed here so they aren't forgotten)

`docs/runbooks/`: `queue-backlog.md`, `answer-submission-failures.md`, `pdf-import-stuck.md`,
`push-delivery-drop.md`, `stale-service-worker.md`, `db-slow-query.md`, `restore-drill.md`,
`secret-rotation.md`, `incident-template.md`. Each: symptoms → dashboards to open → first three actions → escalation →
post-incident notes.

### 6. On-call basics (small team version)

- One primary responder per week; alerts route to a single channel with severity prefixes.
- Only two alert severities: **page** (students cannot submit answers / API down / queue critical stalled) and
  **ticket** (everything else). Alert fatigue is the main failure mode of small-team monitoring, so anything that isn't
  worth waking someone for is a ticket by default.
- Weekly 20-minute review: slow-query top 10, failed jobs, Sentry new issues, Core Web Vitals trend, queue wait p95.
