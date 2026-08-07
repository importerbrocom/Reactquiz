# 13 — Environment Variables

Complete inventory, so Phase 2 starts with a known-good `.env.example`. Values shown are safe placeholders/defaults.

## 1. API (`api/.env`)

### Application
```env
APP_NAME=QuizPath
APP_ENV=production                 # local | testing | staging | production
APP_KEY=base64:CHANGE_ME           # php artisan key:generate
APP_DEBUG=false                    # MUST be false outside local
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com    # used in mail links, password reset, CORS
APP_TIMEZONE=UTC                   # storage timezone; never change after launch
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_MAINTENANCE_DRIVER=cache
API_CONTRACT_VERSION=1             # bumped on client-relevant API changes
```

### Database
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=quizpath
DB_USERNAME=quizpath
DB_PASSWORD=CHANGE_ME
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_0900_ai_ci
DB_READ_HOST=                      # optional read replica (leave empty until S3 stage)
DB_STICKY=true                     # read-after-write consistency; required if a replica is used
```

### Cache / Redis / session / queue
```env
CACHE_STORE=redis
CACHE_PREFIX=qp_cache
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_DOMAIN=.example.com

QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_PREFIX=qp_prod_
REDIS_CACHE_DB=0
REDIS_QUEUE_DB=1
REDIS_DB=2                         # locks, idempotency, rate limits
REDIS_QUEUE_HOST=                  # set to a separate instance in the scalable tier
HORIZON_PREFIX=qp_horizon:
HORIZON_DOMAIN=
HORIZON_PATH=admin/horizon
```

### Authentication
```env
SANCTUM_STATEFUL_DOMAINS=app.example.com
SANCTUM_TOKEN_PREFIX=
ACCESS_TOKEN_TTL_MINUTES=15
REFRESH_TOKEN_TTL_DAYS=30
REFRESH_COOKIE_NAME=qp_refresh
REFRESH_COOKIE_PATH=/api/v1/auth
AUTH_MAX_LOGIN_ATTEMPTS=5
AUTH_LOCKOUT_MINUTES=1             # progressive: 1 → 5 → 30
AUTH_REQUIRE_EMAIL_VERIFICATION=true
AUTH_ADMIN_2FA_REQUIRED=true
ADMIN_IP_ALLOWLIST=                # comma-separated CIDRs; empty = disabled
BCRYPT_ROUNDS=12
```

### CORS
```env
CORS_ALLOWED_ORIGINS=https://app.example.com
CORS_SUPPORTS_CREDENTIALS=true     # only the /auth paths actually use cookies
```

### Filesystem / object storage
```env
FILESYSTEM_DISK=s3                 # local for dev; s3 in production
AWS_ACCESS_KEY_ID=CHANGE_ME
AWS_SECRET_ACCESS_KEY=CHANGE_ME
AWS_DEFAULT_REGION=auto
AWS_BUCKET=quizpath-private
AWS_PUBLIC_BUCKET=quizpath-public
AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com   # R2/Spaces; omit for AWS S3
AWS_USE_PATH_STYLE_ENDPOINT=false
CDN_URL=https://cdn.example.com
SIGNED_URL_TTL_PDF=300             # seconds
SIGNED_URL_TTL_REPORT=900
SIGNED_URL_TTL_CERTIFICATE=900
```

### Quiz domain configuration (defaults; per-course values live in the DB)
```env
QUIZ_DEFAULT_TOTAL_DAYS=30
QUIZ_DEFAULT_DAILY_QUESTIONS=10
QUIZ_DEFAULT_FINAL_TEST_DAY=31
QUIZ_DEFAULT_FINAL_QUESTIONS=300
QUIZ_DEFAULT_PASS_PERCENTAGE=60
QUIZ_DEFAULT_UNLOCK_MODE=immediate    # immediate | next_calendar_day | scheduled
QUIZ_ATTEMPT_ABANDON_HOURS=12
QUIZ_IDEMPOTENCY_TTL_HOURS=24
QUIZ_ANSWER_RATE_LIMIT=60             # per minute per user
QUIZ_SUSPICIOUS_SUBMISSIONS_PER_DAY=25
FINAL_TEST_WINDOW_SIZE=20             # questions per windowed fetch
FINAL_TEST_BATCH_MAX=20               # answers per batch sync
FINAL_TEST_GRACE_SECONDS=60
```

### PDF processing
```env
PDF_MAX_UPLOAD_MB=50
PDF_MAX_PAGES=1500
PDF_PAGE_BATCH_SIZE=10
PDF_MIN_CHARS_PER_PAGE=20             # below this ⇒ treated as scanned
PDF_EXTRACTOR=pdfparser               # pdfparser | pdftotext
PDFTOTEXT_BINARY=/usr/bin/pdftotext
PDF_JOB_TIMEOUT=900
PDF_IMPORT_RETENTION_DAYS=30
PDF_AV_SCAN=false
PDF_OCR_ENABLED=false                 # reserved for the OCR follow-up
```

### Push notifications
```env
VAPID_SUBJECT=mailto:support@example.com
VAPID_PUBLIC_KEY=CHANGE_ME            # exposed via /bootstrap (public by design)
VAPID_PRIVATE_KEY=CHANGE_ME           # secret
PUSH_ENABLED=true
PUSH_BATCH_SIZE=100                   # users per SendPushBatchJob
PUSH_CHUNK_SIZE=500                   # DB chunk size in the dispatcher
PUSH_MAX_PER_USER_PER_DAY=3
PUSH_QUIET_HOURS_START=22:00
PUSH_QUIET_HOURS_END=07:00
PUSH_MAX_FAILURES_BEFORE_EXPIRE=5
PUSH_TTL_SECONDS=86400
```

### Mail
```env
MAIL_MAILER=smtp                      # log for local, smtp/ses/postmark for prod
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=CHANGE_ME
MAIL_PASSWORD=CHANGE_ME
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Reports
```env
REPORT_EXPORT_TTL_HOURS=168           # 7 days
REPORT_MAX_ROWS_SYNC=0                # 0 = always asynchronous
REPORT_CHUNK_SIZE=2000
```

