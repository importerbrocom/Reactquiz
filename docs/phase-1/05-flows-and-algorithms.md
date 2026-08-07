# 05 — User Flows & Core Algorithms

---

## 11. Student user flow

```
Register / Login
   │  email verification (soft gate: can browse, cannot start a quiz until verified*)
   ▼
Onboarding (resumable — onboarding_preferences.step is the resume pointer)
   1 Welcome            → logo, name, one-line value prop, "Get Started"
   2 Exam category      → cards from GET /exam-categories        (PUT .../category)
   3 Course             → cards from GET /exam-categories/{s}/courses (PUT .../course)
   4 Preferences        → reminder time, notification permission**, language, timezone, study goal
   5 Confirmation       → selections + course config + final-test summary → "Start Course"
   ▼  POST /student/onboarding/complete  (transactional: enrolment + streak row + day-1 availability)
Home dashboard  ── bottom nav: Home │ Quiz │ Progress │ Notifications │ Profile
   │
   ├─ "Start Today's Quiz" / "Continue Quiz"
   │      ▼
   │   Quiz day screen (Day N)
   │      GET .../quiz-days/{day}  →  423 if locked (shows lock card + unlock hint)
   │      POST .../attempts        →  start or resume (idempotent)
   │      loop over 10 questions, one per screen:
   │          select option → Submit
   │             ├─ correct   → ✓ state, "mastered", auto-advance after ~450 ms
   │             └─ incorrect → ✗ on choice, ✓ on correct option, correct answer text,
   │                             explanation, "Try again" (question stays in retry set)
   │          progress bar = mastered/10, not answered/10
   │      when mastered == 10 → POST .../complete
   │      ▼
   │   Day complete screen: 10/10, time, streak, "Day N+1 unlocked" (or unlock-tomorrow notice)
   │
   ├─ Review Mistakes → filters → practice session (does not affect unlocking)
   ├─ Progress → headline numbers, calendar, lazily-loaded charts, weak/strong topics
   ├─ Notifications → inbox, reminder-time settings, per-device push management
   └─ Profile → name, avatar, timezone, language, theme, devices, logout / logout-all
   ▼
After Day 30 completed at 10/10
   Final test unlocks (push + in-app banner + dashboard CTA)
   ▼
Final test (Day 31)
   eligibility check → start/resume attempt (server-side expires_at)
   windowed question fetch (20 at a time) + virtualised palette
   answer → saved locally instantly → debounced batch sync every ~5 s / 10 answers
   mark for review → palette shows flagged
   Review screen → unanswered + flagged summary → confirm dialog → Submit
   ▼
Result: score, %, pass/fail, correct/incorrect/unanswered, time, topic/category/difficulty
        breakdown, wrong-answer review with explanations, certificate (if enabled)
```
\* configurable: `quiz.require_verified_email` (default **true** — prevents throwaway-account abuse of PDF content).
\** notification permission is requested on a user gesture in step 4, never on page load (browsers block that, and it
tanks opt-in rates).

### Edge paths that must work

| Situation | Behaviour |
|---|---|
| Closes the app mid-quiz | Reopen → dashboard shows "Continue Quiz"; attempt resumes at `current_position` with mastered/retry sets intact |
| Refreshes browser mid-question | Local state rebuilt from IndexedDB + server resume payload; no answer lost |
| Goes offline mid-quiz | Answers queue in IndexedDB, UI shows "Saved offline · will sync"; **no correctness feedback is fabricated** — the question is marked "pending" and the student may move on, then feedback appears when sync completes |
| Goes offline on the last question | Completion cannot be granted offline; the app explains that the day will be completed when back online, then auto-completes on sync |
| Two devices, same day | Both resume the same attempt; unique constraints make double-submission a no-op; server state wins on conflict |
| Session expires mid-quiz | 401 → silent refresh → retry; if refresh fails, answers stay in the outbox, login screen preserves the return path |
| Locked day URL typed manually | 423 → lock screen with the exact requirement ("Complete Day 6 with 10/10 to unlock Day 7") |

---

## 12. Admin user flow

