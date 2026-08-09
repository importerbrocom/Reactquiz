# Phase 3 — Student API

Every response uses the standard envelope (`success`, `message`, `data`, `errors`, `meta`).
All routes below require `Authorization: Bearer <token>` and the `student` role.

Attempts are addressed by **UUID**. An id belonging to another student returns **404**, not 403 —
a 403 would confirm the row exists and let ids be enumerated.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/v1/student/onboarding` | Exam categories + programmes; resume pointer |
| POST | `/api/v1/student/onboarding` | Choose exam + programme, enrol, deal questions |
| GET | `/api/v1/student/dashboard` | Home screen, including `next_action` |
| GET | `/api/v1/student/progress` | Accuracy, retention, per-topic breakdown |
| GET | `/api/v1/student/mistakes` | Questions answered wrong, **with** answers |
| GET | `/api/v1/student/days` | 30-day timeline |
| GET | `/api/v1/student/days/{day}` | Is this day open? |
| POST | `/api/v1/student/days/{day}/attempt` | Start **or resume** the day |
| GET | `/api/v1/student/attempts/{uuid}` | Re-read an attempt (resume after reload) |
| POST | `/api/v1/student/attempts/{uuid}/answers` | Submit one answer, get the verdict |
| POST | `/api/v1/student/attempts/{uuid}/complete` | Claim the day as finished |
| GET | `/api/v1/student/level-test` | Eligibility, past attempts, open attempt |
| POST | `/api/v1/student/level-test/attempt` | Start or resume the month-end test |
| GET | `…/level-test/attempts/{uuid}/questions` | One window (`position`, `limit`) |
| GET | `…/level-test/attempts/{uuid}/navigator` | Answered/flagged grid |
| POST | `…/level-test/attempts/{uuid}/answers` | Batch sync (max 20) |
| POST | `…/level-test/attempts/{uuid}/submit` | Hand in and grade |
| GET | `…/level-test/attempts/{uuid}/result` | Score, if released |
| GET | `/api/v1/student/levels/next` | Can I advance, and to what? |
| POST | `/api/v1/student/levels/next` | Advance to the next level or cycle |

## Error codes

| Code | Status | Meaning |
|---|---|---|
| `ONBOARDING_REQUIRED` | 403 | No enrolment yet — send the student to onboarding |
| `ENROLMENT_INACTIVE` | 403 | Enrolment exists but is not usable — a support problem |
| `QUIZ_DAY_LOCKED` | 423 | Day not reached. `meta.unlock_hint` explains how |
| `QUIZ_NOT_COMPLETE` | 422 | `meta.outstanding_question_ids` lists what is left |
| `LEVEL_TEST_NOT_ELIGIBLE` | 423 | `meta.missing_days` lists what is unfinished |
| `TEST_ATTEMPT_CLOSED` | 409 | Already submitted — fetch the result |
| `TEST_ATTEMPT_EXPIRED` | 409 | Time up; graded on what arrived. Fetch the result |
| `LEVEL_ADVANCE_NOT_ALLOWED` | 423 | Level unfinished, or opens tomorrow |
| `ATTEMPT_ALREADY_COMPLETED` | 409 | Cannot answer into a completed attempt |

423 is used throughout for "not yet", so the client can distinguish it from "not allowed".

## Things the client can rely on

**Repeating a write is safe.** Starting a day, starting the test, completing a day, submitting
the test and advancing a level are all idempotent. A double-tap or a retried request returns the
existing state rather than forking it.

**Answers carry their own idempotency token.** `client_answer_uuid` (single) and
`client_batch_uuid` (batch) are required, and backed by unique constraints — so a replay from the
offline outbox is recorded once even if the cache has been flushed. The `Idempotency-Key` header
is an optional faster short-circuit on top.

**Option order is stable until you answer, and moves afterwards.** Options carry a canonical
`key` and a separate `display_position`; always submit the `key`. A refresh or an offline reload
shows the same order; being asked again does not (`docs/adr/003`).

**Quiz payloads contain no answers.** The answer columns are never selected on the delivery path.
`/mistakes` is the sole exception and only lists questions already answered wrong.

**Verdicts and results are `no-store`.** Do not cache them in the service worker.

## `next_action` on the dashboard

One of: `start_day`, `resume_day`, `locked`, `take_level_test`, `retake_level_test`,
`advance_level`, `programme_complete`. The client should render the primary button from this and
hold no progression logic of its own.

## Open questions

1. **Explanations.** 247 of the 305 extracted questions have none. Ship without, or hold them back?
2. **Option-letter references.** ~50 explanations say "option B", which per-exposure shuffling
   makes wrong. Rewrite, or set `shuffle_options = false` on those questions?
3. **`require_test_pass_to_advance`** currently defaults to *false* — a student advances having sat
   the test, whether or not they passed. Confirm.
