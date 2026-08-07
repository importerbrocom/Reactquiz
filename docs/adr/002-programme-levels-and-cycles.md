# ADR 002 — Programmes, monthly levels and repeat cycles

**Status:** Proposed — three items need confirmation (marked **⚠ CONFIRM**)
**Date:** 2026-08-03
**Supersedes:** the single-course model in `docs/phase-1/03-database-design.md` §6.2 and §6.4

---

## The requirement

1. A student works through **monthly levels**. Level 1 = month 1 = 300 questions over 30 days + a month-end test.
2. Completing a level unlocks the next. **Six levels = 1,800 questions ≈ 186 days.**
3. After all six levels, the **same 1,800 questions repeat** for another ~180 days — a second pass with a fresh
   per-student shuffle.
4. After that second pass, a **new question bank** is introduced.
5. Everything from ADR 001 still applies *within* a level: exhaustive per-student shuffle of that level's 300, atomic
   question blocks, unlimited retries, unlimited month-end test attempts.

## Decision

Introduce two concepts above the existing 30-day unit, and **rename that unit to match the client's vocabulary**:

```
exam_categories        FMGE, AMC, NEET-PG …                     (exam type — admin creates any number)
   └── programmes      "FMGE Complete — 6 Months" (1,800 Qs)    NEW
          └── levels   Level 1 … Level 6, 300 questions each    RENAMED from `courses`
                 ├── daily_quizzes   Day 1 … Day 30
                 └── level_test      Day 31 — that level's 300, re-shuffled
```

A **cycle** is one full pass through all six levels. Cycle 2 re-deals the same questions with new seeds.

---

## 1. Renaming `courses` → `levels`

The client's own word is "level" ("next level means next month's daily questions"). Keeping `courses` for a 30-day month
would make "the student is enrolled in a course" permanently ambiguous — is that the month or the programme?

**We are still pre-code.** No migrations, no models, no application code exists. The rename costs a find-and-replace in
the Phase 1 documents now, and would cost a schema migration plus a codebase-wide rename later. Do it now.

| Before | After |
|---|---|
| `courses` | `levels` |
| `course_enrollments` | `level_enrollments` |
| `questions.course_id` | `questions.level_id` |
| `daily_quizzes.course_id` | `daily_quizzes.level_id` |
| `final_tests` / `final_test_*` | `level_tests` / `level_test_*` |
| `courses.total_quiz_days` | `levels.total_quiz_days` (still 30) |
| — | `programmes`, `programme_enrollments` (new) |

Everything else in the Phase 1 schema is unchanged.

---

## 2. New tables

```sql
CREATE TABLE programmes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_category_id BIGINT UNSIGNED NOT NULL,
  title            VARCHAR(160) NOT NULL,        -- "FMGE Complete — 6 Months"
  slug             VARCHAR(180) NOT NULL,
  description      TEXT NULL,
  thumbnail_path   VARCHAR(255) NULL,
  bank_label       VARCHAR(60) NULL,             -- "2026 Bank" — shown to admins, not students

  total_levels     SMALLINT UNSIGNED NOT NULL DEFAULT 6,
  -- cycle behaviour
  total_cycles         SMALLINT UNSIGNED NOT NULL DEFAULT 2,   -- 0 = repeat indefinitely
  cycle_reshuffle_scope VARCHAR(24) NOT NULL DEFAULT 'within_level',
                        -- within_level | across_programme        (§5)
  next_programme_id    BIGINT UNSIGNED NULL,     -- where students go after the last cycle (§6)

  -- level progression
  require_test_pass_to_advance TINYINT(1) NOT NULL DEFAULT 0,   -- ⚠ CONFIRM (§4)
  level_unlock_mode VARCHAR(24) NOT NULL DEFAULT 'immediate',   -- immediate | next_calendar_day

  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  questions_count   INT UNSIGNED NOT NULL DEFAULT 0,            -- counter cache, target 1800
  enrollments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,

  UNIQUE KEY uq_programmes_slug (slug),
  KEY ix_programmes_category_status (exam_category_id, status),
  CONSTRAINT fk_programmes_category FOREIGN KEY (exam_category_id)
      REFERENCES exam_categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE programme_enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  user_id      BIGINT UNSIGNED NOT NULL,
  programme_id BIGINT UNSIGNED NOT NULL,
  status       VARCHAR(16) NOT NULL DEFAULT 'active',  -- active|paused|completed|cancelled
  is_active    TINYINT(1) NULL DEFAULT 1,              -- NULL when closed → unique-while-active trick

  current_cycle SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  current_level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  levels_completed_this_cycle SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  total_levels_completed      SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- across all cycles
  total_questions_mastered    INT UNSIGNED NOT NULL DEFAULT 0,
  overall_progress_percent    DECIMAL(5,2) NOT NULL DEFAULT 0.00,

  enrolled_at TIMESTAMP NOT NULL,
  started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,

  UNIQUE KEY uq_prog_enrol_active (user_id, programme_id, is_active),
  UNIQUE KEY uq_prog_enrol_uuid (uuid),
  KEY ix_prog_enrol_user_status (user_id, status),
  KEY ix_prog_enrol_programme (programme_id, status),
  CONSTRAINT fk_prog_enrol_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_prog_enrol_programme FOREIGN KEY (programme_id)
      REFERENCES programmes(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
```

