---
inclusion: always
---

# Locked decisions — and what was rejected

These are settled. Do not silently revisit them. If a task seems to require reversing one, say so
explicitly and explain the cost, because each of these was chosen over a specific alternative for
a specific reason.

The three that have full write-ups live in `docs/adr/`. Read them before changing the quiz engine.

## 1. Per-student exhaustive question assignment (ADR 001)

All 300 of a level's questions are **dealt into 30 days per student** and **persisted** in
`enrollment_day_questions`, driven by a stored `assignment_seed`. One bulk insert of 300 rows at
enrolment; one indexed range scan per day thereafter.

- **Rejected:** shared days with only the option order shuffled — fails "every student gets a
  different day 1".
- **Rejected:** sampling 10 from a larger pool per day — conflicts with "he must finish all 300
  by month end".
- **Rejected:** recomputing from the seed without persisting — the month-end set could not be
  derived in one query, and pagination/resume/review would disagree about what question 143 was.

The unique index `(level_enrollment_id, question_id)` makes "never the same question twice in a
cycle" a **database guarantee**, not a code convention.

## 2. Per-EXPOSURE option shuffling (ADR 003)

This answers the owner's core concern, quoted verbatim: *"student 1 is familiar with a question and
he knows the answer is option B, so he may remember the position only, not the right answer."*

Option order is seeded on `(scope, question, submission_count)`. Two properties must hold at once:

- **STABLE** while the student is looking — a refresh, an offline reload from the service worker,
  or a resume must not reshuffle under their cursor.
- **MOVING** the next time they are asked, so position carries no information.

`submission_count` only advances when they answer, which is exactly the boundary between the two.

- **Rejected:** per-student-fixed shuffling. The owner rejected it himself and was right — it does
  not solve position memorisation at all.
- Within a *single* month-end test attempt the order is fixed for the whole attempt (flicking back
  to question 3 must not reshuffle it — that reads as a bug and invites a misclick). The
  per-exposure guarantee is met by the **retake** reshuffling everything.
- `pin_last` keeps "All of the above" last. `shuffle_options = false` disables shuffling for
  questions whose options reference each other ("Both 1 and 2").

## 3. Programmes, levels and cycles (ADR 002)

A cycle is **just a new `level_enrollments` row with a new seed**. That is why cycles cost almost
no code: the same dealing logic produces a completely different 30-day grouping.

- **Rejected:** extra columns on the assignment table to track cycles.
- Advancing a level **does not close the finished one**. Retries are unlimited, so a student on
  level 3 must still be able to redo day 5 of level 1. `status` stays `Active`; `completed_at` is
  stamped instead.

## 4. `retry_mode = requeue_at_end` by default

A wrong answer does not come straight back. The student sees the correct answer and explanation,
moves on, and the question returns after the others. That gap turns recognition into recall.

- **Rejected as the default:** immediate retry — it tests echo, not memory. Still available as
  `RetryMode::Immediate`.

## 5. Sanctum **token** mode, not cookie/session

Because a React Native app is planned and must hit the same endpoints. This **reverses an earlier
recommendation** made before the native app was mentioned. Sanctum's stateful middleware is
deliberately not enabled.

## 6. PDF parsing stays OUT of the application

`tools/question-extraction/` is a **dev-only** Python tool. The app imports XLSX/CSV.

- **Rejected:** in-app PDF import (it was risk R8 in Phase 1).
- The tool **never invents explanations and never infers answers** — it flags instead. This is
  non-negotiable: fabricated medical rationale could teach a doctor the wrong medicine.
- `stem_boundary_uncertain` is a **blocking error**, not a warning — it had been silently
  swallowing a previous question's explanation while looking clean.
- Watermarks are filtered at **character level** (size ≥40pt, or lightness >0.8), not by text
  regex, because watermark glyphs interleave mid-word (`intaubation`).
