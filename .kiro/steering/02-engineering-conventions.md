---
inclusion: always
---

# Engineering conventions and environment

## Commands

Run everything from `api/`.

```bash
vendor/bin/pest --compact                                   # 369 tests at end of Phase 3
vendor/bin/pest tests/Feature/Quiz --compact                # one directory
vendor/bin/phpstan analyse --memory-limit=1G --no-progress   # level 5, must be clean
vendor/bin/pint --quiet                                     # formats; run before committing
php artisan migrate:fresh --seed
php artisan quiz:verify-question-integrity

export COMPOSER_ALLOW_SUPERUSER=1                           # required before any composer command
```

**After any migration that adds or changes a column**, regenerate the model annotations:

```bash
php artisan ide-helper:models --write --reset --no-interaction
```

PHPStan depends on those generated `@property` annotations to know that cast columns are enums and
not strings. Skipping this brings back a wave of false "comparison always false" errors — which is
exactly why Phase 2 had seven broad suppressions, since removed.

## Code layout

- **Actions** (`app/Actions/`) — single-purpose write operations, `final readonly`, one public
  `__invoke()`. Dependencies injected via constructor.
- **Services** (`app/Services/`) — reusable read logic and decisions. No writes where an Action
  would be clearer.
- **DTOs** (`app/DTOs/`) — `final readonly` with named constructors, a `toArray()` for the wire,
  and deliberately **no field** for anything the client must not be able to assert.
- **Controllers** stay thin: resolve through `StudentContext`, call one Action or Service, wrap in
  `ApiResponse`.
- `ApiResponse` is the **only** place that builds a top-level JSON body. Use
  `ApiResponse::sensitive()` for anything that must not be cached.
- Exceptions extend `ApiException` and carry a status, an `errorCode`, and a `meta` array the
  client can act on (outstanding question ids, missing days, unlock hints).

Every write path that a flaky phone might retry must be idempotent, and there should be a test
proving it by calling it 5–20 times.

## Comment style

Comments explain **why**, and especially **what was rejected and what would break**. They do not
narrate what the code plainly does. Existing files set the standard — match it. A comment that
would still be true if the code were wrong is not worth writing.

## Testing philosophy

Pest, feature tests over unit tests, one behaviour per test, and **test names that read as claims
about the product** ("it counts two days finished in one sitting as one day of streak"), not as
descriptions of methods.

Test the **property**, not the implementation. Examples worth imitating:

- Statement-count budgets, so an N+1 or an accidental aggregate on a hot path fails the build.
- Distribution assertions — the correct answer must reach all four slots over repeated exposures.
- Structural leakage checks — every option must expose an *identical* set of fields, so the right
  one is not identifiable by shape.
- Replay storms — call the same write 20 times and assert the state is unchanged.

Do not weaken a failing assertion to make it pass. Two real product bugs in Phase 3 were found by
tests that at first looked simply wrong, and one test *was* wrong in a way that hid a bug
(`DB::getQueryLog()` accumulates — call `DB::flushQueryLog()` between measurements).

`tests/Support/QuizScenario.php` builds a full programme/level/enrolment graph:
`QuizScenario::make(levels:, days:, perDay:)` for small fast cases, `::realistic()` for the real
300-question shape. Helpers: `completeDay`, `completeDaysUpTo`, `completeAllDays`, `startAttempt`,
`enrolInLevel`, `dailyQuiz`, `level`, `levelTest`.

`actingAsStudent()` / `actingAsAdmin()` use `Sanctum::actingAs` with an explicit ability list on
purpose — `$this->actingAs($user, 'sanctum')` mints a token granting every ability and would hide
a broken `abilities:` middleware.

## Sandbox and test-environment quirks

Hard-won; each of these cost real debugging time.

- **No MySQL, no Redis.** Tests use SQLite in-memory. `database/database.sqlite` exists for
  artisan commands. `/tmp` is not writable for a database file.
- `HAVING` on a subquery fails on SQLite — use a `whereRaw` correlated subquery.
- `UPDATE ... JOIN` is MySQL-only. Use correlated subqueries so the same SQL runs on both.
- Carbon 3 returns **float** from `diffInSeconds()` — cast to `int`.
- Time travel is `$this->travel(...)`, not the bare `travel()` helper.
- Pest's `toContain()` takes **more needles** as extra arguments, not a failure message.
- Laravel memoises the guard's user across requests within one test. Call
  `app()['auth']->forgetGuards()` before acting as a second user.
- `DB::getQueryLog()` accumulates from the first `enableQueryLog()`; `disableQueryLog()` does not
  clear it. Use `DB::flushQueryLog()`.
- Factories use **static `$sequence` counters**, not `fake()->unique()`, which exhausts its pool at
  300+ rows (LevelFactory, QuestionFactory, ExamCategoryFactory, ProgrammeFactory).
- A freshly `save()`d model does **not** contain database defaults. `refresh()` before returning it
  from an Action, or the caller sees `null` where it expects `0`.
- `users.timezone` is `NOT NULL`.

## Git and PRs

- Remote must be `https://github.com/importerbrocom/Reactquiz.git`; git rewrites it to the gateway
  URL, which **must keep its `/github/` path segment** or the push fails with
  `HTTP 400 Missing header field, please provide ProviderId`.
- `gh pr create` fails here (GraphQL). Use the REST API:

  ```bash
  gh api repos/importerbrocom/Reactquiz/pulls \
    -f title="..." -f head=<branch> -f base=main -F body=@/tmp/pr.md --jq '.html_url'
  ```

- Never push straight to `main`. Branch, push, open a PR, and give the owner the link — he reviews
  on GitHub because he has no filesystem access.
- Commit messages and PR bodies lead with **why** and call out bugs found and decisions made.
  He reads them.

## Timezones — a standing trap

The app runs in **UTC**; the students are in **India**. Anything involving a *calendar day*
(streaks, daily unlocks, "opens tomorrow") must be computed in the **student's** timezone and
compared as **date strings**, never as timestamps.

A `date` column reads back as midnight UTC. Comparing that to midnight in Asia/Kolkata differs by
5.5 hours and silently fails. This exact bug reset every Indian student's streak to 1 every day
and was invisible until a test used a case where the local and server dates genuinely disagree.
`tests/Feature/Progress/StreakTest.php` is the regression guard — keep it.

Unlock times must also anchor to **when something was finished**, not to `now()`. Computing
"tomorrow" from `now()` makes the unlock recede by a day on every request so it never arrives.
