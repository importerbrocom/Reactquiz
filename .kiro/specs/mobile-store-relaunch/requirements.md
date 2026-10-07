# Requirements Document

## Introduction

ERO relaunches as a new application record on Google Play and the App Store under the package and
bundle id `com.incomeinn.ero`, with a new UI built from the owner's mockups, on Expo SDK 57 targeting
Android API 36. These requirements are derived from the approved
[design document](./design.md) and are scoped to the V1 the design settled.

The document exists to close a specific gap. Two rejections — *Broken functionality / unresponsive UI
elements* and *Misleading Claims / store listing mismatch* — happened because nobody had ever written
down, in a form a test could check, **what a Play reviewer must be able to do on a fresh install**.
Requirement 18 is that statement. Requirements 3–6 are the defects that made the first rejection
true; Requirement 8 is the one that made the second true.

Two things are deliberately *not* requirements and appear only as prerequisites, because no code can
satisfy them: Apple Developer Program enrolment — individual enrolment, so ~24–48 hours and no
D-U-N-S number, which keeps it off the critical path, and which the owner has chosen to **purchase
only after the Android release is approved and live on Google Play** — and the two hosted URLs. The FAST MARRIAGE
policy notices are neither: Play Console reports no account-level issues, so that app is optional
housekeeping rather than a gate on this submission.

Vocabulary follows `.kiro/steering/01-locked-decisions.md`: **level** = one month (30 daily quizzes +
the month-end test), **programme** = the 6-month container, **cycle** = one full pass. The
student-facing label for a level test is **Monthly Test**. The word *course* appears nowhere.

## Glossary

- **Mobile_App**: the Expo/React Native application in `mobile/`, as installed on a device.
- **Build_Configuration**: `mobile/app.config.ts` plus `mobile/eas.json`, the inputs EAS reads.
- **Entry_Module**: the module named by `mobile/package.json` `main` (`mobile/src/App.tsx`), which
  calls `registerRootComponent`.
- **Session_Bootstrap_Gate**: the component that resolves the stored session before any navigator
  mounts, replacing `AppNavigator`'s `if (isLoading) return null`.
- **Root_Error_Boundary**: the React error boundary mounted above `QueryClientProvider`.
- **Safe_Area_Chrome**: `useBottomChromeHeight` and `useScreenPadding`, the single source of truth
  for inset-derived spacing.
- **Exam_Catalogue**: the module holding exam marketing copy and the only module permitted to render
  a question count.
- **Daily_Quiz_Runner**: the screen that plays one day's 10 questions under `requeue_at_end`.
- **Monthly_Test_Runner**: the screen that plays a level test (300 questions, free navigation,
  3-hour deadline).
- **Option_Tile**: the component rendering one answer option.
- **Dashboard**: the Home screen rendered from `GET /student/dashboard`.
- **Notification_Centre**: the in-app notification list and its unread badge.
- **API_Client**: `mobile/src/api/client.ts` — the axios instance, its interceptors and its
  single-flight refresh queue.
- **Student_API**: the Laravel API under `api/routes/api/v1/student.php`.
- **Account_Deletion_Action**: the new `api/app/Actions/` class performing the queued anonymisation.
- **Demo_Student_Seeder**: the seeder producing the mid-programme student whose credentials are given
  to store reviewers.
- **Release_Package**: the uploadable artefacts and store metadata for one submission — the `.aab` or
  `.ipa`, the icon set, the listing, the Data safety declaration and the App access entry.
- **Release_Process**: the human-executed sequence in the design's release pipeline, including the
  manual device checklist.
- **CI_Pipeline**: the GitHub Actions workflows in `.github/workflows/`.

## Decisions carried in from the design

Recorded here so no requirement below reads as negotiable.

