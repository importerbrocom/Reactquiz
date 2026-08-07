# ADR 003 — Per-exposure option shuffling (position-memory defence)

**Status:** Accepted
**Date:** 2026-08-03
**Supersedes:** the answer-order decisions in [ADR 001](./001-per-student-question-assignment.md) §7 and
`docs/import-format.md` §6

---

## The problem

A student meets a question, gets it wrong, is shown the correct answer, retries, and passes. Over 30 days and two
cycles they meet it several more times. What have they actually stored?

> "Question about the aorta → the answer is **B**."

They have learned a **position**, not a fact. In the real FMGE or AMC paper the same question appears with the choices in
a different order, they select B out of habit, and they fail a question they believed they knew.

This is a well-known failure mode of repetitive MCQ drilling, and it is worse in this product than in most, because our
design deliberately shows the correct answer and allows unlimited retries — which is excellent for teaching and terrible
for position memory, unless we defend against it.

## Why the earlier proposal did not solve it

ADR 001 originally proposed shuffling option order **per student**, stable for that student's whole programme. That
defends against students *sharing* answers with each other ("it's B"), but it does nothing about the actual problem: each
individual student still sees a fixed layout every single time, so position memory forms exactly as before.

**The variable that matters is not *who* is looking — it is *how many times* they have looked.**

---

## Decision

### Fix 1 — Shuffle option order on every exposure (essential)

Option order is derived from a seed that **changes every time the question is served after a submission**:

```
option_order = permutation( hash(attempt_id, question_id, submission_count) )
```

| Situation | `submission_count` | Result |
|---|---|---|
| First serve of the question in an attempt | 0 | Order X |
| Page refresh, resume, offline reload — before submitting | 0 | **Order X, unchanged** |
| After a wrong answer, retrying | 1 | Order Y |
| Third look | 2 | Order Z |
| Month-end test (different attempt id) | 0 | Different again |
| Cycle 2 (different attempt id) | 0 | Different again |

The stability property matters as much as the shuffling: while the student is *looking at* a question, the order is
fixed, so a refresh, an offline reload or a resume never reorders under them. It only changes once they have submitted an
answer and are being asked again.

`quiz_attempt_questions.submission_count` already exists in the schema, so this needs **no new columns and no extra
writes.** Nothing is persisted, because it does not need to be — see §3.

### Fix 2 — Requeue wrong questions instead of retrying immediately (strongly recommended)

Today's flow: wrong answer → correct answer revealed → "Try again" → student clicks the answer they were just shown →
marked mastered.

Even with shuffled options, that is an echo, not recall. Change it to:

```
Day 7, first pass:  Q1 ✓  Q2 ✗  Q3 ✓  Q4 ✓  Q5 ✗  Q6 ✓  Q7 ✓  Q8 ✓  Q9 ✗  Q10 ✓
                    → "7 of 10 mastered. 3 to revisit."
Recall pass:        Q2 (reordered)  Q5 (reordered)  Q9 (reordered)
                    → any still wrong go round again
Day complete when all 10 are correct — the rule is unchanged
```

The student still gets unlimited attempts and still must reach 10/10, but between seeing the answer and being asked
again there is now other material. That gap is what converts recognition into recall, and it costs the student nothing
extra in time.

`levels.retry_mode = 'requeue_at_end' | 'immediate'` — default `requeue_at_end`. The correct answer and explanation are
still shown immediately on a wrong answer, exactly as your business rules require. Only the moment of *re-asking* moves.

### Fix 3 — Daily recall check (optional, off by default)

`levels.recall_check_count` (default `0`). When set to 2–3, each day opens with that many questions the student
previously got wrong on *earlier* days, drawn oldest-weakest-first. They do **not** count toward the day's ten and cannot
block completion; they exist purely to interrupt forgetting.

Off by default because it lengthens each session. Worth enabling once you have real drop-off data.

---

## 1. How the API expresses this

The canonical option key always travels with its text, and the **client never sends a position**:

```json
{
  "id": 8421,
  "question_text": "Which chamber of the heart pumps oxygenated blood into the aorta?",
  "options": [
    { "key": "c", "text": "Left atrium" },
    { "key": "a", "text": "Right atrium" },
    { "key": "d", "text": "Left ventricle" },
    { "key": "b", "text": "Right ventricle" }
  ]
}
```

The client renders in array order, labels them A–D **by position**, and submits `selected_option: "d"` — the canonical
key of whatever the student tapped.

Server-side evaluation is therefore **completely unchanged**: one comparison against `questions.correct_option`. There is
no position-to-key translation anywhere in the codebase, which matters because this is the most correctness-critical line
in the application and it must stay trivially auditable.

Seeds when no attempt row exists yet (the day-fetch endpoint can be called before an attempt is created):

```
daily quiz      hash(level_enrollment_id, day_number, attempt_number, submission_count, question_id)
month-end test  hash(level_test_attempt_id, question_id)          # one exposure per attempt
practice        hash(practice_session_id, question_id, submission_count)
```

---

## 2. Consequences for the UI