### Changed tables

```sql
-- levels (was courses)
programme_id  BIGINT UNSIGNED NOT NULL,
level_number  SMALLINT UNSIGNED NOT NULL,        -- 1..6
UNIQUE KEY uq_levels_programme_number (programme_id, level_number),
-- prerequisite is level_number - 1 within the same programme; no FK column needed

-- level_enrollments (was course_enrollments) — now ONE ROW PER (student, level, cycle)
programme_enrollment_id BIGINT UNSIGNED NOT NULL,
cycle_number            SMALLINT UNSIGNED NOT NULL DEFAULT 1,
assignment_seed         INT UNSIGNED NOT NULL,          -- from ADR 001; new per cycle
UNIQUE KEY uq_level_enrol_active (user_id, level_id, cycle_number, is_active),
KEY ix_level_enrol_programme_cycle (programme_enrollment_id, cycle_number, level_id),

-- quiz_attempts — a student answers Day 1 of Level 1 again in cycle 2
cycle_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
UNIQUE KEY uq_attempt_user_quiz_cycle_number (user_id, daily_quiz_id, cycle_number, attempt_number),
```

---

## 3. Why cycles cost almost nothing to implement

**Because `level_enrollments` is per (student, level, cycle), ADR 001's machinery handles repetition for free.**

Cycle 2 of Level 1 is simply a *new* `level_enrollments` row with a *new* `assignment_seed`. The same
`AssignCourseQuestionsAction` deals the same 300 questions into a different 30-day grouping. `enrollment_day_questions`
needs no new columns; its `UNIQUE (enrollment_id, question_id)` still means "no repeats **within** a cycle", while
repetition **across** cycles is exactly what we want.

```
Cycle 1, Level 1  →  level_enrollment #1  (seed 8471)  →  300 Qs dealt into days 1-30
Cycle 2, Level 1  →  level_enrollment #7  (seed 2093)  →  same 300 Qs, different days
```

`student_question_progress` remains keyed on `(user_id, question_id)` and accumulates across cycles — which turns it into
a genuine spaced-repetition record: "you have now seen this question 3 times across 2 cycles and got it wrong twice."
That is a reporting asset, not a conflict, because daily-quiz completion is judged from `quiz_attempt_questions`
(per attempt), so a previously mastered question must still be answered correctly in cycle 2.

### Progression state machine

```
CompleteLevelTestAction / CompleteDailyQuizAction:

  if all 30 days of (level, cycle) completed at 10/10:
       unlock level_test for that level
  when level_test submitted (and passed, if require_test_pass_to_advance):
       programme_enrollment.total_levels_completed += 1
       if current_level < programme.total_levels:
            current_level += 1
            create level_enrollments(next level, same cycle, NEW seed)
            → assign that level's 300 questions
       else:
            levels_completed_this_cycle = total_levels
            if current_cycle < programme.total_cycles  (or total_cycles = 0):
                 current_cycle += 1 ; current_level = 1
                 create level_enrollments(Level 1, new cycle, NEW seed)   ← the repeat
            else:
                 status = 'completed'
                 if programme.next_programme_id: offer the new bank      (§6)
```

Level assignment is **lazy per level** — a level's 300 questions are dealt when that level unlocks, not all 1,800 at
enrolment. That keeps the enrolment transaction small, and means questions added to Level 4 while a student is on
Level 2 are still picked up.

---

## 4. Open items

| # | Question | Default if unanswered |
|---|---|---|
| **⚠ 1** | "After **160** days new set of questions" — was that 180 (a full second cycle)? | Treated as 180 = 2 complete cycles, `total_cycles = 2` |
| **⚠ 2** | To advance a level, must the student **pass** the month-end test, or only complete it? Attempts are unlimited, so a pass gate is soft either way | `require_test_pass_to_advance = 0` — completing the 30 days and submitting the test advances them |
| **⚠ 3** | Is there a **programme-wide final** over all 1,800 questions at the end of a cycle, in addition to the six month-end tests? | No — six month-end tests only. Easy to add later as a level-0 style test |