| Decision | Source |
|---|---|
| SDK 52 pin lifted; SDK 57 with the New Architecture | design, Reversal 1 — API 36 and SDK 52 are mutually exclusive |
| iOS is in scope but gated on membership, not on code | design, Reversal 2 |
| Guest mode: **demo credentials only**; the mockup's "Skip →" is removed | design, guest-mode decision (owner, Option B) |
| Previous Papers tab dropped in V1 | design, conflict 6 — provenance is free-text `questions.source` |
| No A/B/C/D option badges; feedback names answer text | ADR 003 |
| No timer on the daily quiz; 3 hours on the Monthly Test | owner-locked |
| FMGE is the only `live` exam; all others "Coming soon" with no counts | owner-locked |

## Requirements

### Requirement 1: Application identity and version stamping

**User Story:** As the owner, I want ERO to ship as a new application record with correct version
stamping on both stores, so that uploads are accepted and the abandoned `com.ero.quiz` listing is
never touched again.

#### Acceptance Criteria

1. THE Build_Configuration SHALL declare `android.package` as `com.incomeinn.ero`.
2. THE Build_Configuration SHALL declare `ios.bundleIdentifier` as `com.incomeinn.ero`.
3. THE Build_Configuration SHALL declare `version` as `1.0.0` and `android.versionCode` as `1`.
4. THE Build_Configuration SHALL declare `ios.buildNumber` as the string `'1'`.
5. THE Build_Configuration SHALL declare `extra.eas.projectId` as the identifier of the EAS project
   created for the new slug.
6. THE Build_Configuration SHALL describe the local-versioning rule in a comment and SHALL reference
   only the EAS project in use, with the `quizpath` reference removed.
7. WHEN a Google Play upload is prepared, THE Build_Configuration SHALL carry an
   `android.versionCode` greater than every value previously uploaded for `com.incomeinn.ero`.
8. WHEN an App Store upload is prepared, THE Build_Configuration SHALL carry an `ios.buildNumber`
   greater than every value previously uploaded for `com.incomeinn.ero`.
9. THE `eas.json` production profile SHALL declare an explicit `ios` block rather than relying on
   platform defaults.

### Requirement 2: Expo SDK 57 upgrade on the New Architecture

**User Story:** As the owner, I want the app built on Expo SDK 57, so that it can target Android API
36 and remain eligible for Play uploads.

#### Acceptance Criteria

1. THE Mobile_App SHALL build against Expo SDK 57 with the New Architecture enabled.
2. THE Android build output SHALL report `targetSdkVersion` 36 in the merged manifest.
3. THE repository SHALL contain `mobile/package-lock.json`.
4. WHEN dependency versions are selected for the upgrade, THE Release_Process SHALL derive them from
   `npx expo install --fix` executed on the upgrade branch and commit the resulting
   `mobile/package-lock.json`.
5. IF a dependency has no New Architecture support, THEN THE Release_Process SHALL replace or remove
   that dependency before UI work starts on the relaunch branch.
6. THE Mobile_App SHALL control the status bar and the system navigation bar through the
   edge-to-edge `SystemBars` API.
7. THE Release_Process SHALL deliver the SDK upgrade and the UI relaunch as two separate branches and
   two separate pull requests.

### Requirement 3: Provider tree and gesture root

**User Story:** As a student, I want drawer swipes and every screen's controls to work, so that the
app responds to touch everywhere.

#### Acceptance Criteria

1. THE Entry_Module SHALL place `import 'react-native-gesture-handler'` as its first statement.
2. THE Mobile_App SHALL mount `GestureHandlerRootView` with `flex: 1` as the outermost component.
3. THE Mobile_App SHALL mount providers in the order `GestureHandlerRootView`, `SafeAreaProvider`,
   `RootErrorBoundary`, `QueryClientProvider`, `Session_Bootstrap_Gate`, `NavigationContainer`.
4. THE Mobile_App SHALL mount `SafeAreaProvider` above every navigator.
5. THE Mobile_App SHALL mount the drawer navigator above the bottom tab navigator, so that drawer
   items can target both tabs and stack screens.
6. WHEN a swipe gesture is performed from the screen edge, THE Mobile_App SHALL open the drawer.

### Requirement 4: Every interactive element sits inside the safe area

**User Story:** As a Play reviewer, I want every visible control to respond to a tap, so that the app
does not read as broken.

#### Acceptance Criteria

