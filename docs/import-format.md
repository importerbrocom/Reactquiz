# Question Bulk Import — Format Specification

Replaces the PDF extraction module. Admins upload **.xlsx** (preferred) or **.csv**, review a validated preview, then
approve. Images are attached afterwards in the question editor.

**Ready-to-fill files:**
- `docs/templates/questions-import-template.xlsx` ← give this to whoever writes the questions
- `docs/templates/questions-import-template.csv` — same columns, for tooling and tests
- `docs/templates/build-template.py` — regenerates both; the single source of truth for the template's shape

> Prefer **.xlsx**. Question text and explanations contain commas, quotes and apostrophes constantly, all of which are
> escaping hazards in CSV. XLSX has no escaping rules to get wrong, and the template ships with dropdowns that stop
> typos at the source. If you must use CSV, save as **UTF-8 with BOM** (Excel: *Save As → CSV UTF-8*), otherwise
> symbols and accents import as mojibake.

---

## 1. Sheet layout

The workbook has three sheets:

| Sheet | Purpose |
|---|---|
| **`questions`** | Where you type. Header row is frozen; required columns are shaded |
| `instructions` | Twelve rules, readable without leaving Excel |
| `topics` | The subject list that feeds the `topic` dropdown. Edit it to suit your exam |

- **Row 1 = header.** Names are matched case-insensitively and ignore spaces/underscores, so `Correct Option`,
  `correct_option` and `CORRECTOPTION` all work.
- **Column order does not matter.** Unknown columns are ignored, so you can keep private working notes in the file.
- Blank rows are skipped. The import aborts if more than 20 consecutive rows are blank (guards against a 5,000-row file
  containing 40 real questions).
- **Rows 2–9 of the template are worked examples — delete them before importing.**

---

## 2. Columns

| Column | Required | Allowed values | Notes |
|---|---|---|---|
| `question_no` | no | integer | Your own reference. Not stored; it appears in the error report so you can find the row in your sheet |
| `question` | **yes** | text, 10–2000 chars | The question. Line breaks allowed (Alt+Enter in Excel) |
| `option_a` | **yes** | text, 1–500 chars | Shown to students as **A** |
| `option_b` | **yes** | text, 1–500 chars | Shown as **B** |
| `option_c` | **yes** | text, 1–500 chars | Shown as **C** |
| `option_d` | **yes** | text, 1–500 chars | Shown as **D** |
| `correct_option` | **yes** | `A` `B` `C` `D` | Dropdown in the template. Also accepts lower case, `1`–`4`, and forms like `(B)` or `B.` |
| `explanation` | **yes** | text, 5–2000 chars | Shown after a wrong answer. Required by design — your business rules say a wrong answer must show an explanation, so a row without one is rejected rather than silently imported |
| `topic` | recommended | text from the `topics` sheet | Powers the weak/strong subject analysis and day-to-day subject variety. Dropdown in the template so spellings stay consistent |
| `tags` | no | semicolon-separated | Finer labels: `autonomic;beta-blockers;high-yield` |
| `source` | no | text, ≤ 160 chars | e.g. `FMGE 2023 Paper 1`. Useful for audit and filtering |
| `image_filename` | no | filename | Leave blank unless you are bulk-attaching images via ZIP — see §7 |
| `status` | no | `active` `draft` | Default `active`. Use `draft` for questions written but not ready to serve |

**Not columns, deliberately:**

| Absent field | Why |
|---|---|
| `quiz_day` | **The system assigns days per student.** Put all 300 questions for a level in one file; each student receives a personal shuffle of them across the 30 days. There is nothing for you to assign |
| `difficulty` | Removed. Difficulty is **observed**, not declared — the system computes it from how many students actually get each question wrong, which is more accurate than a guess at authoring time |
| `correct_answer_text` | Auto-filled from whichever option you marked correct |

---

## 3. Validation

Every row is validated before anything is written, and approval is all-or-nothing per batch, so a bad file can never
leave you with half a level.

**Row rejected:**

| Error code | Meaning |
|---|---|
| `missing_question` | `question` empty or under 10 characters |
| `missing_option` | any of `option_a`–`option_d` empty |
| `missing_correct_option` | `correct_option` empty |
| `invalid_correct_option` | not resolvable to A–D |
| `missing_explanation` | `explanation` empty or under 5 characters |
| `duplicate_in_file` | another row in this file has the same question hash |
| `duplicate_in_level` | an active question with the same hash already exists in the target level |
| `identical_options` | two or more choices have identical text (usually a copy-paste slip) |
| `text_too_long` | a field exceeds its limit |
| `unsafe_html` | disallowed markup detected — see §6 |

**Row imported with a warning:**

| Warning code | Meaning |
|---|---|
| `no_topic` | `topic` blank — subject analytics and day variety both degrade |
| `similar_topic` | near-match of an existing topic; suggests the existing spelling (`Anotomy` → `Anatomy`) |
| `non_canonical_topic` | topic is not on the `topics` sheet — accepted, but flagged so typos don't fragment your reports |
| `short_explanation` | under 25 characters — probably not useful to a student |
| `long_question` | over 600 characters — will need scrolling on a phone |
| `image_not_found` | `image_filename` given but no matching file in the uploaded ZIP |
| `pool_size_mismatch` | file-level: the level's total active questions is not exactly `total_quiz_days × daily_question_count` (300). See §5 |

