# ADR 001 — Per-student question assignment

**Status:** Accepted (supersedes the shared `daily_quiz_questions` snapshot model in `docs/phase-1/03-database-design.md` §6.4)
**Date:** 2026-08-03 (revised)
**Context:** FMGE / AMC and other medical licensing exam preparation

**Revision note:** an earlier draft of this ADR also permuted answer-option order per student. That was rejected by the
client and removed. **Answer order is fixed as authored.** Authored difficulty levels were also removed.

> **Terminology:** this document was written before [ADR 002](./002-programme-levels-and-cycles.md), which renames the
> 30-day unit from **course** to **level** and nests six of them inside a **programme**. Read every "course" below as
> "level" (`courses` → `levels`, `course_enrollments` → `level_enrollments`, `questions.course_id` →
> `questions.level_id`). Everything else in this ADR is unchanged and applies *within* a single level.
> The Phase 1 documents will be renamed in one pass.

---

## The requirement

1. If 100 students are enrolled, no two should receive the same ten questions on Day 1. The **question blocks** must be
   shuffled between students.
2. A **question block** — the question, its four choices, and which choice is correct — is **atomic**. Its internal order
   never changes.
3. Every student must complete **all 300** questions by the end of the month before reaching the month-end test.
4. The month-end test contains those same 300 questions the student has become familiar with, but **not in the same
   order** they practised them.
5. Unlimited retries on daily quizzes and unlimited attempts at the month-end test. No negative marking.

## Decision

**Assignment of question blocks to days moves from the course to the individual enrolment.** Each student receives an
*exhaustive personal permutation* of the course's 300 questions, dealt into 30 days of 10.

- The course holds exactly `total_quiz_days × daily_question_count` = **300** active questions.
- At enrolment, `AssignCourseQuestionsAction` deals all 300 into that student's 30 days using a stored per-student seed,
  and bulk-inserts the result.
- Every student therefore covers **all 300** questions (requirement 3), but no two students share a Day 1
  (requirement 1).
- The month-end test is that student's own 300, re-ordered with a separate per-attempt seed (requirement 4).
- **Option order is never touched** (requirement 2).

The elegant consequence of an exhaustive permutation: requirements 1 and 3 are usually in tension — "everyone must do
all of them" versus "nobody gets the same ones" — and they are reconciled by shuffling the *grouping*, not the
*selection*.

---

## 1. Schema changes

### New table

```sql
CREATE TABLE enrollment_day_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id BIGINT UNSIGNED NOT NULL,
  course_id     BIGINT UNSIGNED NOT NULL,   -- denormalised for reporting joins
  user_id       BIGINT UNSIGNED NOT NULL,   -- denormalised: avoids joining enrolments on the hot path
  day_number    SMALLINT UNSIGNED NOT NULL,
  question_id   BIGINT UNSIGNED NOT NULL,
  position      SMALLINT UNSIGNED NOT NULL, -- 1..daily_question_count, this student's order within the day
  assigned_at   TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,

  UNIQUE KEY uq_edq_enrolment_question (enrollment_id, question_id),        -- never the same question twice
  UNIQUE KEY uq_edq_enrolment_day_position (enrollment_id, day_number, position),
  KEY ix_edq_enrolment_day (enrollment_id, day_number),                     -- the hot read
  KEY ix_edq_user_course (user_id, course_id),
  KEY ix_edq_question (question_id),                                        -- per-question analytics

  CONSTRAINT fk_edq_enrolment FOREIGN KEY (enrollment_id)
      REFERENCES course_enrollments(id) ON DELETE CASCADE,
  CONSTRAINT fk_edq_question FOREIGN KEY (question_id)
      REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

`uq_edq_enrolment_question` is the constraint that matters most: "a student never sees the same question twice in their
course" becomes a database guarantee rather than an application hope.

### Added columns

```sql
-- course_enrollments
assignment_seed        INT UNSIGNED NOT NULL,     -- drives the day deal; reproducible forever
questions_assigned_at  TIMESTAMP NULL,

-- courses
question_selection_mode  VARCHAR(24) NOT NULL DEFAULT 'exhaustive_shuffle',
                         -- exhaustive_shuffle | fixed_shared
spread_topics_across_days TINYINT(1) NOT NULL DEFAULT 1,