1. THE Safe_Area_Chrome SHALL compute bottom chrome height as the supplied base height plus
   `insets.bottom`.
2. THE Mobile_App SHALL render every interactive element with its hit rect inside the safe area,
   such that each element's bottom edge is at or above `screenHeight − insets.bottom`.
3. THE bottom tab bar and every pinned footer SHALL derive height from `useBottomChromeHeight`.
4. WHILE the Mobile_App runs on Android 15 or Android 16 under gesture navigation, THE bottom tab bar
   SHALL render above the system navigation bar and every tab item SHALL activate on a single tap.
5. WHILE the Mobile_App runs on Android 15 or Android 16 under 3-button navigation, THE bottom tab bar
   SHALL render above the system navigation bar and every tab item SHALL activate on a single tap.
6. WHERE a screen draws to the display edges, THE screen SHALL obtain top and bottom padding from
   `useScreenPadding`.

### Requirement 5: The session gate always renders something interactive

**User Story:** As a Play reviewer on a throttled network, I want the app to show me a screen I can
act on, so that a slow network does not look like an unresponsive app.

#### Acceptance Criteria

1. THE Session_Bootstrap_Gate SHALL hide the native splash screen within 2500 ms of mount in every
   bootstrap branch.
2. WHILE the bootstrap status is `loading` and 2500 ms have elapsed since mount, THE
   Session_Bootstrap_Gate SHALL render a boot skeleton containing at least one hit-testable element.
3. THE API_Client SHALL abort the bootstrap refresh request after 8000 ms.
4. IF the bootstrap refresh request fails with a network error or the 8000 ms timeout, THEN THE
   Session_Bootstrap_Gate SHALL render the offline retry screen carrying an enabled Retry control and
   an enabled "Continue signed out" control.
5. IF the bootstrap refresh request returns HTTP 401, THEN THE Session_Bootstrap_Gate SHALL delete
   the stored refresh token and render the Welcome screen.
6. WHEN no refresh token is present in secure storage, THE Session_Bootstrap_Gate SHALL render the
   Welcome screen without issuing a network request.
7. THE Session_Bootstrap_Gate SHALL render exactly one of the boot skeleton, the offline retry
   screen, or the navigator at every point after mount.
8. THE Mobile_App SHALL render a screen component in every branch of every navigator and gate, with
   no branch yielding an empty tree.

### Requirement 6: Uncaught render errors produce a visible, recoverable screen

**User Story:** As a student, I want a failure to tell me it failed and let me retry, so that a crash
is not a blank screen.

#### Acceptance Criteria

1. WHEN a descendant component throws during render, THE Root_Error_Boundary SHALL render a fallback
   containing at least one enabled, hit-testable control.
2. WHEN the Retry control in the fallback is activated, THE Root_Error_Boundary SHALL clear the
   captured error and remount the subtree.
3. WHEN an error is captured, THE Root_Error_Boundary SHALL pass the error and the component stack to
   the configured `onError` handler.

### Requirement 7: Honest device labelling

**User Story:** As the owner, I want session records to name the real device, so that session
management and security logs are meaningful.

#### Acceptance Criteria

1. WHEN the Mobile_App calls login or register, THE API_Client SHALL send `device_name` as the
   current `Platform.OS` value followed by `Device.modelName`.
2. IF `Device.modelName` resolves to null, THEN THE API_Client SHALL send the literal `device` in
   place of the model name.
3. THE API_Client SHALL derive the platform segment of `device_name` from `Platform.OS` at runtime,
   with no platform literal written into source.

### Requirement 8: Content honesty — counts come from the API or do not exist

**User Story:** As the owner, I want the app to claim only the content it has, so that a second
Misleading Claims citation cannot happen — account standing is clean today, and every further
violation compounds against it.

#### Acceptance Criteria

1. THE Exam_Catalogue SHALL mark FMGE as the only entry with availability `live` in V1.
2. WHERE a catalogue entry has availability `live`, THE Exam_Catalogue SHALL source the rendered
   question count and level count from the Student_API programme payload for that entry.
