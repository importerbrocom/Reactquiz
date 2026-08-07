# QuizPath API

Laravel 13 · PHP 8.4 · MySQL 8.4 · Redis 7.4 · Sanctum (token mode)

## Local setup

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate

# Option A — SQLite, zero infrastructure
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
touch database/database.sqlite

# Option B — MySQL (matches production)
#   create the database, then set DB_* in .env

php artisan migrate:fresh --seed
php artisan serve
```

Seeded admin: `admin@quizpath.test` / `ChangeMe!2026` — **change it immediately.**
The seed also creates the FMGE programme with six levels and 300 questions in Level 1.

## Commands

```bash
composer check              # pint --test + phpstan + pest   (run before pushing)
composer test               # test suite
composer lint               # apply formatting
composer analyse            # static analysis (level 5, clean)
composer fresh              # migrate:fresh --seed
composer verify:questions   # nightly integrity check, also runnable by hand
```

## Architecture notes

- **Every API response** uses one envelope: `{success, message, data, errors, meta}`.
  Built only by `App\Support\ApiResponse`.
- **Authentication** is token-based so the same endpoints serve the web PWA and the
  future React Native app. Access tokens last 15 minutes; refresh tokens rotate on
  every use and reuse of a rotated token revokes the whole family.
- **Answers never leave the server early.** `Question::$hidden` conceals
  `correct_option`, `correct_answer_text` and `explanation`, and
  `QuestionForStudentResource` is a positive whitelist. `AnswerLeakageTest` sweeps
  every non-admin GET route to prove it.
- **Four authorisation gates** on admin routes: `auth:sanctum` → `abilities:admin`
  → `role:admin` (DB-backed, so a revoked role takes effect immediately) → a Policy
  call in the action.
- **Idempotency** is middleware plus a database unique constraint, so a replayed
  offline answer is recorded exactly once even if the cache has been flushed.
- **Strict Eloquent** outside production (`shouldBeStrict`): an N+1 or a typo'd
  attribute fails the test suite instead of shipping.

## Directory map

```
app/
├── Console/Commands/       quiz:verify-question-integrity
├── Enums/                  23 string-backed enums, mirrored on the frontend
├── Exceptions/             ApiException + 10 typed failures with error codes
├── Http/
│   ├── Controllers/Api/V1/ Auth/ · Admin/ · Shared/
│   ├── Middleware/         role · account · idempotent · security headers · contract version
│   ├── Requests/           one Form Request per write endpoint
│   └── Resources/          QuestionForStudentResource (answer-free) vs QuestionAdminResource
├── Models/                 31 models
├── Notifications/          queued verification + password reset
├── Policies/               auto-discovered
├── Services/
│   ├── Auth/               TokenService · LoginThrottleService
│   └── Support/            ActivityLogger
└── Support/                ApiResponse · CacheKeys
```
