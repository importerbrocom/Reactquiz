---
inclusion: always
---

# QuizPath — product, audience, and where the build has got to

## What this is

A quiz PWA that prepares students for **medical licensing exams they must pass after finishing
MBBS abroad** — FMGE (India) and AMC (Australia), with more exam types addable by an admin.
The owner runs a **study-abroad consultancy** and gives this app to his students as practical
exam preparation. Students are **Indian**. Hosting is a **VPS in the Mumbai region**.

An **iOS React Native app is planned**, which is why the API is token-authenticated rather than
cookie/session based — the same endpoints must serve the PWA and the native app.

## The learning model (this is the product, not an implementation detail)

```
exam_categories   FMGE, AMC, ...          (admin creates any number)
  └── programmes  "FMGE Complete — 6 Months", 1,800 questions
       └── levels Level 1..6 = one month each, 300 questions
            ├── daily_quizzes  Day 1..30, 10 questions per day
            └── level_test     Day 31 — that level's 300, reshuffled
```

- A student must answer all **10 of 10 correctly** to complete a day. Wrong answers are retried,
  **unlimited attempts, never a negative mark**.
- Completing a day unlocks the next. Completing all 30 unlocks the **month-end test**, which is
  the same 300 questions they practised, in a different order.
- Six levels = one **cycle** (~186 days). The cycle then **repeats over the same 1,800 questions**
  with fresh per-student shuffles. After the second cycle a new question bank is introduced.
- **Every student gets a different day 1.** Ten students taking day 1 must not see the same ten
  questions. This was an explicit requirement and drives ADR 001.

## Vocabulary — use these words

`level` = one month (30 days + test). Never "course" — it was renamed because "course" was
ambiguous between the month and the 6-month programme. `programme` = the 6-month container.
`cycle` = one full pass through all levels.

## Stack

Monorepo. `api/` is **Laravel 13** (PHP 8.4) REST API with Sanctum **token** auth.
The frontend will be **React 19** PWA (Phase 4+, not yet started). MySQL + Redis in production;
the sandbox has neither, so tests run on SQLite in-memory.

## Delivery status

| Phase | What | State |
|---|---|---|
| 1 | Architecture — 14 docs in `docs/phase-1/` | **Merged** (PR #1) |
| 2 | Laravel foundation: 36 migrations, 23 enums, 31 models, Sanctum token auth, 11 exceptions, 7 middleware, 5 policies | **Merged** (PR #1) |
| — | Question extraction tooling (`tools/question-extraction/`) | **PR #2 open** |
| 3 | Quiz engine + student API | **PR #3 open**, branch `phase-3-quiz-engine` |
| 4 | React 19 PWA | Not started |
| 5–11 | Admin panel, imports, notifications, reporting, hardening, deployment | Not started |

**Branch topology:** `phase-3-quiz-engine` is cut from `extraction-tooling`, so **PR #3 contains
PR #2's commits**. Merge #2 first, then #3.

> A new session must check out `phase-3-quiz-engine` (or merge #2 and #3 into main first).
> `main` alone has only Phases 1–2 and none of the quiz engine.

## What exists in Phase 3

Actions in `api/app/Actions/`: `Quiz/{AssignLevelQuestions, StartQuizAttempt, SubmitAnswer,
CompleteQuiz, AdvanceLevel}`, `LevelTest/{StartLevelTest, SyncLevelTestAnswers, SubmitLevelTest,
GradeLevelTest}`, `Enrolment/EnrolInProgramme`.

Services in `api/app/Services/`: `Quiz/{QuizUnlock, OptionOrder, AnswerEvaluation,
QuestionDelivery, DailyQuizDelivery, LevelTestDelivery, LevelProgression}`, `Progress/Streak`,
`Student/StudentContext`.

Student endpoints are documented in **`docs/phase-3/student-api.md`** — read that before touching
the HTTP layer.

## Question content

305 questions extracted from two real FMGE papers (Dec 2019: 247, Jan 2024: 58), zero cross-paper
duplicates, in `tools/question-extraction/output/`. Import format and templates are in
`docs/import-format.md` and `docs/templates/`.

## Open questions awaiting the owner's decision

These are **not blockers** for coding, but they block shipping content:

1. **Explanations** — 247 of the 305 questions have none. Ship without, or hold them back?
2. **Option-letter references** — ~50 explanations say "option B is correct", which per-exposure
   shuffling makes actively wrong. Rewrite them, or set `shuffle_options = false` on those?
3. **`require_test_pass_to_advance`** currently defaults to **false**: a student advances having
   *sat* the month-end test, pass or fail. Confirm this is wanted.

Also unconfirmed: whether to raise the explanation length cap from 2000 to 3000 characters.

## How the owner works

He communicates in English and Malayalam, often briefly, sometimes correcting an earlier
statement (he first said students were in Russia/Vietnam/Uzbekistan, then corrected to Indian
students at his consultancy). **Re-read his corrections rather than the original.** He wants
phase-by-phase delivery and approves each phase before the next. He asks for best practice and
performance directly — give him the reasoning, and push back when a proposal of his would cause
data loss or be cheatable, as happened with the `completed_questions` JSON design he proposed.


## Resume here

Immediate next steps, in order:

1. **Get PR #2 and PR #3 merged**, or work on `phase-3-quiz-engine`. Do not start from `main`.
2. **Ask the owner the three open questions above.** Question 2 (explanations naming option
   letters) is the one that will actively teach students something wrong if ignored.
3. **Phase 4 — the React 19 PWA.** Nothing exists yet. `docs/phase-1/06-pwa-and-offline.md` has
   the offline design; `docs/phase-3/student-api.md` is the API contract to build against. The
   offline outbox is the interesting part, and the API is already built to support it: every write
   is idempotent, and answers carry a client-generated uuid.
4. Alternatively the owner may want a **seeded clickable demo** before Phase 4 — he was offered
   this and has not answered.

Not yet built anywhere: admin panel, XLSX/CSV import endpoint (format is designed and templated,
the endpoint is not written), notifications/push, reporting, certificates, deployment.