3. WHERE a catalogue entry has availability `coming_soon`, THE rendered tile SHALL present the exam
   label, accent, artwork and the words "Coming soon" and SHALL present no question count.
4. WHERE a catalogue entry has availability `coming_soon`, THE rendered tile SHALL expose no
   navigation target into a question catalogue or question list.
5. THE Exam_Catalogue source SHALL contain no literal question count, enforced by a `coming_soon`
   entry carrying `stats` being a compile error.
6. THE Mobile_App SHALL render a question count only where the Student_API supplied that value in the
   current response.
7. THE Mobile_App SHALL use the label "Monthly Test" for a level test on every surface, including the
   Tests tab and the drawer.

### Requirement 9: Daily quiz under requeue-at-end

**User Story:** As a student, I want a wrong answer to come back after the others, so that I recall
the answer rather than echo it.

#### Acceptance Criteria

1. WHEN the student opens today's quiz, THE Daily_Quiz_Runner SHALL create or resume the attempt
   through `POST /student/days/{day}/attempt`.
2. WHEN the student selects an option, THE Daily_Quiz_Runner SHALL submit the option key to
   `POST /student/attempts/{uuid}/answers` and render the verdict returned by the Student_API.
3. IF the returned verdict is `incorrect`, THEN THE Daily_Quiz_Runner SHALL place that question at
   the tail of the pending queue.
4. WHEN the returned verdict is `correct`, THE Daily_Quiz_Runner SHALL add that question to the
   mastered set and remove that question from the pending queue.
5. THE Daily_Quiz_Runner SHALL hold the union of the mastered set, the pending queue and the current
   question equal to the delivered question set after every queue transition.
6. THE Daily_Quiz_Runner SHALL keep the mastered set non-decreasing across every queue transition.
7. THE Daily_Quiz_Runner SHALL call `POST /student/attempts/{uuid}/complete` only when the cardinality
   of the mastered set equals the cardinality of the delivered question set.
8. THE Daily_Quiz_Runner SHALL render no countdown and SHALL derive no submission from elapsed time.
9. THE Daily_Quiz_Runner SHALL offer option selection and a post-feedback Continue control as the
   only progression controls, with no control that moves to an arbitrary question.
10. THE Daily_Quiz_Runner toolbar SHALL contain Calculator as its only item.

### Requirement 10: Question and option rendering carries no answer signal

**User Story:** As the owner, I want position and shape to carry no information about the answer, so
that students memorise medicine rather than option positions.

#### Acceptance Criteria

1. THE Option_Tile SHALL render the option text and SHALL render no letter, index or ordinal label.
2. THE Option_Tile SHALL submit `option.key` on activation and SHALL order tiles by
   `display_position`.
3. WHILE an Option_Tile is in the `idle` state, THE Option_Tile SHALL expose a set of style and
   accessibility properties identical to every other `idle` tile for that question.
4. THE feedback copy builder SHALL accept a verdict and the correct answer text as its only inputs,
   and THE rendered feedback SHALL contain the correct answer text.
5. IF a question carries no explanation, THEN THE Mobile_App SHALL render the defined empty state for
   the explanation block.
6. WHERE a question carries an image with stored width and height, THE Mobile_App SHALL reserve
   layout space from those stored dimensions.
7. THE Mobile_App SHALL render `questions.explanation` as a single block.

### Requirement 11: Monthly Test — free navigation on a server-owned deadline

**User Story:** As a student, I want to move freely through the 300 questions within three hours, so
that the month-end test behaves like the exam it prepares me for.

#### Acceptance Criteria

1. IF `GET /student/level-test` returns HTTP 423, THEN THE Monthly_Test_Runner SHALL render a
   "Complete Month N" card listing the day numbers in `meta.missing_days`.
2. WHEN an attempt is open, THE Monthly_Test_Runner SHALL fetch questions in windows using the
   `position` and `limit` parameters.
3. THE Monthly_Test_Runner SHALL compute remaining time as the attempt's `expires_at` minus the
   current time.