-- final_test_attempts
shuffle_seed  INT UNSIGNED NOT NULL      -- already present; now generated PER ATTEMPT (unlimited attempts)
```

### Removed

| Removed | Reason |
|---|---|
| `daily_quiz_questions` table | Replaced by the per-enrolment table |
| `final_test_questions` table | The final test's content is now derived per student |
| `questions.difficulty` (+ its CHECK constraint) | Client decision: no authored difficulty levels. **Observed** difficulty is kept — `questions.observed_difficulty` (was `difficulty_score`) is computed nightly from real wrong-answer rates, which is more accurate than an author's guess |
| `courses.shuffle_option_order` | Option order is never shuffled |
| `enrollment_day_questions.option_order` | Same |
| `courses.negative_marks_per_wrong` | No negative marking |
| Index `questions(course_id, status, difficulty)` | Becomes `questions(course_id, status)` |

`daily_quizzes` **stays** as the per-course day container (day number, title, status, `available_from`), because
`quiz_attempts.daily_quiz_id` and all the unlock/scheduling logic key off it. Only *membership* moved.

`fixed_shared` mode (every student gets an identical deal) is implemented by substituting `course.id` for
`enrollment.assignment_seed`. **One code path, two behaviours** — which avoids maintaining two question-delivery
implementations on the most safety-critical route in the app.

---

## 2. Assignment algorithm

Runs once per enrolment, inside `POST /student/onboarding/complete`, in the same transaction that creates the enrolment.

```
AssignCourseQuestionsAction(enrollment):

  course   = enrollment.course
  required = course.total_quiz_days × course.daily_question_count       # 30 × 10 = 300
  seed     = enrollment.assignment_seed                                # crypto_random_int, stored

  pool = SELECT id, topic
         FROM questions
         WHERE course_id = ? AND status = 'active' AND deleted_at IS NULL
         # one query, two narrow columns

  if count(pool) < required:
      throw InsufficientQuestionPoolException(needed: required, available: count(pool))
      # unreachable in practice: the course Readiness check blocks publishing below 300,
      # so a student can never enrol into a short course

  ordered = seededShuffle(pool, seed)                    # full permutation of all 300
  if course.spread_topics_across_days:
      ordered = spreadTopics(ordered, course.daily_question_count, seed)   # §4

  rows = []
  foreach chunk(ordered, course.daily_question_count) as dayIndex => questions:
      foreach questions as i => questionId:
          rows[] = { enrollment_id, course_id, user_id,
                     day_number: dayIndex + 1, question_id: questionId,
                     position: i + 1, assigned_at: now() }

  DB::table('enrollment_day_questions')->insert(rows)    # ONE bulk insert of 300 rows
  enrollment.questions_assigned_at = now()
```

**Cost:** one `SELECT` of 300 narrow rows plus one 300-row bulk insert, once per student, ever. At 2,000 students that is
600,000 rows in total — trivial for MySQL, and it buys a fully deterministic, auditable, resumable assignment.

**Why eager rather than lazy:** assigning day-by-day at unlock time would let a growing bank feed in-progress students,
but it makes the month-end question set unknowable until the month ends, complicates day-boundary retries, and adds a
pool-exhaustion failure mode at day 28. Eager assignment is deterministic and testable. If the bank is later expanded,
`POST /admin/enrolments/{id}/reshuffle-future-days` re-deals only the days a student has not yet started.

---

## 3. Determinism

Every shuffle is a **seeded** permutation, never `rand()`:

```
seededShuffle(items, seed)          # Fisher-Yates driven by a seeded PRNG
finalTestOrder(attemptSeed, ids)    # separate seed, generated per attempt
```

| Property | Why it matters |
|---|---|
| Same student + same day → same questions on every request | Resume after refresh, offline replay and retries all work |
| Different students → different days | The requirement |
| Reproducible from the stored seed | Support can reconstruct exactly what a student saw on Day 7, months later |
| A new seed per final-test attempt | Unlimited attempts each get a fresh order |

The assignment is nevertheless **persisted** rather than recomputed from the seed on each request. Persistence is what
makes the month-end set derivable with one indexed query, and it protects against a future PHP or PRNG change silently
rewriting a student's history — unacceptable in an exam record.

---

## 4. Topic spreading

With authored difficulty removed, the one remaining quality control is subject variety. A plain shuffle will
occasionally hand a student a Day 1 of nine Anatomy questions, which makes the day feel like a single-subject drill
rather than exam practice.

```
spreadTopics(orderedIds, perDay, seed):
    # after the full shuffle, redistribute so that consecutive days and consecutive
    # positions within a day rarely repeat a topic:
    #   1. bucket the shuffled list by topic, preserving shuffled order inside each bucket
    #   2. deal round-robin across buckets, largest bucket first
    #   3. cap any single topic at ceil(perDay / 3) = 4 per day where the pool allows
    #   4. questions with a blank topic are treated as one "unclassified" bucket
