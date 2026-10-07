# Implementation Plan: Mobile Store Relaunch

## Overview

Three branches, three pull requests, in this order of landing:

| Branch | Tasks | Depends on |
|---|---|---|
| `sdk-57-upgrade` | 1 | nothing |
| `api-account-deletion` | 3, 4 | nothing — runs in parallel with `sdk-57-upgrade` |
| `ui-relaunch` | 6–14 | `sdk-57-upgrade` merged |

Requirement 2.7 requires the SDK upgrade and the UI relaunch to be two branches and two PRs, so a
regression can be attributed to one or the other. They are **not** interleaved: nothing in tasks 6–14
starts until task 1 is merged. The `api/` work in tasks 3–4 touches no `mobile/` file and has no
dependency on the upgrade.

Language is TypeScript in `mobile/` and PHP in `api/`, fixed by the existing stacks. `api/` work
follows `.kiro/steering/02-engineering-conventions.md`: `final readonly` Actions with one public
`__invoke()`, `ApiResponse` as the only JSON body builder, Pest feature tests whose names read as
claims about the product, `vendor/bin/pint` before committing, PHPStan level 5 clean.

Steering forbids pushing to `main`. Every branch is pushed and opened as a PR with
`gh api repos/importerbrocom/Reactquiz/pulls` — `gh pr create` fails in this environment (GraphQL).

`mobile/node_modules` and `mobile/package-lock.json` do not currently exist. Task 1.1 and 1.2 create
them, in that order, and the lockfile is committed.