```
Admin login (separate route, same Sanctum flow, role=admin) → 2FA-ready (see 08)
   ▼
Dashboard: cards (students, active today, categories, courses, questions, quizzes completed,
           final tests, avg score, completion rate) + charts + filters (date/course/category)
           + import health + push delivery + hardest questions
   │
   ├─ Categories: list/search/filter → create (title, slug auto, description, icon, order)
   │              → activate/deactivate → reorder (drag) → soft delete
   │
   ├─ Courses: create under a category → configure quiz rules (30/10/31/300, pass %, timer,
   │           attempts, shuffle, pause, unlock mode, retry mode, reminders)
   │           → Readiness check → Generate daily quizzes → Publish (status=active)
   │
   ├─ Questions: table (cursor, fulltext search, filters, bulk select)
   │           → add/edit (text, image, 4 options, correct option, explanation, difficulty,
   │             topic, tags, source) → preview as student
   │           → bulk: activate/deactivate/delete/assign day/move day
   │           → duplicates report → export
   │
   ├─ PDF import:  upload → (202) → progress bar (poll/SSE)
   │           → review table sorted by parser confidence (worst first)
   │           → fix incomplete items inline (raw text shown side-by-side)
   │           → assign category/course/day (bulk)
   │           → approve selected → bulk insert → import audit entry
   │           → failure states with actionable reasons (encrypted / no text layer / corrupt)
   │
   ├─ Students: search → detail → progress → attempts → suspend/activate → revoke sessions
   │           → manual enrol / transfer / reopen a day (reason required, audited)
   │
   ├─ Notifications: compose announcement → audience (all/course/category/inactive)
   │           → schedule or send → batch progress → delivery stats → prune dead endpoints
   │
   └─ Reports: choose type + filters + format → queued → notified when ready
               → download via signed expiring URL
```

Every destructive or student-affecting admin action writes an `activity_logs` row with `event`, `subject`, `properties`
(before/after diff for edits) and, for support overrides, a mandatory `reason`.

---

## 13. Quiz-unlocking algorithm

Owned by `App\Services\Quiz\QuizUnlockService`. It is the **only** place that decides access, and it is called by both
the policy and the middleware, so no controller can bypass it.

### 13.1 Definitions

- A day is **completed** iff a `quiz_attempts` row exists for `(user, daily_quiz)` with
  `status = completed` **and** `score = required_count` (i.e. 10/10). Nothing else counts.
- `enrollment.highest_unlocked_day` is a **cache/denormalisation** of the rule below, maintained transactionally at
  completion time. It is used for cheap reads (dashboards, day lists) but **never** as the sole authority for access:
  `assertUnlocked()` re-derives from completion data. This protects against a corrupted counter granting access.

### 13.2 Algorithm

```
function isDayUnlocked(enrollment, dayNumber) -> UnlockDecision:

    course = enrollment.course

    # 0. hard bounds
    if dayNumber < 1 or dayNumber > course.total_quiz_days:
        if dayNumber == course.final_test_day: return decideFinalTest(enrollment)
        return DENY(reason: 'day_out_of_range')

    # 1. enrolment must be usable
    if enrollment.status not in ('active',):        return DENY('enrolment_inactive')
    if course.status != 'active':                   return DENY('course_inactive')

    # 2. day 1 is always the entry point (business rule 3)
    if dayNumber == 1:
        if course.start_rule == 'fixed_date' and today < course.starts_on:
            return DENY('course_not_started', unlocks_at: course.starts_on)
        return ALLOW

    # 3. the previous day must be completed at full score (business rules 10, 11)
    previous = completionRecord(enrollment, dayNumber - 1)     # 1 indexed query
    if previous is null or previous.score < previous.required_count:
        return DENY('previous_day_incomplete',
                    hint: "Complete Day {dayNumber-1} with {required}/{required} to unlock Day {dayNumber}",
                    blocking_day: dayNumber - 1)

    # 4. course-level pacing mode
    switch course.unlock_mode:
        case 'immediate':
            return ALLOW

        case 'next_calendar_day':
            # student's local date, not server date
            completedLocalDate = previous.completed_at.inTimezone(user.timezone).toDate()
            todayLocal         = now().inTimezone(user.timezone).toDate()
            if todayLocal > completedLocalDate: return ALLOW
            return DENY('available_tomorrow',
                        unlocks_at: startOfNextLocalDay(completedLocalDate, user.timezone))

        case 'scheduled':
            # day N becomes available on course start + (N-1) days
            base = course.start_rule == 'fixed_date' ? course.starts_on : enrollment.started_at.toDate()
            availableOn = base.addDays(dayNumber - 1)
            if todayLocal >= availableOn: return ALLOW
            return DENY('scheduled_later', unlocks_at: availableOn)
```