### Duplicate detection

```
question_hash = sha256(
    normalise(question) + '|' + sorted([normalise(a), normalise(b), normalise(c), normalise(d)])
)
normalise(x) = lowercase, trim, collapse internal whitespace
```

A question is a duplicate when the text and the *set* of choices match — so reordered choices, different capitalisation
and stray spacing are all still caught, while a genuinely reworded question is not. A **unique database constraint** on
`(level_id, question_hash)` makes duplicates impossible even if two admins upload the same file simultaneously.

This is also what makes re-uploading safe: fix your sheet, upload the whole thing again, and only the new or changed
rows come through.

---

## 4. No day assignment

You do **not** decide which question lands on which day. Each student gets their own shuffle of the full 300 across
their 30 days, so no two students share a Day 1. Details in `docs/adr/001-per-student-question-assignment.md`.

Practical consequences for you:

- Upload questions in any order. Sheet order is irrelevant.
- Upload in batches if convenient — 50 at a time into the same level is fine.
- The **only** number that matters is the level total: exactly **300** active questions.

---

## 5. How many questions — one file per level

Questions belong to a **level** (one month: 30 days × 10 questions). Six levels make a **programme**:

```
Exam type: FMGE
└── Programme: FMGE Complete — 6 Months        1,800 questions total
    ├── Level 1  (Month 1)   300 questions   ← one import file
    ├── Level 2  (Month 2)   300 questions   ← one import file
    ├── …
    └── Level 6  (Month 6)   300 questions
```

Each level needs **exactly 300 active questions**, because every student must complete all of that level's questions
across its 30 days.

| Level total | What happens |
|---|---|
| Under 300 | The level cannot be published and students cannot reach it. The Readiness screen shows exactly how many more are needed |
| **Exactly 300** | Correct. Every student completes all 300 across 30 days, in their own personal order |
| Over 300 | Allowed, but each student is dealt only 300, so the surplus goes unused for them. You get a `pool_size_mismatch` warning. Only do this deliberately |

Upload in as many files as you like per level — 50 at a time is fine. The level total is what counts, and the Readiness
screen shows a live count.

### You do not need 1,800 questions to launch

You need **300**. Each level gives you roughly a month of runway to write the next one:

| Milestone | Total needed | Time from launch |
|---|---|---|
| Launch Level 1 | 300 | — |
| Before anyone reaches Level 2 | 600 | ~31 days |
| Before Level 3 | 900 | ~62 days |
| Before Level 4 | 1,200 | ~93 days |
| Before Level 5 | 1,500 | ~124 days |
| Before Level 6 | 1,800 | ~155 days |
| Cycle 2 (the same 1,800 repeat, re-shuffled) | **0 new** | ~186 days |

Required pace: **300 per month ≈ 10 per day.** The admin dashboard shows a **content runway** card — how many days until
your fastest student reaches an unpublished level — so you always have warning.

---

## 6. Formatting inside question text

Plain text is safest. A small subset is supported and sanitised on import:

**Kept:** `<b> <strong> <i> <em> <u> <sup> <sub> <br>` and line breaks — so `H<sub>2</sub>O` and `m<sup>2</sup>` survive,
which matters for science subjects.

**Stripped or rejected:** `<script> <iframe> <style> <a>`, `on*` event attributes, `javascript:` URLs, inline styles.
Word and Google Docs cruft (`<span style=…>`, `<o:p>`, smart-quote artefacts) is normalised automatically, so pasting
from either is fine.

### Never name an option letter in an explanation

**Answer choices are reshuffled every time a student sees the question.** This is deliberate: it stops students passing
by remembering "the answer is the second one" instead of learning the material, which is exactly what fails them in the
real exam. Full reasoning in `docs/adr/003-per-exposure-option-shuffling.md`.

So a letter in an explanation will be **wrong** for most students, most of the time:

| | Example |
|---|---|
| ❌ | `The correct answer is option D.` |
| ❌ | `Options A and C are distractors.` |
| ❌ | `B is wrong because the pressure is lower.` |
| ✅ | `The left ventricle is correct because it must generate systemic pressure. The right ventricle pumps only to the lungs, at roughly one-fifth of that pressure.` |

**Always name the answer text.** The importer flags likely violations
(`explanation_references_option_letter`), but it cannot catch every phrasing — brief whoever writes the questions before
they start. It is the better habit anyway: an explanation naming the concept teaches, one naming a letter only scores.

### Options that reference other options

| What you wrote | What happens |
|---|---|
| `All of the above`, `None of the above`, `All of these`, `None of these` | Automatically **pinned to the last position** while the other three shuffle. Safe to use |
| `Both A and B`, `A and C only` | Cannot be shuffled — the letters carry the meaning. Shuffling is switched off for that one question and you get an `options_reference_letters` warning. Better: rewrite as `Both hypertension and diabetes` |
| Anything else | Shuffled normally |