4. WHEN the Mobile_App returns to the foreground or restarts, THE Monthly_Test_Runner SHALL recompute
   remaining time from `expires_at`.
5. WHEN the computed remaining time reaches zero, THE Monthly_Test_Runner SHALL call the submit
   endpoint exactly once.
6. THE Monthly_Test_Runner SHALL make every question index reachable through the navigator grid.
7. WHILE a single attempt is open, THE Monthly_Test_Runner SHALL render a given question's options in
   an identical order on every visit.
8. THE Monthly_Test_Runner SHALL buffer answers locally and sync them in batches of at most 20
   answers.
9. THE Monthly_Test_Runner SHALL render the attempt duration from the payload's `durationMinutes`.
10. THE Monthly_Test_Runner toolbar SHALL contain Calculator and Flag as its items.

### Requirement 12: Retried writes cannot double-record

**User Story:** As a student on a flaky connection, I want a retried answer to count once, so that a
dropped response does not corrupt my progress.

#### Acceptance Criteria

1. THE Daily_Quiz_Runner SHALL generate one `client_answer_uuid` per question exposure.
2. WHEN an answer submission is retried, THE Daily_Quiz_Runner SHALL send the `client_answer_uuid`
   generated for that exposure, unchanged.
3. WHEN a Monthly Test answer batch is retried, THE Monthly_Test_Runner SHALL send the
   `client_batch_uuid` generated for that batch, unchanged.
4. THE API_Client SHALL retry a write request only with the idempotency token supplied by the caller.

### Requirement 13: API client behaviour and error semantics

**User Story:** As a student, I want every API failure to land me on a screen that explains the state,
so that no failure becomes a dead end.

#### Acceptance Criteria

1. WHEN two or more in-flight requests receive HTTP 401, THE API_Client SHALL issue exactly one
   refresh request.
2. WHEN the refresh request succeeds, THE API_Client SHALL retry every queued request with the
   resulting access token.
3. IF the refresh request fails, THEN THE API_Client SHALL reject every queued request with the
   refresh error, delete the stored refresh token, and route the Mobile_App to the Welcome screen
   carrying a session-expired notice.
4. THE API_Client SHALL abort any request after 8000 ms.
5. THE Mobile_App SHALL resolve every request path from the single `endpoints` module keyed to
   `docs/phase-3/student-api.md`.
6. WHEN a response carries HTTP 423, THE Mobile_App SHALL render "not yet" copy taken from the
   response `meta`, distinct from the copy used for HTTP 403.
7. WHEN `errors.code` is `ONBOARDING_REQUIRED`, THE Mobile_App SHALL navigate to onboarding.
8. WHEN `errors.code` is `QUIZ_NOT_COMPLETE`, THE Mobile_App SHALL return the student to the
   questions listed in `meta.outstanding_question_ids`.
9. WHEN `errors.code` is `TEST_ATTEMPT_CLOSED`, `TEST_ATTEMPT_EXPIRED` or
   `ATTEMPT_ALREADY_COMPLETED`, THE Mobile_App SHALL fetch and render the completed state rather than
   an error state.
10. WHEN `errors.code` is `ENROLMENT_INACTIVE`, THE Mobile_App SHALL render the support screen and
    SHALL issue no further automatic retry of that request.
11. THE Mobile_App SHALL configure verdict and result queries with `staleTime` 0 and SHALL exclude
    those queries from every persisted cache.

### Requirement 14: Rendered surfaces report only what the server supplied

**User Story:** As a student, I want blank where data is missing, so that the app never tells me
something untrue about my own progress.

#### Acceptance Criteria

1. THE Dashboard SHALL render the primary action from the payload's `next_action` value.
2. IF a statistic is absent or null in the payload, THEN THE Dashboard SHALL render the placeholder
   "—" in place of that statistic.
3. THE Dashboard SHALL clamp `overallAccuracy` to the range 0 to 100 for rendering.
4. THE Dashboard SHALL read the day total from the payload's `day.total` field.
5. WHILE a day is locked, THE Dashboard SHALL render the server-supplied unlock hint verbatim.
6. THE Progress screen SHALL render the topic-wise breakdown from `GET /student/progress`.
7. IF `GET /student/progress` supplies no time series, THEN THE Progress screen SHALL omit the trend
   chart.
