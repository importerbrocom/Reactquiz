# Extraction report

- Pages: 102
- Text layer: yes (799 chars/page)
- Questions detected: **250**
- Expected: 250 — matches
- Ready to import: **247**
- Need review: **3**
- Duplicate question hashes: 0

## Blocking issues

| Issue | Rows |
|---|---|
| `figure_referenced_but_missing` | 2 |
| `identical_options` | 1 |

## Warnings (imported, but worth a look)

| Warning | Rows |
|---|---|
| `missing_explanation` | 250 |
| `pin_last_option_4` | 13 |
| `cross_reference_option_3` | 2 |

## Shuffle safety (ADR 003)

- `All/None of the above` style options: **13** — these are pinned to the last position, other choices shuffle around them.
- Options referencing other options (e.g. "Both 1 and 2"): **2** — shuffling is disabled for these questions.

  - Ques 76 (QuesID 799828)
  - Ques 90 (QuesID 799883)

## What was NOT done

- **No explanation was written by the tool.** Rows with `missing_explanation` are listed on the `needs_review` sheet for a subject-matter expert. Inventing medical rationale would look convincing and could teach a student something wrong.
- No answer was inferred. An unresolvable `Ans:` is flagged, not guessed.