---

## 7. Images

**A. Attach later in the editor** — the normal route. Leave `image_filename` blank, import, then open any question in
the admin editor and upload an image. It is converted to WebP at two sizes (900 px display, 400 px thumbnail) and its
real dimensions are stored, so the layout never shifts while the image loads.

**B. Bulk attach via ZIP** — put filenames in `image_filename` (`nephron-labelled.png`) and upload a ZIP of images
alongside the workbook. Matching is case-insensitive and ignores folder structure inside the ZIP. Unmatched names give
an `image_not_found` warning, not a failure; the question imports fine and you can attach the image later.

Rules: `jpg` `jpeg` `png` `webp`, max 8 MB and 6000×6000 px each. **SVG is rejected** (security). Alt text is requested
in the editor when an image carries meaning the question text does not.

---

## 8. Import flow

```
1  Upload .xlsx/.csv (+ optional image ZIP)   →  POST /api/v1/admin/question-imports
2  Every row parsed and validated             →  status: validating → needs_review
3  Preview screen
      counts: valid / warnings / rejected / duplicates
      table sorted worst-first, each row showing its error or warning
      fix rows inline — no need to re-upload for a typo
4  Pick the target programme and level
5  Approve  →  bulk insert in chunks of 500, one transaction per chunk
6  status: completed  →  audit entry, level content_version bumped, caches invalidated
7  Rejected rows downloadable as CSV with an added `error` column, so you fix and re-upload
   only the failures
```

A 300-row file validates in under two seconds and commits in well under one. It runs on a queue so the browser never
waits on a long request, but at this size it feels instant.

---

## 9. Export uses the same shape

`Questions → Export` produces a file with **identical columns**, so export → edit in Excel → re-import is a safe round
trip. Exports include an extra `id` column: keep it and the importer **updates** those questions instead of creating new
ones; delete it and they are treated as new questions (and caught as duplicates if unchanged).

---

## 10. Canonical topic list

On the `topics` sheet, and used for the dropdown. Edit it freely — it is not hard-coded anywhere. Defaults suit
FMGE / AMC style medical licensing exams:

**Pre-clinical** — `Anatomy` · `Physiology` · `Biochemistry`

**Para-clinical** — `Pathology` · `Pharmacology` · `Microbiology` · `Forensic Medicine` · `Community Medicine`

**Clinical** — `General Medicine` · `General Surgery` · `Obstetrics & Gynaecology` · `Paediatrics` ·
`Orthopaedics` · `ENT` · `Ophthalmology` · `Psychiatry` · `Dermatology` · `Anaesthesia` · `Radiology`

Keep `topic` for the subject and put finer grain in `tags`: `topic = Pharmacology`,
`tags = autonomic;beta-blockers;high-yield`.

Because the 300 questions are dealt out proportionally, **the subject mix you author is the mix students experience.**
If the bank is 60% Anatomy, every student's month is 60% Anatomy. Build the pool to mirror the real exam paper.

---


## 11. Recommended settings

Each exam type is an **exam category** the admin creates (FMGE, AMC, NEET-PG, or anything else). Under it sit one or more
**programmes**, and each programme holds six monthly **levels**. Nothing about any exam is hard-coded — you can add exam
types and programmes at any time, and students choose which one they are preparing for during onboarding.

**Per level** (one month)

| Setting | Value | Note |
|---|---|---|
| `total_quiz_days` | 30 | |
| `daily_question_count` | 10 | |
| Questions required | **300** | Enforced by the Readiness check before the level can be published |
| Daily retries | **Unlimited** | A wrong answer reveals the correct answer and the explanation, then the question returns for another attempt |
| Month-end test day | 31 | |
| Month-end test size | 300 | That level's own questions, re-shuffled |
| `level_test_attempt_limit` | **0 = unlimited** | Every attempt re-shuffles the order |
| `pass_percentage` | 50 (FMGE) / 60 (AMC) | Confirm against the current official standard |
| Time limit | 300 min, or blank for untimed | Roughly one minute per question |
| Negative marking | **None** | Not implemented |
| Answer-order shuffling | **Not used** | Choices always appear exactly as you authored them |
| Pause during the test | off | Makes it feel like the real exam |

**Per programme** (six months)

| Setting | Value | Note |
|---|---|---|
| `total_levels` | 6 | 1,800 questions in total |
| `total_cycles` | 2 | The same 1,800 questions repeat once, freshly shuffled. `0` = repeat indefinitely |
| `cycle_reshuffle_scope` | `within_level` | Cycle 2's Level 1 re-deals Level 1's own 300. Switch to `across_programme` if your levels are arbitrary batches rather than a subject sequence |
| `require_test_pass_to_advance` | off | Completing the 30 days and submitting the month-end test is enough to advance |
| `next_programme_id` | — | Point this at a new-bank programme when one is ready |

See `docs/adr/002-programme-levels-and-cycles.md` for the full structure, and
`docs/adr/001-per-student-question-assignment.md` for how the per-student shuffle works inside a level.