`completionRecord()` is a single indexed query against
`quiz_attempts(user_id, course_id, day_number)` filtered on `status='completed'`, selecting only
`score, required_count, completed_at`. For the 31-row day list, the service loads **all** completion records for the
enrolment in **one** query and evaluates the loop in memory — never one query per day.

### 13.3 Final-test decision

```
function decideFinalTest(enrollment) -> UnlockDecision:
    course = enrollment.course
    completedFullScoreDays = count(quiz_attempts
                                   where user, course, status='completed', score = required_count,
                                   distinct day_number, day_number <= course.total_quiz_days)
    if completedFullScoreDays < course.total_quiz_days:
        return DENY('final_test_not_eligible',
                    missing_days: allDays - completedDays,
                    hint: "Complete all {total} daily quizzes with full marks")
    if attemptsUsed(enrollment) >= course.final_test_attempt_limit:
        return DENY('attempt_limit_reached')
    return ALLOW
```

### 13.4 Enforcement layers (defence in depth)

1. **Route middleware** `EnsureQuizDayUnlocked` — resolves `{course}`/`{day}` from the route, calls the service,
   throws `QuizDayLockedException` → 423 before the controller runs.
2. **Policy** `QuizAccessPolicy@viewDay|startAttempt|submitAnswer|complete` — protects paths reached by attempt id
   rather than day number (e.g. `POST /quiz-attempts/{attempt}/answers` verifies the attempt's day is still unlocked and
   owned by the user).
3. **Service re-check inside the transaction** at completion time — the authoritative check.
4. **Frontend** shows lock UI from the day-list payload — purely cosmetic; removing it changes nothing server-side.

A test asserts that hitting every student quiz route for a locked day, with a valid token, returns 423 — including by
attempt id, by day number, and via the batch endpoint.

---

## 14. Answer-evaluation algorithm

Owned by `AnswerEvaluationService` + `SubmitAnswerAction` (the Action owns the transaction).

```
POST /student/quiz-attempts/{attempt}/answers
     Idempotency-Key: <uuid>
     { question_id, selected_option, client_answer_uuid, time_spent_ms, answered_at, was_offline }

1  validate  (Form Request): selected_option ∈ [a..d]; question_id exists;
             time_spent_ms 0..3_600_000; answered_at within [attempt.started_at - 5m, now + 2m]
2  authorise (Policy): attempt.user_id == auth.id
                       attempt.status == 'in_progress'
                       question ∈ daily_quiz_questions(attempt.daily_quiz_id)     ← blocks answer injection
                       QuizUnlockService::assertUnlocked(attempt.day_number)
3  idempotency: Redis SETNX; on replay return the stored response

4  DB::transaction:
       # a) lock the attempt row — serialises concurrent submissions for this attempt
       attempt = SELECT ... FROM quiz_attempts WHERE id = ? FOR UPDATE

       if attempt.status == 'completed': throw AttemptAlreadyCompletedException (409)

       # b) load the question's grading data by primary key (no join: correct_option is denormalised)
       q = SELECT id, correct_option, correct_answer_text, explanation FROM questions WHERE id = ?

       # c) evaluate on the server — the client's opinion is never read
       isCorrect = (normalise(selected_option) === q.correct_option)

       # d) append to the immutable log (business rule 19)
       #    duplicate client_answer_uuid → unique violation → caught → return existing evaluation
       state = SELECT * FROM quiz_attempt_questions WHERE quiz_attempt_id=? AND question_id=? FOR UPDATE
       submissionNumber = state.submission_count + 1
       INSERT INTO quiz_attempt_answers (..., submission_number, is_correct, recorded_at=now())

       # e) update per-question state (upsert, one row)
       state.submission_count += 1
       state.selected_option   = selected_option
       state.time_spent_seconds += round(time_spent_ms/1000)
       if isCorrect:
            if state.state != 'mastered':
                 state.state = 'mastered'; state.mastered_at = now()
                 attempt.mastered_count += 1                # only ever incremented once per question
            attempt.correct_submissions += 1
       else:
            state.state = 'retry_required'
            state.wrong_count += 1
            attempt.wrong_submissions += 1
            if submissionNumber > 1: attempt.retry_count += 1

       # f) lifetime progress (drives mistakes, weak topics, practice)
       UPSERT student_question_progress (user, course, question)
              attempts += 1
              correct_count / wrong_count += 1
              first_attempt_correct = (submissionNumber == 1 ? isCorrect : keep)
              is_mastered = is_mastered OR isCorrect
              mastered_at = coalesce(mastered_at, isCorrect ? now() : null)
              last_selected_option, last_attempted_at, total_time_seconds

       # g) attempt bookkeeping
       attempt.last_activity_at = now()
       attempt.time_spent_seconds += round(time_spent_ms/1000)
       attempt.current_position = position_of(question) (if moving forward)
       save attempt

5  build response:
       {
         is_correct,
         question_id,
         submission_number,
         # revealed ONLY now, and only when needed:
         correct_option:      isCorrect && !course.show_answers_on_correct ? null : q.correct_option,
         correct_answer_text: same condition,
         explanation:         same condition,
         retry_required:      !isCorrect,
         progress: { mastered_count, required_count, retry_required_question_ids[] },
         can_complete: mastered_count == required_count
       }
   headers: Cache-Control: no-store, X-Idempotent-Replay?: true
6  store response in the idempotency ledger (24 h)
```