- **Reordering must be announced.** When the options reorder for a retry, an `aria-live="polite"` region announces
  "Answer choices have been reordered" — otherwise a screen-reader or low-vision user is silently disoriented. This is an
  accessibility requirement, not a nicety.
- **Sighted users get a brief visual cue** — a 150 ms cross-fade on the option list plus a one-line hint on the first
  occurrence per session: *"Choices are reshuffled each time, so read them again."* Framing it as intentional prevents it
  reading as a bug.
- **Position labels stay** (A/B/C/D) for keyboard shortcuts and screen-reader clarity. They are harmless once they carry
  no stable meaning.
- **The review screen renders by key, not position**, so "you answered *Right ventricle*" is always accurate regardless
  of what order the student saw at the time.

---

## 3. Why nothing is persisted

We store the **canonical key** the student selected, never the position they tapped. Every downstream view — mistake
review, month-end result breakdown, admin inspection — reconstructs correctly from the key alone. So there is no need to
record the historical display order, which means:

- no new columns, no extra writes on the hottest path in the app;
- no risk of a future PHP or hash-function change corrupting stored history, because nothing about the past depends on
  reproducing a permutation.

This is a deliberate contrast with question *assignment* (ADR 001), which **is** persisted, because which questions a
student was given is an exam record and must survive any code change.

---

## 4. Questions that cannot be shuffled

Some options reference other options, and shuffling breaks them. Handled at import:

| Option text pattern | Handling |
|---|---|
| `All of the above`, `None of the above`, `All of these`, `None of these` | `question_options.pin_last = 1` — that option is pinned to the final position and the rest shuffle around it. Still works |
| `Both A and B`, `A and C only`, `Only B and D` | **Cannot be shuffled** — the letters are part of the meaning. The importer sets `questions.shuffle_options = 0` for that question and raises an `options_reference_letters` warning suggesting a rewrite (`Both hypertension and diabetes`) |
| Everything else | Shuffled normally |

New columns: `questions.shuffle_options` (default `1`) and `question_options.pin_last` (default `0`). Detection happens
at import and is re-checked whenever an admin edits a question, so a later edit cannot silently create a broken item.

You confirmed your source material contains no "All of the above" items, so in practice this is a safety net rather than
a common path — but medical banks accumulate them over time, and it is far cheaper to handle now than to discover a
mis-scored question later.

---

## 5. Consequences for content authoring — the rule returns

**Explanations must not name option letters.** This rule was in an earlier draft, removed when option order was fixed,
and now returns because order is no longer fixed. Since no questions have been written yet, the timing is fortunate.

| | |
|---|---|
| ❌ | `The correct answer is option D.` |
| ❌ | `Options A and C are distractors.` |
| ❌ | `B is wrong because the pressure is lower.` |
| ✅ | `The left ventricle is correct because it must generate systemic pressure. The right ventricle pumps only to the lungs, at roughly one-fifth of that pressure.` |

Always name the **answer text**. The importer raises `explanation_references_option_letter` on likely violations, but it
cannot catch every phrasing, so brief whoever writes the bank before they start.

This is a better habit regardless: an explanation that names the concept teaches, while one that names a letter only
scores.

---

## 6. What this does and does not fix

**Fixed:** position memory. A student cannot pass by remembering "it's the second one", because it will not be the second
one next time. To answer correctly they must recognise the answer *text* — which is what the real exam tests.

**Also improved:** answer-sharing between students. Two students comparing notes cannot exchange letters, only content —
and discussing content is learning, so that is a feature.

**Not fixed — and worth being honest about:** a student can still memorise the *answer text* for a specific question
without understanding the underlying concept ("aorta question → left ventricle"). No shuffling scheme can prevent that;
it is inherent to a fixed question bank. The mitigations are pedagogical rather than technical:

- Explanations that teach the mechanism rather than restating the answer (§5).
- Fix 2's recall gap, so retrieval is practised rather than recognition.
- Enough questions per concept that the concept, not the item, is what gets learned.
- Cycle 2 at a six-month remove, which is genuinely a spaced-repetition interval.

Worth tracking once live: **first-attempt accuracy in cycle 2 versus cycle 1** for the same student and question.
`student_question_progress.first_attempt_correct` already records what is needed. Rising cycle-2 first-attempt accuracy
means real retention; flat accuracy means the bank is being memorised item-by-item and needs more coverage per concept.
That single metric is the honest measure of whether the product teaches.

---

## 7. Summary of schema changes

```sql
-- questions
shuffle_options TINYINT(1) NOT NULL DEFAULT 1,     -- 0 for items whose options reference letters

-- question_options
pin_last TINYINT(1) NOT NULL DEFAULT 0,            -- "All of the above" stays last

-- levels
retry_mode        VARCHAR(20) NOT NULL DEFAULT 'requeue_at_end',  -- requeue_at_end | immediate
recall_check_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,          -- Fix 3, off by default
```

No changes to `quiz_attempt_questions`, `quiz_attempt_answers`, `enrollment_day_questions` or the evaluation path.
`submission_count` — already present — is the whole mechanism.