8. THE Mobile_App SHALL render level identity as "Level N", with "programme" reserved for the
   6-month container and the word "course" absent from every surface.

### Requirement 15: In-app notifications without a permission prompt

**User Story:** As a student, I want to see my notifications in the app, so that I know about unlocks
and reminders without the app asking for a permission it cannot use.

#### Acceptance Criteria

1. THE Notification_Centre SHALL list notifications from `GET /student/notifications`.
2. THE Notification_Centre SHALL render the unread badge count from
   `GET /student/notifications/unread-count`.
3. WHEN a notification is opened, THE Notification_Centre SHALL call
   `POST /student/notifications/{id}/read`.
4. WHEN the student activates "mark all read", THE Notification_Centre SHALL call
   `POST /student/notifications/read-all`.
5. THE Mobile_App SHALL request no notification runtime permission in V1.
6. THE Mobile_App SHALL register no push subscription in V1.

### Requirement 16: Account deletion — API, app path and public page

**User Story:** As a student, I want to delete my account from inside the app, so that I control my
own data — and as the owner, I need this because both stores block publication without it.

#### Acceptance Criteria

1. THE Student_API SHALL expose `POST /student/account/delete-request` behind the `auth:sanctum`,
   `account`, and `role:student` middleware.
2. WHEN a deletion request is accepted, THE Student_API SHALL queue the anonymisation and respond
   HTTP 202 through `ApiResponse`.
3. THE Account_Deletion_Action SHALL resolve the target user through `StudentContext` and SHALL
   ignore any user identifier present in the request body.
4. WHEN anonymisation runs, THE Account_Deletion_Action SHALL set `users.name` to "Deleted user", set
   `users.email` to `deleted+{uuid}@invalid`, and set `phone` and `avatar` to null.
5. WHEN anonymisation runs, THE Account_Deletion_Action SHALL delete the user's push subscriptions,
   notifications and onboarding records.
6. WHEN anonymisation runs, THE Account_Deletion_Action SHALL retain the user's quiz attempt and
   answer rows in anonymised form.
7. WHEN anonymisation completes, THE Student_API SHALL revoke every Sanctum token belonging to that
   user.
8. WHEN the deletion request is submitted 20 times for the same user, THE Account_Deletion_Action
   SHALL leave the resulting database state identical to the state after one submission.
9. THE Mobile_App SHALL expose the deletion path under Profile → Account behind a confirmation step
   that states what is deleted, what is retained, and the retention period.
10. THE Release_Package SHALL reference a publicly reachable account-deletion page that states what is
    deleted, what is retained, and the retention period.
11. THE privacy policy SHALL describe the deletion path and the retention of anonymised attempt rows.

### Requirement 17: Store compliance deliverables

**User Story:** As the owner, I want every store-side obligation satisfied at upload time, so that
review turns on the app rather than on paperwork.

#### Acceptance Criteria

1. THE Release_Package SHALL generate `icon.png`, `android.adaptiveIcon.foregroundImage` and the
   512×512 store icon from one master asset.
2. WHEN the uploaded artefact is installed, THE launcher icon SHALL be visibly identical to the icon
   published in the store listing.
3. THE Release_Package SHALL declare the application name identically in the store listing and in the
   installed application.
4. THE Release_Package SHALL carry a privacy policy URL that resolves without authentication, and THE
   Mobile_App SHALL link that same URL under Settings.
5. THE Data safety declaration SHALL enumerate the collected set as email, name, timezone, device
   label and quiz activity, declared as tied to identity and not shared with third parties.
6. THE Data safety declaration SHALL state that account deletion is available and SHALL agree with
   the privacy policy on every declared item.
7. THE Release_Package SHALL carry demo credentials for the seeded demo student in Play Console's App
   access section and in App Store Connect's review notes.
8. THE App Store Connect review notes SHALL state that launch content is FMGE only and SHALL explain
   what a "Coming soon" tile is.