**Query cost:** 1 `SELECT ... FOR UPDATE` (attempt) + 1 PK select (question) + 1 `SELECT ... FOR UPDATE` (state)
+ 1 insert + 1 update + 1 upsert + 1 update = **7 statements, all single-row, all by primary/unique key.** No joins,
no aggregates, no loops.

### 14.1 Batch (offline drain) variant

`POST /answers/batch` accepts ≤ 20 items. It locks the attempt **once**, then loops the items *in PHP* while issuing only
single-row statements — critically, it does **not** re-open a transaction per item and does not re-read the attempt.
Items are processed in `answered_at` order so that retry sequence numbers reflect reality. Each item returns its own
result object; a per-item failure (e.g. question no longer in the day) does not abort the batch.

### 14.2 Completion

```
POST /student/quiz-attempts/{attempt}/complete   (Idempotency-Key required)

DB::transaction:
    attempt = SELECT ... FOR UPDATE                       # serialises the race in rule 23
    if attempt.status == 'completed':                     # idempotent success
        return existing result payload

    # re-derive from the state table — never trust a counter or the client
    mastered = SELECT COUNT(*) FROM quiz_attempt_questions
               WHERE quiz_attempt_id = ? AND state = 'mastered'
    if mastered < attempt.required_count:
        throw QuizNotCompleteException(422, outstanding: [question ids not mastered])

    attempt.status       = 'completed'
    attempt.score        = mastered                       # == required_count == 10
    attempt.mastered_count = mastered
    attempt.completed_at = now()

    enrollment = SELECT ... FOR UPDATE
    enrollment.completed_days      = recount(distinct completed full-score days)
    enrollment.last_completed_day  = attempt.day_number
    enrollment.last_completed_at   = now()
    enrollment.current_day         = min(attempt.day_number + 1, course.final_test_day)
    enrollment.highest_unlocked_day= computeHighestUnlocked(enrollment)   # pure function of completions
    enrollment.progress_percent    = completed_days / total_quiz_days * 100
    enrollment.total_study_seconds += attempt.time_spent_seconds
    if enrollment.completed_days == course.total_quiz_days:
        enrollment.final_test_unlocked_at = now()
    save enrollment

    StreakService::registerCompletion(user, course, localDate(now(), user.timezone))
        # same local day  → no change
        # yesterday       → current_streak += 1
        # older / first   → current_streak = 1
        # longest_streak  = max(longest, current)

commit
→ event DailyQuizCompleted(attempt)
     ├ listener InvalidateStudentDashboardCache   (deletes the 3 scoped keys)
     ├ listener UnlockNextQuizDay                 (audit log entry + push if enabled)
     ├ listener NotifyFinalTestUnlocked           (only when the 30th day lands)
     └ job     RecalculateStudentProgressJob      (queue: default — topic/accuracy aggregates)
→ response: { score, required, day_number, next_day: {number, unlocked, unlocks_at?},
              streak, final_test: {unlocked, url?}, time_spent_seconds }
```

**Concurrency proof sketch:** two simultaneous `complete` calls both attempt `SELECT ... FOR UPDATE` on the same
`quiz_attempts` row; MySQL serialises them. The first sets `status='completed'` and commits; the second, now unblocked,
re-reads `status` inside its own transaction, sees `completed`, and returns the existing result. No double streak
increment, no double unlock, no duplicate certificate. The same pattern protects `final-test/submit`.

### 14.3 Full-retry mode