### Logging & monitoring
```env
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning                     # debug only in local
LOG_DAILY_DAYS=14
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_SEND_DEFAULT_PII=false
PULSE_ENABLED=true
PULSE_PATH=admin/pulse
TELESCOPE_ENABLED=false               # provider is not registered outside local anyway
SLOW_QUERY_THRESHOLD_MS=200
SLOW_QUERY_SAMPLE_RATE=0.1
```

### Feature flags
```env
FEATURE_CERTIFICATES=true
FEATURE_PRACTICE_MODE=true
FEATURE_ADMIN_ANNOUNCEMENTS=true
FEATURE_SSE_IMPORT_PROGRESS=true
FEATURE_ACCOUNT_DELETION=true
```

---

## 2. Frontend (`web/.env`)

Only `VITE_`-prefixed values are bundled — **never** put a secret here; everything in this file is public.

```env
VITE_API_BASE_URL=https://api.example.com/api/v1
VITE_APP_NAME=QuizPath
VITE_APP_BUILD_ID=                    # injected by CI (git SHA)
VITE_API_CONTRACT_VERSION=1           # must match the API's API_CONTRACT_VERSION
VITE_VAPID_PUBLIC_KEY=                # optional; normally read from /bootstrap at runtime
VITE_SENTRY_DSN=
VITE_SENTRY_TRACES_SAMPLE_RATE=0.05
VITE_ENABLE_DEVTOOLS=false            # TanStack Query devtools
VITE_ENABLE_PWA=true
VITE_ENABLE_ANALYTICS=false
VITE_DEFAULT_THEME=system             # light | dark | system
VITE_SUPPORT_EMAIL=support@example.com
```

`src/config/env.ts` parses these with Zod and **throws at build time** if a required value is missing or malformed, so a
misconfigured deploy fails in CI rather than in a student's browser.

---

## 3. Local development (`compose.dev.yml` services)

```env
# host ports used by the dev stack
MYSQL_PORT=3306          # mysql:8.4
REDIS_PORT=6379          # redis:7.4-alpine
MAILPIT_HTTP=8025        # mail catcher UI
MINIO_PORT=9000          # S3-compatible local storage
MINIO_CONSOLE=9001
VITE_DEV_PORT=5173
API_DEV_PORT=8000
```

## 4. Secrets inventory (never in git; rotate on the documented schedule)

`APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `AWS_SECRET_ACCESS_KEY`, `AWS_ACCESS_KEY_ID`, `MAIL_PASSWORD`,
`VAPID_PRIVATE_KEY`, `SENTRY_LARAVEL_DSN` (semi-secret), any CI deploy keys.

Rotation notes:
- `APP_KEY` rotation invalidates encrypted cookies/values — requires a documented maintenance step (see
  `docs/runbooks/secret-rotation.md`).
- `VAPID_PRIVATE_KEY` rotation invalidates **all** existing push subscriptions; students must re-subscribe. Only rotate
  with a planned re-subscription prompt.