Everything that no code can satisfy — Apple enrolment (individual: no D-U-N-S number, ~24–48 hours,
so it no longer paces the schedule, and **deferred by decision until the Android release is approved
and live on Play**), the two hosted URLs, store console work, device testing — is
outside the checkbox list, in
[Owner and external prerequisites](#owner-and-external-prerequisites),
[Release execution](#release-execution--owner-run-not-coding-tasks) and the
[Manual device checklist](#manual-device-checklist-requirement-209).

Android is the sole near-term delivery target. The critical path is: branch `sdk-57-upgrade` →
branches `api-account-deletion` and `ui-relaunch` → Android preview build → manual device checklist →
production `.aab` → Play approval → *then* Apple enrolment → iOS credentials → iOS build → App Store
submission. iOS stays fully in scope as a later phase, and the iOS *configuration* in tasks 1.3 and
1.4 still lands on branch 1 — the deferral is about when the membership is paid for, not a reason to
strip iOS config out of the upgrade.

## Tasks

- [ ] 1. Branch `sdk-57-upgrade` — toolchain, build configuration and steering correction

  - [ ] 1.1 Create the `mobile/` test harness
    - Add `jest-expo`, `@testing-library/react-native` and `fast-check` to `mobile/package.json`
      `devDependencies`
    - Add a `jest` config using the `jest-expo` preset, a `jest.setup.ts` that mocks
      `react-native-safe-area-context` insets and `expo-secure-store`, and `test`, `lint`,
      `typecheck` npm scripts
    - Land this before 1.2 so the upgrade itself is the first thing the harness verifies
    - _Requirements: 20.1_

  - [ ] 1.2 Upgrade `mobile/` to Expo SDK 57 on the New Architecture
    - Audit every dependency for Fabric/TurboModule support first; replace or remove anything without
      it before any UI work begins
    - Derive the version set from `npx expo install --fix` run on this branch (the sanctioned use —
      see 1.8), not from versions transcribed by hand; use `npx expo@latest` explicitly if the machine
      has a mismatched global CLI
    - Move `react-native-reanimated` to v4 with its worklets package, bump
      `react-native-screens`, `react-native-safe-area-context` and `@react-navigation/*` together, and
      add `@react-navigation/drawer`
    - Run a clean `npm install`, commit `mobile/package-lock.json`
    - _Requirements: 2.1, 2.3, 2.4, 2.5_

  - [ ] 1.3 Rewrite identity and version stamping in `mobile/app.config.ts`
    - Set `android.package` and `ios.bundleIdentifier` to `com.incomeinn.ero`, `version` to `1.0.0`,
      `android.versionCode` to `1`, `ios.buildNumber` to the string `'1'`
    - Set `extra.eas.projectId` to the new EAS project's id and delete the stale `quizpath` comment
    - Add a comment stating the local-versioning rule: `appVersionSource: "local"` means both
      `android.versionCode` and `ios.buildNumber` are read from this file and must be bumped per upload
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8_

  - [ ] 1.4 Give `mobile/eas.json` an explicit `ios` block and a `preview` profile
    - Add an explicit `ios` block to the `production` profile rather than relying on platform defaults
    - Keep `cli.appVersionSource: "local"` and no `autoIncrement`
    - Add a `preview` profile producing an installable `.apk` for the device checks
    - _Requirements: 1.9, 2.2_

  - [ ] 1.5 Move status bar and system navigation bar control to the edge-to-edge `SystemBars` API
    - Replace `expo-status-bar` usage with `SystemBars`; edge-to-edge is always on from SDK 54 and
      cannot be opted out of
    - _Requirements: 2.6_

  - [ ]* 1.6 Write the build-configuration assertion tests
    - Assert `android.package` and `ios.bundleIdentifier` are `com.incomeinn.ero`, and that
      `android.versionCode` and `ios.buildNumber` are both present and non-empty
    - Assert the `eas.json` production profile carries an `ios` block
    - This is the regression guard for defect 7 — the absent `ios.buildNumber` that killed every iOS
      build at ~39s
    - _Requirements: 20.5, 1.1, 1.2, 1.4, 1.9_

  - [ ] 1.7 Add the `mobile/` job to CI
    - In `.github/workflows/ci.yml`, run `npm ci`, `tsc --noEmit`, `eslint` and `jest` in `mobile/` on
      any pull request touching `mobile/`
    - `npm ci` is the step that fails when `mobile/package-lock.json` is missing or out of sync with
      `package.json`, which is exactly defect 8 — do not substitute `npm install`
    - _Requirements: 20.2, 20.3_

  - [ ] 1.8 Correct `.kiro/steering/03-mobile-eas-and-play-store.md`
    - Land it on this branch, with the facts it describes, so the file is never stale
    - Add the missing front-matter (`---` / `inclusion: always` / `---`) to match the other three
      steering files
    - Rewrite the `npx expo install --fix` prohibition: the ban existed to stop an *accidental* bump
      from a mismatched global `expo` CLI; a deliberate, planned run on an upgrade branch, committed
      through `package-lock.json`, is now the sanctioned way to pin a version set
    - Replace the superseded facts: SDK 52 → SDK 57 on the New Architecture; `com.ero.quiz` →
      `com.incomeinn.ero` as a new application record with no update path; `versionCode` 7 and
      `version` 1.0.5/1.0.6 → `1` and `1.0.0`; the `ero-quiz` EAS project → the new project for the
      new slug; the open Play problem is not Misleading Claims/icon but Broken Functionality
      (unresponsive UI elements, minor violation) on a listing now abandoned
    - Keep what is still true and hard-won: the committed-lockfile requirement, the gateway remote URL
      keeping its `/github/` path segment, `gh pr create` failing so `gh api` is used, the
      cPanel/Cloudflare/service-worker notes, and the secrets warning
    - _Requirements: 2.4, 2.7, 1.5, 1.6_

- [ ] 2. Checkpoint — SDK upgrade PR
  - Ensure all tests pass, ask the user if questions arise.
  - Push the branch and open the PR with `gh api repos/importerbrocom/Reactquiz/pulls`; never push to
    `main`. Lead the body with why the SDK 52 pin was lifted (API 36 and SDK 52 are mutually
    exclusive) and list the dependency majors that moved.

- [ ] 3. Branch `api-account-deletion` — store-blocking backend work, parallel with task 1

  - [ ] 3.1 Implement the account-deletion Action
    - `final readonly` class in `api/app/Actions/Account/` with one public `__invoke()`, dependencies
      injected via the constructor
    - Resolve the target user through `StudentContext`; take no user identifier from the request
    - Anonymise: `users.name` → "Deleted user", `users.email` → `deleted+{uuid}@invalid`, `phone` and
      `avatar` → null
    - Delete push subscriptions, notifications and onboarding records; retain attempt and answer rows
      in anonymised form so deleting an account cannot rewrite aggregates
    - Revoke every Sanctum token for that user
    - Make the whole operation idempotent — a second run over an already-anonymised user must be a
      no-op, not a second `deleted+{uuid}` email
    - _Requirements: 16.3, 16.4, 16.5, 16.6, 16.7, 16.8_

  - [ ] 3.2 Expose `POST /student/account/delete-request`
    - Add the route in `api/routes/api/v1/student.php` behind `auth:sanctum`, `account` and
      `role:student`
    - Thin controller: resolve through `StudentContext`, queue the anonymisation, return HTTP 202
      through `ApiResponse` — `ApiResponse` stays the only place a top-level JSON body is built
    - Use an existing queue name from `QueueName` rather than a string literal
    - _Requirements: 16.1, 16.2_

  - [ ]* 3.3 Write the Pest feature tests for account deletion
    - "it anonymises the student and keeps their answers" — fields nulled/replaced, attempt rows still
      present and still counted in aggregates
    - "it ignores a user id supplied in the request body" — post another student's id, assert that
      student is untouched and the authenticated student is the one anonymised
    - "it leaves the same state after twenty replays" — call the endpoint 20 times, assert the database
      state is identical to the state after one call
    - "it revokes every token the deleted student held"
    - Use `actingAsStudent()` with an explicit ability list, not `actingAs($user, 'sanctum')`
    - _Requirements: 16.3, 16.4, 16.5, 16.6, 16.7, 16.8, 20.7_

  - [ ] 3.4 Clear the `api/` static-analysis gate
    - `vendor/bin/pest --compact`, `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` clean
      at level 5, `vendor/bin/pint --quiet`
    - If any migration added or changed a column, run
      `php artisan ide-helper:models --write --reset --no-interaction` before PHPStan — the generated
      `@property` annotations are what stop the false "comparison always false" wave
    - _Requirements: 20.8_

- [ ] 4. Demo student seeder — same branch as task 3

  - [ ] 4.1 Implement the demo student seeder
    - New seeder in `api/database/seeders/` producing a student with an active enrolment positioned
      mid-level: completed days, a non-zero streak, recorded mistakes, and a Monthly Test in a
      reachable state
    - Build the graph with the same shapes `tests/Support/QuizScenario.php` uses so the seeded student
      matches the real 300-question level
    - Re-runnable: a second run restores the documented state with no manual editing
    - `refresh()` models after `save()` before relying on database defaults
    - Honour the timezone trap — the streak must be computed in the student's timezone as date
      strings, or the demo account reads as streak 1 to an Indian reviewer
    - _Requirements: 18.1, 18.2_

  - [ ]* 4.2 Write the Pest test for seeder idempotency
    - "it restores the same reviewable state when run twice" — run the seeder twice, assert one
      student, one active enrolment, and identical day/streak/mistake counts
    - _Requirements: 18.2_

- [ ] 5. Checkpoint — account deletion PR
  - Ensure all tests pass, ask the user if questions arise.
  - Open the PR with `gh api`. The body should state that both stores block publication without an
    in-app deletion path, and that attempt rows are deliberately retained anonymised.

- [ ] 6. Branch `ui-relaunch` — provider tree and platform correctness

  Every screen in tasks 7–14 depends on this tree. It lands first and alone.

  - [ ] 6.1 Rebuild the entry module and provider tree
    - `import 'react-native-gesture-handler'` as the **first statement** of `mobile/src/App.tsx`,
      before any other import
    - Mount, outermost first: `GestureHandlerRootView` (`flex: 1`) → `SafeAreaProvider` →
      `RootErrorBoundary` → `QueryClientProvider` → `SessionBootstrapGate` → `NavigationContainer`
    - Keep `registerRootComponent(App)` — `package.json` `main` is `src/App.tsx`
    - Prevent `expo-splash-screen` auto-hide at module scope, which 6.5 depends on
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ]* 6.2 Write the provider-tree tests
    - "it imports gesture-handler before anything else" — assert the entry module's first statement
    - "it mounts the safe area provider above every navigator" — assert the rendered provider order of
      Requirement 3.3
    - _Requirements: 20.6, 3.1, 3.3_

  - [ ] 6.3 Implement the safe-area chrome hooks
    - `useBottomChromeHeight(base)` returns `base + insets.bottom`; `useScreenPadding()` returns
      inset-derived top and bottom padding
    - These are the single source of truth — no screen reads `useSafeAreaInsets()` ad hoc
    - Point the bottom tab bar and every pinned footer (quiz toolbar, test submit bar) at them
    - _Requirements: 4.1, 4.3, 4.6_

  - [ ]* 6.4 Write the property test for safe-area containment
    - **Property 1: Interactive elements stay inside the safe area** — *for any* set of safe-area
      insets and any screen dimensions, every interactive element's hit rect is fully inside the safe
      area, and bottom chrome height exceeds its content height by at least `insets.bottom`
    - Tag: `Feature: mobile-store-relaunch, Property 1: Interactive elements stay inside the safe area`
    - Minimum 100 iterations
    - **Validates: Requirements 4.2, 4.4, 4.5**
    - _Requirements: 4.2, 4.4, 4.5_

  - [ ] 6.5 Implement `SessionBootstrapGate`
    - Replace `AppNavigator`'s `if (isLoading) return null` — the blank, touch-dead screen that reads
      as unresponsive
    - States `loading` / `ready(user | null)` / `degraded`; hide the native splash within 2500 ms in
      every branch; render a boot skeleton with at least one hit-testable element once the soft
      deadline passes
    - No stored refresh token → render Welcome with no network request; 401 → delete the stored token
      then render Welcome; network error or the 8000 ms timeout → render the offline retry screen with
      an enabled Retry and an enabled "Continue signed out"
    - Exactly one of skeleton, degraded screen or navigator on screen at any point after mount
    - _Requirements: 5.1, 5.2, 5.4, 5.5, 5.6, 5.7_

  - [ ]* 6.6 Write the property test for the bootstrap gate
    - **Property 2: The session gate never renders nothing** — *for any* sequence of bootstrap outcomes
      (token present/absent, refresh success/401/timeout/network error) and *any* timing of those
      outcomes, at every point after the soft splash deadline the gate has rendered at least one
      hit-testable element and the native splash is hidden
    - Tag: `Feature: mobile-store-relaunch, Property 2: The session gate never renders nothing`
    - Minimum 100 iterations; drive timing with fake timers
    - **Validates: Requirements 5.1, 5.2, 5.7, 5.8**
    - _Requirements: 5.1, 5.2, 5.7, 5.8_

  - [ ] 6.7 Implement `RootErrorBoundary` and its fallback
    - Fallback renders at least one enabled, hit-testable control; Retry clears the captured error and
      remounts the subtree; captured errors and component stacks go to the configured `onError`
    - _Requirements: 6.1, 6.2, 6.3_

  - [ ]* 6.8 Write the error-boundary tests
    - "it shows a retryable failure instead of a blank screen" — a child that throws renders the
      fallback, not an empty tree
    - "it remounts the subtree when retry is pressed"
    - "it reports the error and component stack to onError"
    - _Requirements: 6.1, 6.2, 6.3_

  - [ ] 6.9 Build the navigation skeleton — drawer above tabs
    - Drawer navigator above the bottom tab navigator so drawer items can target both tabs and stack
      screens; edge swipe opens the drawer
    - Tabs: Home, Exams, Tests, Progress, Profile. Tab bar height from `useBottomChromeHeight`
    - Drawer items exactly: Today's Quiz, Notifications, Settings, Help & Support, Sign Out — no
      Bookmarks, no Mock Tests
    - Every branch of every navigator renders a screen component; no branch yields an empty tree
    - _Requirements: 3.5, 3.6, 5.8, 19.2_

- [ ] 7. API client hardening and error semantics

  - [ ] 7.1 Rework `mobile/src/api/client.ts` and add the `endpoints` module
    - Request timeout 8000 ms (was 15000), applied to the bootstrap refresh as well
    - New `endpoints.ts` holding every path, keyed to `docs/phase-3/student-api.md`; no screen builds a
      URL string
    - Narrow the `ApiErrorCode` union and type the `ApiEnvelope<T>` so screens switch exhaustively
    - `device_name` helper: `` `${Platform.OS} ${Device.modelName ?? 'device'}` `` — platform read at
      runtime, no platform literal in source, sent on login and register
    - Keep the existing single-flight refresh queue; retry a write only with the idempotency token the
      caller supplied, never a freshly generated one
    - `staleTime` 0 on verdict and result queries, and exclude them from every persisted cache
    - _Requirements: 7.1, 7.2, 7.3, 12.4, 13.4, 13.5, 13.11, 5.3_

  - [ ]* 7.2 Write the property test for single-flight refresh
    - **Property 12: Token refresh is single-flight** — *for any* set of concurrent requests that
      receive a 401, exactly one refresh request is issued and every queued request is retried with the
      resulting token or rejected with the refresh error
    - Tag: `Feature: mobile-store-relaunch, Property 12: Token refresh is single-flight`
    - Minimum 100 iterations over generated request counts and arrival orders
    - **Validates: Requirements 13.1, 13.2, 13.3**
    - _Requirements: 13.1, 13.2, 13.3_

  - [ ] 7.3 Route every documented error code to a screen state
    - 423 renders "not yet" copy from the response `meta`, visibly distinct from 403 copy
    - `ONBOARDING_REQUIRED` → onboarding; `QUIZ_NOT_COMPLETE` → back to
      `meta.outstanding_question_ids`; `TEST_ATTEMPT_CLOSED` / `TEST_ATTEMPT_EXPIRED` /
      `ATTEMPT_ALREADY_COMPLETED` → fetch and render the completed state, not an error;
      `ENROLMENT_INACTIVE` → support screen with no further automatic retry
    - Refresh failure → clear the stored token and route to Welcome with a session-expired notice
    - _Requirements: 13.3, 13.6, 13.7, 13.8, 13.9, 13.10_

  - [ ]* 7.4 Write the unit tests for `device_name` and error routing
    - "it names the real device on every platform" — a mocked `Platform.OS` matrix, including
      `Device.modelName` resolving to null
    - "it tells a locked day apart from a forbidden one" — 423 and 403 produce different copy
    - _Requirements: 7.1, 7.2, 7.3, 13.6_

- [ ] 8. Checkpoint — platform correctness
  - Ensure all tests pass, ask the user if questions arise.
  - The nine remediation defects from the design's root-cause table should now each have either a test
    or a line on the manual checklist. Confirm none is unaccounted for before screen work starts.

- [ ] 9. Exam catalogue and content honesty

  - [ ] 9.1 Build the exam catalogue module
    - `ExamCatalogueEntry` with the `LiveExam | ComingSoonExam` union and `stats?: never` on
      `coming_soon`, so a literal count is a compile error rather than a policy incident
    - FMGE is the only `live` entry; its question and level counts come from the Student_API programme
      payload, never from this file
    - `coming_soon` tiles render label, accent, artwork and "Coming soon", with no count and no
      navigation target into a question catalogue or list
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 9.2 Write the property test for coming-soon tiles
    - **Property 7: Coming-soon exams never carry a question count** — *for any* catalogue entry whose
      availability is `coming_soon`, the rendered tile contains no numeral representing a question
      count and no navigation target into a question catalogue
    - Tag: `Feature: mobile-store-relaunch, Property 7: Coming-soon exams never carry a question count`
    - Minimum 100 iterations
    - **Validates: Requirements 8.3, 8.4**
    - _Requirements: 8.3, 8.4_

  - [ ]* 9.3 Write the property test for live counts
    - **Property 8: Live exam counts come from the server** — *for any* live catalogue entry, the
      rendered question count equals the value supplied by the API for that programme, and no literal
      count appears in client source
    - Tag: `Feature: mobile-store-relaunch, Property 8: Live exam counts come from the server`
    - Minimum 100 iterations over generated API payloads
    - **Validates: Requirements 8.2, 8.5, 8.6**
    - _Requirements: 8.2, 8.5, 8.6_

  - [ ] 9.4 Build the exam detail screen
    - Topic-wise breakdown only — no Subjects tab, no Previous Papers tab, no rank
    - Entry points into the Daily Quiz Runner and the Monthly Test Runner
    - Treat exam selection as selection-then-enrolment in one active programme; "My Exams" lists the
      enrolled programme only
    - _Requirements: 19.5, 19.7, 19.8, 8.7_

- [ ] 10. Daily quiz runner

  - [ ] 10.1 Implement the pure queue reducer
    - `advance(state, questionId, verdict)` over `{ pending, current, mastered, exposures }`
    - `correct` → into `mastered`, out of `pending`; `incorrect` → to the **tail** of `pending`, with
      `mastered` unchanged; `exposures` incremented either way; `current'` is `pending'[0]` or null
    - No branch decrements anything — there is no negative marking in this model
    - _Requirements: 9.3, 9.4, 9.5, 9.6_

  - [ ]* 10.2 Write the property test for the queue reducer
    - **Property 3: The daily queue conserves questions and never regresses mastery** — *for any*
      initial question set and *any* sequence of verdicts, after each `advance` the union of
      `mastered`, `pending` and `current` equals the initial set, `mastered` is non-decreasing, and an
      incorrect verdict places the question at the tail of `pending` rather than at its head
    - Tag: `Feature: mobile-store-relaunch, Property 3: The daily queue conserves questions and never regresses mastery`
    - Minimum 100 iterations
    - **Validates: Requirements 9.3, 9.4, 9.5, 9.6**
    - _Requirements: 9.3, 9.4, 9.5, 9.6_

  - [ ]* 10.3 Write the property test for the completion gate
    - **Property 4: Completion requires every question mastered** — *for any* sequence of verdicts, the
      runner requests completion only when `mastered` has the same cardinality as the initial question
      set, never on a separately tracked count
    - Tag: `Feature: mobile-store-relaunch, Property 4: Completion requires every question mastered`
    - Minimum 100 iterations
    - **Validates: Requirements 9.7**
    - _Requirements: 9.7_

  - [ ] 10.4 Build `OptionTile`, the feedback copy builder and the explanation block
    - Tile renders option text only — no letter, index or ordinal; submits `option.key`; ordered by
      `display_position`
    - Every `idle` tile exposes an identical set of style and accessibility properties
    - `feedbackCopy(verdict, correctAnswerText)` is the only sanctioned builder, with no overload
      taking a letter or an index
    - Explanation renders as a single block with a defined empty state — 247 of 305 questions have no
      explanation, so an empty card is the common case
    - Reserve image layout space from the question's stored width and height
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7_

  - [ ]* 10.5 Write the property test for option labelling
    - **Property 5: No option letter is ever rendered or spoken** — *for any* delivered question and
      *any* display order, the rendered tiles contain no letter, index or ordinal label, and *for any*
      verdict the feedback copy contains the correct answer's text and no letter-style reference to a
      position
    - Tag: `Feature: mobile-store-relaunch, Property 5: No option letter is ever rendered or spoken`
    - Minimum 100 iterations; assert over accessibility labels as well as rendered text
    - **Validates: Requirements 10.1, 10.4**
    - _Requirements: 10.1, 10.4_

  - [ ]* 10.6 Write the property test for tile indistinguishability
    - **Property 6: Idle option tiles are visually indistinguishable** — *for any* delivered question,
      every option tile in the `idle` state exposes an identical set of style and accessibility
      properties, so the correct option is not identifiable by shape, size or label
    - Tag: `Feature: mobile-store-relaunch, Property 6: Idle option tiles are visually indistinguishable`
    - Minimum 100 iterations
    - **Validates: Requirements 10.3**
    - _Requirements: 10.3_

  - [ ] 10.7 Wire the Daily Quiz Runner screen
    - Create or resume through `POST /student/days/{day}/attempt`; submit option keys to
      `POST /student/attempts/{uuid}/answers` and render the server verdict; complete through
      `POST /student/attempts/{uuid}/complete` only on 10-of-10
    - One `client_answer_uuid` generated per question exposure, reused unchanged on every retry
    - No countdown, no time-derived submission; progression controls are option taps and the
      post-feedback Continue only — no Previous/Next and no jump to an arbitrary question
    - Toolbar: Calculator only — no Notes, no Report, no Flag
    - Footer height from `useBottomChromeHeight`
    - _Requirements: 9.1, 9.2, 9.7, 9.8, 9.9, 9.10, 12.1, 12.2_

- [ ] 11. Monthly Test runner

  - [ ] 11.1 Implement `useAttemptDeadline(expiresAtIso)`
    - `remainingMs` is a function of `expires_at − now` only; recomputed on foreground and on restart,
      never resumed from a mount-time countdown
    - Latch the expiry submission so it fires exactly once even though the endpoint is idempotent
    - _Requirements: 11.3, 11.4, 11.5_

  - [ ]* 11.2 Write the property test for the deadline
    - **Property 9: The attempt deadline is a function of server time only** — *for any* `expires_at`
      and *any* sequence of foreground/background transitions and app restarts, the remaining time
      equals `expires_at − now` and is never extended by backgrounding
    - Tag: `Feature: mobile-store-relaunch, Property 9: The attempt deadline is a function of server time only`
    - Minimum 100 iterations over generated transition sequences
    - **Validates: Requirements 11.3, 11.4**
    - _Requirements: 11.3, 11.4_

  - [ ] 11.3 Wire the Monthly Test Runner screen
    - Windowed question fetch using `position` and `limit`; navigator grid making every question index
      reachable; option order for a given question identical on every visit within the attempt
    - Answers buffered locally and synced in debounced batches of at most 20, each batch carrying one
      `client_batch_uuid` reused unchanged on retry
    - Duration rendered from the payload's `durationMinutes`; submit on expiry or on demand
    - Toolbar: Calculator and Flag
    - _Requirements: 11.2, 11.6, 11.7, 11.8, 11.9, 11.10, 12.3_

  - [ ] 11.4 Render the ineligible state
    - On HTTP 423 from `GET /student/level-test`, render the "Complete Month N" card listing the day
      numbers from `meta.missing_days` — 423 is "not yet", never "not allowed"
    - _Requirements: 11.1_

  - [ ]* 11.5 Write the property test for free navigation
    - **Property 13: Free navigation exists only in the monthly test** — *for any* daily quiz state
      there is no control that moves to an arbitrary question; *for any* monthly test state every
      question index is reachable and a given question's option order is identical on every visit
      within the attempt
    - Tag: `Feature: mobile-store-relaunch, Property 13: Free navigation exists only in the monthly test`
    - Minimum 100 iterations
    - **Validates: Requirements 9.9, 11.6, 11.7**
    - _Requirements: 9.9, 11.6, 11.7_

  - [ ]* 11.6 Write the property test for timing
    - **Property 10: Only the monthly test is timed** — *for any* daily quiz attempt no countdown is
      rendered and no submission is triggered by elapsed time; *for any* monthly test attempt exactly
      one submission is triggered when the deadline passes
    - Tag: `Feature: mobile-store-relaunch, Property 10: Only the monthly test is timed`
    - Minimum 100 iterations
    - **Validates: Requirements 9.8, 11.5**
    - _Requirements: 9.8, 11.5_

  - [ ]* 11.7 Write the property test for idempotency tokens
    - **Property 11: Retried writes carry a stable idempotency token** — *for any* answer submission
      retried any number of times, the `client_answer_uuid` (single) or `client_batch_uuid` (batch)
      sent is identical across retries, so a replay storm cannot double-record
    - Tag: `Feature: mobile-store-relaunch, Property 11: Retried writes carry a stable idempotency token`
    - Minimum 100 iterations over generated retry counts and failure patterns, covering both runners
    - **Validates: Requirements 12.1, 12.2, 12.3**
    - _Requirements: 12.1, 12.2, 12.3_

- [ ] 12. Checkpoint — both runners
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 13. Dashboard, progress, notifications and account screens

  - [ ] 13.1 Build the Dashboard
    - Primary action rendered from the payload's `next_action`; no client-side progression logic
    - Absent or null statistics render "—", never `0`; `overallAccuracy` clamped to 0–100; day total
      read from `day.total`; locked-day copy rendered verbatim from the server's unlock hint, because
      calendar-day arithmetic belongs on the server where the timezone rules are already right
    - Today's quiz, the Monthly Test state, Progress, notifications and the account deletion path each
      reachable within three taps
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 18.6_

  - [ ]* 13.2 Write the property test for rendered surfaces
    - **Property 14: Rendered surfaces never invent data** — *for any* payload with absent or null
      statistics, the rendered output shows a neutral placeholder rather than a zero or a synthesised
      value
    - Tag: `Feature: mobile-store-relaunch, Property 14: Rendered surfaces never invent data`
    - Minimum 100 iterations over generated dashboard and progress payloads with holes
    - **Validates: Requirements 14.2, 14.5, 14.7**
    - _Requirements: 14.2, 14.5, 14.7_

  - [ ] 13.3 Build the Progress screen
    - Topic-wise breakdown from `GET /student/progress`, as the only breakdown dimension
    - Omit the trend chart entirely if the payload carries no time series — no synthesised points
    - _Requirements: 14.6, 14.7, 19.7_

  - [ ] 13.4 Build the Notification Centre
    - List from `GET /student/notifications`; unread badge from
      `GET /student/notifications/unread-count`; `POST /student/notifications/{id}/read` on open;
      `POST /student/notifications/read-all` on mark-all-read
    - Request no notification runtime permission and register no push subscription in V1 — prompting
      for a permission the backend cannot serve is worse than not prompting
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

  - [ ] 13.5 Build the account screens
    - Profile → Account → deletion, behind a confirmation step stating what is deleted, what is
      retained and the retention period, calling `POST /student/account/delete-request` (task 3.2)
    - Settings links the hosted privacy policy URL — the same URL carried in the store listing
    - _Requirements: 16.9, 17.4_

  - [ ] 13.6 Sweep vocabulary and V1 scope boundaries
    - "Monthly Test" everywhere a level test appears, including the Tests tab and the drawer; "Level N"
      for a level; "programme" only for the 6-month container; the word "course" nowhere
    - Omit, do not disable, every element with no backing endpoint: Bookmarks, Notes, Report, rank,
      Previous Papers, Subjects, the "Skip →" guest affordance, Continue with Google and Continue with
      Apple
    - Sign In offers email and password only; onboarding exits are Sign In and Create Account only
    - Onboarding copy about papers says only that questions are drawn from real FMGE papers — no
      implication of a browsable archive
    - Render a question count only where the current response supplied it
    - _Requirements: 19.1, 19.3, 19.4, 19.6, 8.6, 8.7, 14.8, 18.7_

- [ ] 14. Icon and name parity assets

  - [ ] 14.1 Generate the icon set from one master asset
    - One master produces `icon.png`, `android.adaptiveIcon.foregroundImage` and the 512×512 store
      icon; commit all three plus the master
    - Declare the application name identically in `app.config.ts` and in the store listing copy held in
      the repo
    - The previous failure was a blue blob baked into the `.aab` while the listing showed the chrome
      ERO mark; an icon change requires a new build and cannot be fixed from Play Console
    - _Requirements: 17.1, 17.2, 17.3_

- [ ] 15. Final checkpoint — UI relaunch PR
  - Ensure all tests pass, ask the user if questions arise.
  - Open the PR with `gh api`. The body should lead with the nine remediation defects and name which
    rejection each one caused.

## Notes

- Tasks marked `*` are optional and can be skipped for a faster MVP. Nothing in the dependency graph
  below depends on a `*` task's output, so skipping one does not strand later work — it only removes
  the regression guard. Skipping 1.6, 6.2 or 6.4 removes the guards on exactly the defects that
  produced the two rejections, which is the worst place to save time.
- All 14 design properties are covered: P1 → 6.4, P2 → 6.6, P3 → 10.2, P4 → 10.3, P5 → 10.5,
  P6 → 10.6, P7 → 9.2, P8 → 9.3, P9 → 11.2, P10 → 11.6, P11 → 11.7, P12 → 7.2, P13 → 11.5,
  P14 → 13.2.
- The three API-side invariants the design keeps out of the numbered properties are covered by Pest
  replay tests instead: body-supplied user id ignored (3.3), 20× replay unchanged (3.3), seeder
  idempotent (4.2).
- Requirement 2.2 (`targetSdkVersion` 36) and 17.10 are verified in a build output, not in
  configuration — they live in the release section, not in a checkbox here.

## Owner and external prerequisites

No code in this repository can satisfy these. They are listed so scheduling does not treat them as
work items that can be picked up late.

| Prerequisite | Blocks | Start when |
|---|---|---|
| Apple Developer Program membership — **individual** enrolment: no D-U-N-S number, ~24–48 hours. Accepted cost: the App Store seller is the owner's personal legal name, not "Income Inn Technologies" | Every iOS build. No distribution certificate or provisioning profile can exist for `com.incomeinn.ero` without it | **After the Android release is approved and live on Google Play** — not early. The membership is an annual subscription counting from the purchase date, so paying before iOS work can start burns paid months; and if Android surfaces a dependency or review problem, the owner would rather know first. Deferral is free only because individual enrolment confirms in ~24–48 hours |
| Tidy or unpublish FAST MARRIAGE | **Nothing.** Play Console's account-level Policy status reports no issues with the developer account, so app-level enforcement there has not aggregated upward | Optional housekeeping, no deadline |
| Hosted privacy policy URL, reachable without authentication | Requirement 17.4, the Data safety declaration, both listings, and task 13.5's Settings link | Before task 13.5 |
| Hosted account-deletion page stating what is deleted, what is retained and the retention period | Requirements 16.10, 16.11 on both stores | Before the first submission |
| New Play Console app record for `com.incomeinn.ero`, with developer verification and upload signing key registered | Requirement 17.9 — the first upload | Before the production build |

## Release execution — owner-run, not coding tasks

These are console and device operations, not code. They are deliberately outside the checkbox list.

### Android — runs to completion

1. `eas build --platform android --profile preview` → install the `.apk` on device and work the
   [manual device checklist](#manual-device-checklist-requirement-209).
2. Verify `targetSdkVersion` 36 in the **merged manifest of the build output**, not in
   `app.config.ts` (Requirements 2.2, 17.10).
3. `eas build --platform android --profile production` → `.aab`.
4. Play Console: create the new app record for `com.incomeinn.ero`, register the EAS-managed upload
   signing key and complete developer verification **before the first upload** (17.9).
5. Store listing with the generated 512×512 icon and the name declared identically to the installed
   app (17.2, 17.3); privacy policy URL (17.4).
6. Data safety: email, name, timezone, device label and quiz activity, tied to identity, not shared
   with third parties, deletion available — and agreeing with the privacy policy on every item
   (17.5, 17.6).
7. App access: demo credentials for the seeded student from task 4.1 (17.7).
8. Upload to the internal testing track, re-verify the reviewer journey on the installed artefact
   (18.3–18.7, per 18.8), then promote to production.

   **The 12-tester closed test does not apply here.** Google imposes the
   12-testers-opted-in-for-14-days closed test on personal developer accounts created *after*
   13 November 2023. This account carries enforcement entries dated August 2023, so it predates the
   cutoff and is exempt — internal testing then production, with no fourteen-day tester window.
   Recorded because it removes two weeks a reader would otherwise budget for. Confirm it before
   relying on it: the Production page displays no testing requirement when the exemption holds.
9. Bump `android.versionCode` before every subsequent upload (1.7).

### iOS — dormant by decision until Android is approved, then gated on membership

**Nothing here is executable, and that is deliberate rather than an obstacle.** The owner is not
purchasing the Apple Developer Program membership until the Android app is fixed, submitted and
**approved on Google Play**. The membership is an annual subscription that starts counting from the
purchase date, so paying for it while Android work is in progress spends paid months in which no iOS
build can be produced; and if the Android build turns up a dependency without New Architecture
support or another review rejection, the owner would rather find out before paying. The deferral is
free only because individual enrolment confirms in roughly **24–48 hours** — under the organisation
path, with a D-U-N-S number and multi-week verification, the wait would have had to overlap the
Android work instead.

Every step below also fails without active membership, and no code change can unblock it. All iOS
*configuration* is already done in tasks 1.3 and 1.4 — `ios.bundleIdentifier`, `ios.buildNumber`
`'1'`, and the explicit `ios` block in the `eas.json` production profile — and it stays there. The
deferral is not a reason to strip iOS config out of branch 1: it is nearly free, it keeps the two
platforms from diverging while only Android ships, and it means that once membership is bought,
credentials are the only outstanding item.

Once Android is live and membership is active, in this order:

1. `eas credentials` → iOS → production → let EAS generate and store the distribution certificate and
   the App Store provisioning profile for `com.incomeinn.ero`. This step needs the owner's Apple
   login; the sandbox cannot perform it.
2. `eas build --platform ios --profile production`. If it fails in under 60 seconds it is config or
   credentials, never app code — open the red phase on the build detail page and read the last ~20
   lines (`eas build:list --platform ios --limit 3`, then `eas build:view <BUILD_ID>`).
3. `eas submit --platform ios --profile production`.
4. App Store Connect: attach the build to the `com.incomeinn.ero` app record, fill listing and App
   Privacy from the same collection set as Data safety, add review notes with the demo credentials, a
   statement that launch content is FMGE only, and an explanation of what a "Coming soon" tile is
   (17.7, 17.8). TestFlight is how a production build gets tested on device.
5. Bump `ios.buildNumber` before every subsequent upload (1.8).

## Manual device checklist (Requirement 20.9)

No unit test can prove a touch reaches a view behind the system navigation bar, that an installed
icon matches a listing icon, or that store metadata is correct. Complete this on the preview `.apk`
before each upload and record the result. These are device observations, not coding tasks, so they
carry no checkboxes — copy them into the release record for the submission being prepared.

1. Every bottom tab item activates on a single tap, Android 15 **and** 16, **gesture** navigation
   (Requirement 4.4).
2. Every bottom tab item activates on a single tap, Android 15 **and** 16, **3-button** navigation
   (Requirement 4.5).
3. Every pinned footer control — quiz toolbar, test submit bar — is tappable under both navigation
   modes (4.2, 4.3).
4. An edge swipe opens the drawer (3.6).
5. Cold start on a connection throttled to 3G: the splash clears within 2.5 s and every screen reached
   shows content or a retry affordance (5.1, 18.4, 18.5).
6. Airplane mode cold start with a stored token lands on the offline retry screen with both Retry and
   "Continue signed out" enabled (5.4).
7. Fresh install, App access demo credentials entered on Sign In, Dashboard renders with a primary
   action from `next_action` (18.3).
8. Today's quiz, Monthly Test, Progress, notifications and the account deletion path each reachable
   within three taps of the Dashboard (18.6).
9. No question count appears anywhere except where the API supplied it; every non-FMGE tile reads
   "Coming soon" with no number (8.3, 18.7).
10. The installed launcher icon is visibly identical to the store listing icon, and the installed app
    name matches the listing name (17.2, 17.3).
11. Re-verified on the production artefact being uploaded, not only on the preview build (18.8,
    17.10).

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.3", "1.4", "1.8", "3.1"] },
    { "id": 1, "tasks": ["1.2", "1.5", "3.2", "4.1"] },
    { "id": 2, "tasks": ["1.6", "1.7", "3.3", "3.4", "4.2", "6.1"] },
    { "id": 3, "tasks": ["6.2", "6.3", "6.7", "7.1"] },
    { "id": 4, "tasks": ["6.4", "6.5", "6.8", "7.3", "9.1"] },
    { "id": 5, "tasks": ["6.6", "6.9", "7.2", "7.4", "9.2", "10.1"] },
    { "id": 6, "tasks": ["9.3", "9.4", "10.2", "10.3", "10.4", "11.1"] },
    { "id": 7, "tasks": ["10.5", "10.6", "10.7", "11.2", "11.3", "13.1"] },
    { "id": 8, "tasks": ["11.4", "11.5", "11.6", "11.7", "13.2", "13.3", "13.4", "13.5"] },
    { "id": 9, "tasks": ["13.6", "14.1"] }
  ]
}
```