When `courses.full_retry_mode = 1`, an attempt that is abandoned and restarted resets **all** `quiz_attempt_questions`
rows for the new attempt (new `attempt_number`), so previously-correct questions must be answered again. In the default
mode (`0`), mastered questions carry into the new attempt and only retry-required ones are served. Both are covered by
tests; the flag is read from the course, never from the request.

---

## 15. Final-test generation flow

### 15.1 Materialisation (admin-triggered or automatic on first eligibility)

```
FinalTestService::materialise(course):
    assert course.total_quiz_days days exist and each has exactly daily_question_count active questions
    version = course.content_version                      # ties the snapshot to the content it came from

    DB::transaction:
        finalTest = upsert final_tests (course_id, version) with settings copied from the course
        # single set-based insert — no loops, no per-question queries
        INSERT INTO final_test_questions (final_test_id, question_id, source_day_number, position, marks)
        SELECT :finalTestId,
               dqq.question_id,
               dq.day_number,
               ROW_NUMBER() OVER (ORDER BY dq.day_number, dqq.position),
               1.00
        FROM daily_quiz_questions dqq
        JOIN daily_quizzes dq ON dq.id = dqq.daily_quiz_id
        JOIN questions q      ON q.id  = dqq.question_id
        WHERE dq.course_id = :courseId
          AND dq.day_number BETWEEN 1 AND :totalDays
          AND dq.status = 'active' AND q.status = 'active' AND q.deleted_at IS NULL
        ORDER BY dq.day_number, dqq.position

        finalTest.question_count = affectedRows            # e.g. 300
        finalTest.materialised_at = now()
        if affectedRows != course.final_test_question_count:
            log warning + surface a banner in the admin UI (do not silently proceed)
```

One `INSERT ... SELECT` produces all 300 rows. Re-materialising creates a **new version**; students already inside an
attempt keep pointing at their version, so an admin edit can never change a test mid-flight.

### 15.2 Attempt start

```
POST /student/courses/{course}/final-test/attempts
    eligibility = QuizUnlockService::decideFinalTest(enrollment)     # 30 × 10/10 or 423
    existing = in_progress|paused attempt for (user, final_test)  → resume it (idempotent)

    shuffleSeed = crypto_random_int()                     # server-generated, stored
    order       = final_test_questions ids ordered by position
    if finalTest.shuffle_questions: order = seededShuffle(order, shuffleSeed)

    DB::transaction:
        attempt = INSERT final_test_attempts {
            question_order: JSON(order),                  # authoritative order, immutable
            shuffle_seed, current_position: 1,
            time_limit_seconds: course.final_test_time_limit_minutes * 60,
            expires_at: time limit ? now() + limit : null,
            status: 'in_progress', started_at: now()
        }
        # pre-create answer rows? NO — 300 empty rows per attempt is wasted write volume.
        # rows are created on first save via upsert.

    return { attempt_uuid, total: count(order), current_position, expires_at, palette_state: all 'unanswered' }
```
Option shuffling, when enabled, is also derived from `shuffle_seed` + `question_id`, so the same student always sees the
same option order (essential for resume and for the review screen) while different students see different orders.
The seed lives on the server; the client receives only the already-ordered option list.

### 15.3 Delivery and answering

```
GET /final-test-attempts/{attempt}/questions?position=1&limit=20
    → slice question_order[position-1 : position-1+limit]
    → SELECT id, question_text, question_image_path FROM questions WHERE id IN (…20 ids)
      + options via a single WHERE question_id IN (…) query
    → QuestionForStudentResource (answer-free), ordered to match the slice

Client behaviour:
    - keeps a sliding window of ~40 questions in memory (current ±20), prefetches the next window at 75% through
    - renders exactly ONE <QuestionCard>; the palette is virtualised (rows of 10 cells, ~30 rendered)
    - every selection: (1) update local reducer, (2) write to IndexedDB immediately, (3) mark dirty
    - batch sync: debounce 3 s, flush at 10 dirty answers, on window blur, on palette open, and every 30 s
      POST /answers/batch { batch_uuid, answers: [{question_id, selected_option, is_flagged, time_spent_ms}] }
      pure upsert on (final_test_attempt_id, question_id); response returns only { saved: n, server_time }
    - no correctness is returned or displayed until submission
```

### 15.4 Submission and grading

