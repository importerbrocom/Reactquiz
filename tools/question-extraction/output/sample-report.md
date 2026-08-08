# Extraction report

- Pages: n/a
- Text layer: n/a (pre-extracted text)
- Questions detected: **4**
- Expected: 4 — matches
- Ready to import: **2**
- Need review: **2**
- Duplicate question hashes: 0

## Blocking issues

| Issue | Rows |
|---|---|
| `missing_explanation` | 2 |
| `missing_option_b` | 1 |

## Warnings (imported, but worth a look)

| Warning | Rows |
|---|---|
| `pin_last_option_4` | 2 |
| `short_explanation` | 1 |
| `cross_reference_option_1` | 1 |

## Shuffle safety (ADR 003)

- `All/None of the above` style options: **2** — these are pinned to the last position, other choices shuffle around them.
- Options referencing other options (e.g. "Both 1 and 2"): **1** — shuffling is disabled for these questions.

  - Ques 3 (QuesID 799624)

## Repeating lines removed as page furniture / watermark

- `Placeholder`

## What was NOT done

- **No explanation was written by the tool.** Rows with `missing_explanation` are listed on the `needs_review` sheet for a subject-matter expert. Inventing medical rationale would look convincing and could teach a student something wrong.
- No answer was inferred. An unresolvable `Ans:` is flagged, not guessed.