9. THE Release_Process SHALL register the new application's upload signing key and complete developer
   verification before the first upload.
10. THE Release_Process SHALL verify `targetSdkVersion` 36 in the build output of the artefact being
    uploaded rather than in the configuration alone.

### Requirement 18: Reviewer journey on a fresh install

**User Story:** As a Play or App Review reviewer, I want to install the app, sign in with the supplied
credentials, and reach working content, so that I can assess the app rather than report it as broken.

This requirement is the one that was missing. Each criterion below corresponds to a rejection reason
already received.

#### Acceptance Criteria

1. THE Demo_Student_Seeder SHALL create a student with an active enrolment positioned mid-level, with
   completed days, a non-zero streak, recorded mistakes, and a reachable Monthly Test state.
2. THE Demo_Student_Seeder SHALL be re-runnable to restore that state without manual editing.
3. WHEN the Mobile_App is launched for the first time after install and the App access demo
   credentials are submitted on the Sign In screen, THE Mobile_App SHALL render the Dashboard with a
   primary action derived from `next_action`.
4. WHILE the reviewer journey from launch to Dashboard is performed, THE Mobile_App SHALL render a
   screen containing at least one hit-testable element at every step, with the native splash hidden
   within 2500 ms of launch.
5. WHILE the reviewer journey is performed on a connection throttled to 3G speeds, THE Mobile_App
   SHALL render either content or a retry affordance on every screen reached.
6. WHEN the Dashboard is on screen, THE Mobile_App SHALL make today's quiz, the Monthly Test state,
   Progress, the Notification_Centre, and the account deletion path each reachable within three taps.
7. WHEN any screen of the Mobile_App is rendered, THE Mobile_App SHALL present a question count only
   where the Student_API supplied that count.
8. THE Release_Process SHALL re-verify criteria 3 through 7 on an installed production artefact before
   each store submission.

### Requirement 19: V1 scope boundaries — no control without a backing endpoint

**User Story:** As the owner, I want deferred features to be absent rather than disabled, so that a
reviewer finds no dead control and no unfulfilled claim.

#### Acceptance Criteria

1. IF a mockup element has no backing endpoint in the Student_API, THEN THE Mobile_App SHALL omit that
   element from the rendered tree.
2. THE Mobile_App drawer SHALL contain exactly the items Today's Quiz, Notifications, Settings,
   Help & Support and Sign Out.
3. THE Sign In screen SHALL offer email and password as its only authentication inputs.
4. THE onboarding flow SHALL offer Sign In and Create Account as its only exits.
5. THE exam detail screen SHALL render the Topic-wise breakdown and SHALL present no Subjects tab and
   no Previous Papers tab.
6. WHERE onboarding copy refers to examination papers, THE copy SHALL state only that questions are
   drawn from real FMGE papers.
7. THE Progress screen SHALL present Topic-wise as its only breakdown dimension.
8. THE Mobile_App SHALL treat exam selection as selection followed by enrolment in one active
   programme, and THE "My Exams" surface SHALL list the enrolled programme only.

### Requirement 20: Test infrastructure, static analysis and CI

**User Story:** As a developer, I want the defects fixed in this relaunch to be caught by the build
if they return, so that the next submission does not re-litigate this one.

#### Acceptance Criteria

1. THE `mobile` package SHALL declare `jest-expo`, `@testing-library/react-native` and `fast-check`
   as development dependencies.
2. WHEN a pull request touching `mobile/` is opened, THE CI_Pipeline SHALL run `npm ci`,
   `tsc --noEmit`, `eslint` and `jest` in `mobile/`.
3. IF `mobile/package-lock.json` is absent or inconsistent with `mobile/package.json`, THEN THE
   CI_Pipeline SHALL fail the run.
4. THE property-based tests SHALL execute at least 100 iterations each and SHALL carry the tag
   `Feature: mobile-store-relaunch, Property N: {property text}`.
5. THE Mobile_App test suite SHALL assert that the Build_Configuration declares package
   `com.incomeinn.ero`, `android.versionCode`, and `ios.buildNumber`.