```
POST /final-test-attempts/{attempt}/submit  (Idempotency-Key)
    flush any remaining local answers first (client does this before calling submit)
    DB::transaction:
        attempt = SELECT ... FOR UPDATE
        if status in ('submitted','graded'): return existing result (idempotent)
        if expires_at and now() > expires_at + 60s grace: status = 'expired' (still graded)
        attempt.status = 'submitted'; attempt.submitted_at = now()
    dispatch GradeFinalTestJob(attempt) on queue 'critical'
    return 202 { status: 'grading', result_url }

GradeFinalTestJob:
    # one pass, set-based grading — no per-question queries
    UPDATE final_test_answers a
      JOIN questions q ON q.id = a.question_id
      JOIN final_test_questions ftq ON ftq.final_test_id = ? AND ftq.question_id = a.question_id
      SET a.is_correct = (a.selected_option = q.correct_option),
          a.marks_awarded = IF(a.selected_option = q.correct_option, ftq.marks, 0)
      WHERE a.final_test_attempt_id = ?

    aggregate in ONE query (correct, incorrect, answered, marks) + a second grouped query for
    topic / difficulty / source_day breakdowns
    unanswered = total - answered

    DB::transaction:
        attempt.correct_count/incorrect_count/unanswered_count/score/percentage/passed
        attempt.status = 'graded'; graded_at = now()
        result_released_at = course.release_results_immediately ? now() : null
        if passed and course.issue_certificate: create certificate (unique per attempt) → queue PDF render
        enrollment.final_test_passed_at / status='completed' when passed
    events: FinalTestGraded → push notification, dashboard cache invalidation
```

Grading 300 answers is **2 UPDATE/SELECT round trips**, not 300. Running it on the `critical` queue keeps the HTTP
request fast and lets the client poll `/result` (or receive a push) without holding a connection open.

---

## 16. PDF import workflow

### 16.1 Pipeline

```
[Admin] upload  ──▶ validate ──▶ store private ──▶ checksum ──▶ dedupe ──▶ pdf_imports row (uploaded)
                                                                              │ dispatch (queue: pdf)
                                                                              ▼
                                       ProcessPdfImportJob (ShouldBeUnique on import id)
                                          status → queued → processing
                                          1. open + probe: page count, encryption, text-layer presence
                                          2. for each page batch (10 pages):
                                                extract text (primary: smalot/pdfparser)
                                                if a batch yields < 20 chars/page → mark "low text"
                                                append to a Redis buffer keyed by import id (1 h TTL)
                                                update pages_processed + progress_percent (throttled to 1/s)
                                          3. if overall text density too low → status=failed
                                                (reason: no_text_layer) and stop — this is the "scanned PDF" path,
                                                where OCR would later plug in
                                          4. QuestionBlockParser: split into candidate blocks
                                          5. per block: parse stem, options, answer line, explanation
                                          6. validate + hash + dedupe (within batch and against questions table)
                                          7. bulk insert pdf_import_items in chunks of 200
                                          8. counters → status: needs_review | partially_completed | failed
                                          9. clear Redis buffer; keep the original file for audit
                                                                              │
[Admin] review preview ◀──────── poll /progress or SSE ────────────────────────┘
        fix items → assign course/category/day → approve selected
                                          │
                                          ▼
                        ApprovePdfImportAction (queue: pdf, batched)
                          chunk approved items by 500
                          per chunk, one transaction:
                             INSERT questions (bulk)  → collect ids
                             INSERT question_options (bulk, 4 rows per question)
                             UPDATE pdf_import_items SET created_question_id, status='approved'
                          bump courses.content_version → cache invalidation + final-test re-materialisation flag
                          status → completed; audit entry written
```

### 16.2 Parsing strategy (`PdfQuestionExtractionService`)

Text is normalised first: CRLF→LF, non-breaking spaces→space, ligature/Unicode quote folding, de-hyphenation of
line-broken words, removal of repeated headers/footers (lines that appear on > 60% of pages), page-number stripping.

**Block splitting.** A new question starts at a line matching a question-number pattern *and* followed within the next
~12 lines by at least two option-like lines:

```
QUESTION_START  ^\s*(?:Q(?:uestion)?\.?\s*)?(\d{1,4})\s*[\.\)\:\-]\s+(?<stem>.+)$
OPTION_LINE     ^\s*(?:\(|\[)?\s*(?:Option\s*)?([A-Da-d1-4])\s*(?:\)|\.|\]|:|\-)\s+(?<text>.+)$
ANSWER_LINE     ^\s*(?:Correct\s*)?(?:Ans(?:wer)?|Key|Sol(?:ution)?)\s*(?:Option)?\s*[:\-\.]?\s*
                 (?:\(|\[)?\s*([A-Da-d1-4])\s*(?:\)|\])?\s*$
ANSWER_INLINE   \b(?:Ans(?:wer)?|Key)\b\s*[:\-]?\s*(?:\(|\[)?([A-Da-d1-4])(?:\)|\])?
EXPLANATION     ^\s*(?:Explanation|Solution|Reason|Note)\s*[:\-]\s*(?<body>.*)$
```