---

## 5. Cycle reshuffle scope

`programmes.cycle_reshuffle_scope` — **⚠ related to item 1**:

| Value | Behaviour | When to use |
|---|---|---|
| **`within_level`** (default) | Cycle 2's Level 1 re-deals the *same* 300 questions that Level 1 held in cycle 1 | Levels have pedagogical structure — e.g. Level 1 is pre-clinical, Level 2 para-clinical. Preserves your curriculum |
| `across_programme` | Cycle 2 re-deals all 1,800 across the six levels, so a Level 1 question may land in Level 4 | Levels are arbitrary batches. Better spaced-repetition mixing, but destroys any subject sequencing |

Default is `within_level` because it preserves whatever structure you authored. Flip it per programme if the levels are
just batches of 300.

---

## 6. New question bank after the cycles

Deliberately kept manual rather than automated:

1. Admin creates a **new programme** — "FMGE Complete — 2027 Bank" — with six levels and 1,800 fresh questions.
2. Sets `next_programme_id` on the old programme to point at it.
3. When a student finishes their last cycle, the app offers the new programme and enrolment carries over
   (same student, new `programme_enrollments` row, progress history preserved).

No new machinery, no automatic content rotation to debug, and the admin decides when the new bank is actually ready.
Automatic rotation can be added later if it ever earns its complexity.

---

## 7. Content staging — what this means practically

**1,800 questions is the full programme, but only 300 are needed to launch.**

| Milestone | Questions needed | Available runway |
|---|---|---|
| Launch Level 1 | **300** | — |
| Before any student reaches Level 2 | 600 | ~31 days after launch |
| Before Level 3 | 900 | ~62 days |
| Before Level 4 | 1,200 | ~93 days |
| Before Level 5 | 1,500 | ~124 days |
| Before Level 6 | 1,800 | ~155 days |
| Cycle 2 | **0 new** — the same 1,800 repeat | ~186 days |

Required content velocity: **300 questions per month ≈ 10 per day.** Each level's Readiness check blocks publishing
below 300, so a level can never go live half-filled, and students can never reach an empty level as long as you stay one
month ahead.

The admin dashboard will show a **"content runway"** card — how many days until the earliest student reaches an
unpublished level — so this never becomes a surprise.

---

## 8. Revised data volume

Per student, over a full two-cycle programme (1,800 questions × 2 passes):

| Table | Rows per student | At 2,000 students |
|---|---|---|
| `enrollment_day_questions` | 3,600 | 7.2 M |
| `quiz_attempt_questions` | 3,600 | 7.2 M |
| `quiz_attempt_answers` (~1.35 per question) | ~4,900 | ~9.8 M |
| `level_test_answers` | 3,600 | 7.2 M |
| `student_question_progress` | 1,800 | 3.6 M |
| `quiz_attempts` | 360 | 720 K |
| **Total** | ~17,900 | **~36 M** |

About 12× the earlier single-course estimate. Still comfortable for one well-indexed MySQL instance — every hot query is
a single-row lookup by primary or unique key — but it changes two pieces of infrastructure advice:

- **VPS memory:** start at 4 vCPU / 8 GB (InnoDB buffer pool 3 GB); plan to **16 GB** before cycle 2 begins, so the hot
  indexes stay resident. Roughly a year of runway.
- **Archival becomes a real Phase-10 task, not a theoretical one:** `quiz_attempt_answers` for *completed* cycles can be
  moved to a cold table once results are final. Reversible, scheduled, documented.

The `EXPLAIN` baselines in `docs/phase-1/03-database-design.md` §8.3 must be re-captured against a **36 M-row** seeded
dataset rather than the 7 M one originally specified.

---

## 9. What the student sees

```
Programme: FMGE Complete            Cycle 1 of 2          38% complete
──────────────────────────────────────────────────────────────────────
✓ Level 1   Month 1   30/30 days   Test: 268/300 (89%)    completed
◉ Level 2   Month 2   17/30 days   Day 18 available       in progress
🔒 Level 3   Month 3   locked — complete Level 2 to unlock
🔒 Level 4 · 5 · 6
```

Bottom navigation, daily quiz flow, mistake review and progress charts are unchanged from Phase 1 — they simply operate
within the current level. One addition: a **programme overview** screen showing the six levels and cycle position, which
becomes the natural home for the streak and overall-progress display.
