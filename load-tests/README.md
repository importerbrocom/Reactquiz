# QuizPath Load Tests

k6 scenarios for performance and correctness testing under load.

## Prerequisites

- [k6](https://k6.io/docs/getting-started/installation/) installed
- API running on staging with production-shaped data
- Load test data seeded: `php artisan quiz:seed-load-test`

## Running

```bash
# Single scenario
k6 run scenarios/s03-daily-quiz-journey.js -e API_BASE_URL=https://staging-api.quizpath.com/api/v1

# All scenarios (CI)
k6 run scenarios/s01-login-storm.js
k6 run scenarios/s02-dashboard-load.js
k6 run scenarios/s03-daily-quiz-journey.js
k6 run scenarios/s04-idempotency-race.js
k6 run scenarios/s07-locked-day-hammering.js
k6 run scenarios/s08-final-test-batch.js
k6 run scenarios/s14-offline-sync-storm.js
k6 run scenarios/s15-soak.js
```

## Data Setup

Place test data in `load-tests/data/`:
- `tokens.json` — array of pre-issued Bearer tokens
- `users.json` — array of `{email, password}` for login tests

## Scenarios

| # | Scenario | VUs | Duration | Proves |
|---|----------|-----|----------|--------|
| S1 | Login storm | 100→1000 | 3m | Auth + bcrypt ≤ 800ms p95 |
| S2 | Dashboard load | 200 rps | 5m | Cache works; ≤ 300ms p95 |
| S3 | Daily quiz journey | 100→1000 | 9m | Full flow; answer ≤ 500ms p95 |
| S4 | Idempotency race | 50 | 30s | One DB row per answer |
| S7 | Locked-day hammering | 500 | 3m | 100% 423/404; no leakage |
| S8 | Final-test batch | 300 | 10m | Batch upsert ≤ 700ms p95 |
| S14 | Offline sync storm | 500 | 3m | No duplicates; ≤ 800ms p95 |
| S15 | Soak | 100 | 4h | No memory growth |