Handled variations (all in the parser's unit-test fixtures): `A.` `A)` `(A)` `[A]` `A -` `Option A`, lowercase
`a)`, numeric `1)`, `Answer: B`, `Correct Answer: B`, `Ans: B`, `Ans - B`, `Answer – (B)`, `Key: B`, `Sol: B`,
multi-line stems, multi-line options, multi-line explanations (continuation until the next question start or a blank
line followed by a question start), options on one line (`A. Mumbai  B. Delhi  C. Chennai  D. Kolkata`, split by a
lookahead), answers listed in a separate answer-key table at the end of the document (a second pass maps
`question number → letter`), and per-question difficulty/topic tags (`[Medium] [Polity]`).

**Confidence score** per item (0–1) from: all four options present, answer letter resolvable, explanation present,
stem length plausible (10–600 chars), option lengths plausible, no leftover markup. The review UI sorts ascending by
confidence so admins fix the worst first — this is the single biggest saver of admin time.

**Validation → `status`:**
- all of {stem, 4 options, answer} present → `parsed`
- missing any → `incomplete` with `validation_errors` (e.g. `["missing_option_d","unresolved_answer"]`)
- `question_hash` collides with an existing active question in the target course → `duplicate` +
  `duplicate_of_question_id`
- collides with an earlier item in the same import → `duplicate` (keeps the higher-confidence one)

`question_hash = sha256( lower(trim(collapse_ws(stem))) | '|' | sorted(lower(trim(option_texts))) )` — normalised so
that whitespace, case and option order changes do not defeat deduplication, while a genuinely different question
still hashes differently.

### 16.3 Failure matrix

| Condition | Detection | `failure_reason` | Admin sees |
|---|---|---|---|
| Empty PDF | 0 pages or < 50 chars total | `empty` | "No readable content found" |
| Password protected | parser throws / encryption dict present | `encrypted_pdf` | "Remove the password and re-upload" |
| Corrupted | parser exception on open, invalid header bytes | `corrupt` | "File could not be opened" |
| Scanned (no text layer) | text density < 20 chars/page across the doc | `no_text_layer` | "This looks like a scanned document; OCR is not available yet" |
| Unsupported layout | 0 blocks detected despite good text density | `no_questions_detected` | Raw text preview + a link to the expected format |
| Missing answers only | items parsed but `unresolved_answer` count > 0 | — (`partially_completed`) | Review table with the gaps highlighted |
| Job timeout | queue timeout 900 s | `timeout` | "Too large to process in one pass — split the file" + retry button |
| Worker crash / OOM | `failed_jobs` entry, `failed()` hook sets status | `worker_failure` | Retry button; the import is never left stuck in `processing` |
| Partial extraction | some batches failed | — (`partially_completed`) | Per-page error list |

Job config: `tries = 3`, `backoff = [60, 300, 900]`, `timeout = 900`, `maxExceptions = 2`, `ShouldBeUnique` keyed on the
import id for 1 h. The `failed()` hook always writes a terminal status + reason, so the UI never shows a spinner
forever. `CleanupAbandonedImportsJob` (daily) fails imports stuck in `processing` for > 2 h and deletes files for
imports abandoned in `needs_review` for > 30 days.

### 16.4 OCR seam (for later, not built now)

`Services/Pdf/Contracts/TextExtractorInterface` has implementations `PdfParserExtractor` and `PdfToTextExtractor`,
selected by a `parser_profile`. Adding OCR later means adding an `OcrExtractor` (Tesseract/cloud) plus one branch in the
probe step: when text density is too low, dispatch `OcrPdfPagesJob` on the `pdf` queue instead of failing. No other code
changes. This is why `pdf_imports.extractor` exists as a column today.

---

## 17. Push-notification workflow

### 17.1 Subscription

```
Student taps "Enable reminders" (a user gesture — never automatic)
  → Notification.requestPermission()
      granted  → registration.pushManager.subscribe({ userVisibleOnly: true,
                     applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY from /bootstrap) })
                 → POST /student/push-subscriptions { endpoint, keys, device_label, timezone }
                 → upsert by sha256(endpoint): same device re-subscribing updates instead of duplicating
      denied   → persist "denied" locally, never re-prompt; show how to re-enable in browser settings
      dismissed→ allow re-ask after 7 days, max twice
  Unsupported browser (no PushManager / iOS not installed to home screen)
      → show in-app reminders only, explain the "Add to Home Screen" step for iOS
```

### 17.2 Scheduled delivery (the fan-out that must not melt the server)

```
Scheduler (every 5 minutes, single process, withoutOverlapping):
    DispatchDailyRemindersJob                      # dispatch-only; does zero delivery work
        for each IANA timezone that has subscribers (cached list, ~40 rows):
            localTime = now() in that timezone, rounded to a 5-minute slot
            # candidate students: reminder_time falls in this slot, reminders enabled,
            # active enrolment, today's quiz not yet completed
            chunkById(500) over a covering query:
                  users u JOIN onboarding_preferences p JOIN course_enrollments e
                  WHERE u.timezone = :tz AND p.notifications_opt_in = 1
                    AND p.reminder_time BETWEEN :slotStart AND :slotEnd
                    AND e.status = 'active'
                    AND NOT EXISTS (completed attempt for e.current_day today)
                → Bus::batch( SendPushBatchJob(userIds chunk of 100) )->onQueue('notifications')
```
`chunkById` (not `chunk`) and `select` only the needed columns: memory stays flat regardless of student count.
**No `User::all()`, no per-student query, no per-student job.**

```
SendPushBatchJob(userIds[100]):
    load active subscriptions for those users in ONE query (ix_push_user_status)
    insert notifications rows in ONE bulk insert (one per user)
    insert notification_deliveries rows in ONE bulk insert (one per subscription)
    foreach subscription:                      # WebPush library batches HTTP/2 requests internally
        payload = { title, body, url, tag: 'daily-reminder-{day}', renotify: false }
        result  = webPush->queueNotification(sub, payload)
    flush() → iterate results:
        201/200          → delivery.status = sent
        404 / 410 (gone) → subscription.status = 'expired'   → collected for one bulk UPDATE
        429              → re-queue this subscription with backoff honouring Retry-After
        413              → log payload-too-large (a bug; payload is capped at 3 KB by construction)
        5xx              → retry via job retry (tries=3, backoff 60/300/900)
    bulk-apply status updates (2 UPDATE statements, not 100)
```

Payload is intentionally small and contains **no sensitive data** — no question text, no scores. It carries a type, a
day number and a deep-link URL; the app fetches real data after the user taps.

### 17.3 Notification types and triggers

| Type | Trigger | Queue | Dedupe key |
|---|---|---|---|
| `daily_reminder` | scheduler slot match, today's quiz incomplete | notifications | `daily:{user}:{course}:{localDate}` |
| `missed_quiz` | daily job: no completion for ≥ `missed_quiz_reminder_hours` | notifications | `missed:{user}:{course}:{localDate}` |
| `streak` | streak ≥ 3 and at risk (nothing done by 21:00 local) | notifications | `streak:{user}:{localDate}` |
| `final_test_unlocked` | `DailyQuizCompleted` when the 30th day lands | notifications | `final:{user}:{course}` |
| `course_completed` | `FinalTestGraded` (passed) | notifications | `complete:{user}:{course}` |
| `announcement` | admin campaign send | notifications | `campaign:{id}:{user}` |
| `system` | maintenance, security notice | notifications | ad hoc |

Dedupe keys are Redis `SETNX` guards with a 24 h TTL, so a scheduler re-run or a worker retry cannot double-notify.
Per-user daily cap: 3 pushes (configurable), enforced in `PushNotificationService` before enqueueing.

### 17.4 Client-side handling

The service worker handles `push` (show notification, `tag`-based collapsing), `notificationclick` (focus an existing
client or open the deep link), and `pushsubscriptionchange` (re-subscribe and PATCH the new endpoint automatically —
this is what keeps long-lived installs working). Quiet hours (22:00–07:00 local) are respected server-side by shifting
non-urgent notifications to the next morning slot.

### 17.5 Cleanup

- `PruneExpiredSubscriptionsJob` (daily): delete `status='expired'` older than 30 days; mark `active` subscriptions
  with `failure_count >= 5` and no success in 14 days as `expired`.
- Logout deletes the current device's subscription (client calls `DELETE /push-subscriptions` before clearing state).
- Account deletion cascades (`ON DELETE CASCADE` on `push_subscriptions.user_id`).