- Two extraction profiles: `numbered` (Dec 2019) and `sectioned` (Jan 2024 — anchors on `● A.`
  option blocks and walks upward, which took detection from 7 questions to 61).

## 7. Grading is set-based and inline

A whole month-end paper grades in a **fixed 4 statements** regardless of size, via a
correlated-subquery `UPDATE` (portable — `UPDATE...JOIN` is MySQL-only and tests run on SQLite).
A test asserts the statement count is *identical* for a 6-question and a 100-question paper.

Grading runs **inline, not queued**, because it is O(1). Queueing it would trade an instant score
for a spinner plus a dependency on a live worker. Genuinely heavy follow-up work (certificates,
email) hangs off the `LevelTestGraded` event instead.

## 8. Nothing the client sends is trusted

- Correctness is **always** re-derived from `questions.correct_option`.
- `AnswerSubmissionData` has **nowhere to put** `is_correct`/`score`/`mastered`, by design. A
  client that sends them is ignored rather than rejected — the more robust outcome.
- Completion re-counts mastered questions from the per-question state table. A tampered
  `mastered_count` on the attempt cannot pass the 10/10 gate.
- Client timestamps are clamped; elapsed time is derived from server timestamps and capped.
- **Rejected outright** (the owner proposed this in Malayalam): a `completed_questions` JSON
  column on the student + `ORDER BY RAND()` + the frontend sending back which questions it chose,
  with cache as the source of truth. It loses updates under concurrency, is cheatable in a single
  request, *increases* database load rather than reducing it, and breaks the month-end test
  because the practised set could not be reconstructed.

## 9. Idempotency is durable, not cache-based

The guarantee is a **unique constraint** on a client-supplied uuid —
`quiz_attempt_answers(quiz_attempt_id, client_answer_uuid)` and the upsert key on
`level_test_answers`. It holds even if the cache is flushed.

The `Idempotency-Key` header middleware is an optional faster short-circuit on top, so the answer
routes use `idempotent:optional`, **not** `idempotent`. Requiring the header as well would reject
correct requests for no added safety.

Test-answer sync is idempotent **by construction**: every write is an upsert, every counter is
**recomputed rather than incremented**. Replaying a batch ten times lands on identical state with
no de-duplication bookkeeping to drift.

## 10. Answers cannot leak, structurally

`QuestionDeliveryService` is the **only** path from a `Question` to a student payload, and the
answer columns (`correct_option`, `correct_answer_text`, `explanation`,
`question_options.is_correct`) are **never SELECTed** on it. A future `toArray()`, `append()` or
debug dump therefore has nothing to reveal.

`QuestionForStudentResource` was **deleted** for this reason — two implementations of the most
security-sensitive transformation in the app is how a leak survives a fix to one of them. Do not
reintroduce a second path.

`/student/mistakes` is the sole student endpoint that reveals answers, and only for questions with
`wrong_count > 0` — which the student was already shown at the moment they got them wrong. **That
`wrong_count > 0` guard is the whole safety argument.** Without it the endpoint is a complete
answer key.

The month-end sync endpoint never reads the answer key and never writes `is_correct` (it stays
NULL until grading), so it is *incapable* of leaking a verdict mid-test.

## 11. HTTP semantics

- **423 Locked** for "not yet" (day locked, test not eligible, level not advanceable) so the
  client can distinguish it from **403** "not allowed".
- **404, never 403**, for another student's resource. A 403 confirms the id exists and allows
  enumeration. Every lookup goes through `StudentContext` scoped to the authenticated user.
- Attempts are addressed by **UUID**, never auto-increment id.
- Answer verdicts and test results are `no-store` — a cached verdict would hand back the answer to
  a question about to be asked again.
- Every failure carries a machine-readable `errors.code`; see the table in
  `docs/phase-3/student-api.md`.

## 12. No authored difficulty

There is deliberately **no difficulty column** — the owner said so explicitly. Difficulty is
*observed* (`questions.observed_difficulty`, computed by a nightly job from real wrong-answer
rates), never authored.