```

This is a pure reordering of an already-complete permutation — it never changes *which* questions a student receives,
only their grouping, so requirement 3 is untouched. Disable with `spread_topics_across_days = 0`.

A test asserts that across 100 simulated enrolments, no day exceeds the per-topic cap whenever the pool's composition
permits it.

---

## 5. Day 31 month-end test

```
StartFinalTestAttemptAction(enrollment):
    assert eligibility               # all 30 days completed at 10/10 — unchanged
    assert attempts allowed          # final_test_attempt_limit = 0 → unlimited

    attemptSeed = crypto_random_int()                    # NEW SEED EVERY ATTEMPT

    practised = SELECT question_id FROM enrollment_day_questions
                WHERE enrollment_id = ?
                ORDER BY day_number, position            # 300 rows, one indexed scan

    order = seededShuffle(practised, attemptSeed)        # deliberately not the practice order

    attempt = INSERT final_test_attempts {
        question_order: JSON(order), shuffle_seed: attemptSeed,
        attempt_number: previous + 1, status: 'in_progress', ...
    }
```

So the test contains exactly the 300 questions the student is familiar with, in an order that is different from their
practice month **and** different on every re-attempt. Options appear as authored, as everywhere else.

Delivery is unchanged from the Phase 1 design: windowed fetches of 20 questions, one mounted question component, a
virtualised palette, debounced batch upserts, two-statement grading.

Unlimited attempts mean the month-end test is a **practice instrument, not a gate**. A certificate is issued on the first
passing attempt; later attempts update the best score without re-issuing.

---

## 6. Caching — improved by this change

The old key was per course + day, shared by all students. Per-student assignment would naively explode that into one key
per student per day.

**Instead, cache at the question level:**

```
q:v1:{question_id}:cv{content_version}   →  answer-free question + options, as authored   (TTL 6 h)
```

A quiz-day request then:
1. reads the student's 10 `question_id`s from `enrollment_day_questions` (one indexed query);
2. fetches all 10 bodies with a single Redis `MGET`;
3. returns them in the student's stored `position` order.

This is **better than the original design**: a question is cached once and reused by every student served it, rather than
being duplicated inside hundreds of per-course-day payload blobs. Redis memory goes down, hit ratio goes up, and
invalidation is per question via `content_version`.

Query budget for a quiz-day load stays at **1 Redis MGET + 2 indexed MySQL queries**. No regression.

---

## 7. Consequences for content authoring

Much simpler than the previous draft of this ADR:

| | Rule |
|---|---|
| **Count** | Exactly **300** active questions per course. Below that the course cannot be published; above it the surplus goes unused per student |
| **Days** | Do not assign them. There is no `quiz_day` column |
| **Difficulty** | Do not label it. The system measures it from real student performance |
| **Topic** | Fill it in. It drives the weak/strong subject analysis and day-to-day subject variety |
| **Option letters in explanations** | **Allowed.** Option order never changes, so "the answer is B" is always accurate. Naming the answer *text* still teaches better, but that is style, not a rule |
| **"All of the above"** | Fine. Nothing reorders choices |

---

## 8. Consequences for reporting

| Report | Effect |
|---|---|
| Most-missed / hardest questions | **Unchanged**, and now better sampled — every question is answered by every student |
| Topic-wise performance | **Unchanged** |
| Difficulty-wise performance | Now banded by **observed** difficulty (measured wrong-answer rate) instead of an authored label. More useful: it reflects what students actually find hard |
| "Average score on Day 7 across students" | **No longer like-for-like** — Day 7 holds different questions per student. The admin day-performance report labels this explicitly rather than showing a number that looks comparable but isn't. Cohort comparison should use topic, accuracy and month-end results |
| Month-end test comparisons | **Fully comparable** — every student answers the identical 300 questions, only the order differs |

---

## 9. Alternatives rejected

| Option | Why rejected |
|---|---|
| Shared 10 questions per day for everyone, shuffle order only | Fails requirement 1 — students still receive the same ten questions |
| Also permuting answer-option order per student | Explicitly rejected by the client. A question block is atomic |
| Sampling 300 from a larger pool | Conflicts with requirement 3 ("finish all 300"). Available as a deliberate future option by publishing more than 300 |
| Recomputing assignment from the seed on every request, storing nothing | Cheaper on storage, but the month-end set could not be derived in one query and a PRNG change would rewrite history |
| Drawing 10 random questions at the start of each day | The practised set is unknowable until month end, and pool exhaustion becomes a live failure at day 28 |
| Keeping both `daily_quiz_questions` and the new table | Two delivery code paths on the most safety-critical route. Seed substitution gives the same capability with one path |