6. THE Mobile_App test suite SHALL assert the provider order of Requirement 3.3 and the first
   statement of the Entry_Module.
7. THE Student_API test suite SHALL cover the account-deletion route with Pest feature tests,
   including a replay of the request 20 times asserting unchanged state.
8. WHEN `vendor/bin/phpstan analyse` runs at level 5 over `api/`, THE analysis SHALL report no
   errors.
9. WHERE a behaviour cannot be asserted in an automated test — edge-to-edge tap behaviour on a device,
   installed-versus-listing icon parity, and store metadata — THE Release_Process SHALL record the
   check on a manual device checklist completed before upload.

## External prerequisites

Not requirements: no code in this repository can satisfy them, and each one blocks a different part of
the release. They are listed so that scheduling does not treat them as work items that can be picked
up late.

| Prerequisite | Blocks | Why no code can satisfy it |
|---|---|---|
| Apple Developer Program membership — **individual** enrolment: no D-U-N-S number, ~24–48 hours to confirm, with the owner's personal legal name shown as the App Store seller instead of "Income Inn Technologies". **Deferred by decision until the Android release is approved and live on Play** | Every iOS build — no distribution certificate and no provisioning profile can exist for `com.incomeinn.ero` without it | Requires the owner's identity and payment; the sandbox cannot enrol |
| A hosted privacy policy URL reachable without authentication | Requirement 17.4, the Data safety declaration, and both listings | Hosting and content publication outside this repository |
| A hosted account-deletion page | Requirement 16.10 on both stores | Same |

Apple enrolment is **not** started early. The owner purchases the membership only after the Android
app is fixed, submitted and approved on Google Play. The membership is an annual subscription that
begins counting from the date of purchase, so buying it while Android work is still in flight spends
paid months in which no iOS build can be produced; and if the Android build surfaces a problem — a
dependency without New Architecture support, or another review rejection — the owner would rather
know before paying. The deferral is free precisely *because* individual enrolment confirms inside a
day or two; it would have been the wrong call under the organisation path, where a D-U-N-S number and
multi-week verification would have had to overlap the Android work.

Android is therefore the sole near-term delivery target, and the critical path runs: SDK upgrade
branch → the API and UI branches → Android preview build → manual device checklist → production
`.aab` → Play approval → *then* Apple enrolment → iOS credentials → iOS build → App Store submission.
iOS remains fully in scope as a later phase, and the iOS *configuration* (Requirements 1.2, 1.4 and
1.9) still lands with the SDK upgrade: it is nearly free, it keeps the two platforms from diverging
while only one is shipping, and it means credentials are the only outstanding item when membership is
eventually bought.

Tidying or unpublishing the **FAST MARRIAGE** application is worth doing but blocks nothing here —
Play Console's account-level Policy status reports no issues with the developer account, so app-level
enforcement there has not aggregated into account-level action.

## Out of scope for V1

Deferred to V2. Each is designed *around*, not disabled — Requirement 19.1 is the general rule.

| Deferred | Why |
|---|---|
| Guest preview endpoint | Owner chose demo credentials; it would be the system's only unauthenticated path into the question bank |
| Remote push delivery and the Expo push transport fix | `PushSubscriptionController` hardcodes `WebPush` and demands web-push key material; also needs FCM credentials and an APNs key |
| Subjects taxonomy and Subject-wise practice | No subjects table; questions carry free-text `topic` and are scoped to `level_id` |
| Bookmarks | No bookmarks table |
| Notes | No notes storage |
| Report / flag content | No endpoint |
| Rank and leaderboard | No ranking anywhere in the backend; meaningless at current cohort size |
| Structured Key Points / High-Yield Fact fields | `questions.explanation` is one `text` column |
| Previous Papers | Provenance is free-text `questions.source`; revisitable once modelled |
| Google and Apple third-party sign-in | Sanctum email/password only; offering no third-party login also avoids triggering Apple's Sign in with Apple rule |
| Concurrent multi-exam enrolment | Unverified in the backend; onboarding enrols one exam and one programme |
