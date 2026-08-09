# Question extraction tool

Converts a question-bank PDF into the import spreadsheet defined by
[`docs/import-format.md`](../../docs/import-format.md).

**This is a developer tool, not part of the application.** It lives outside `api/`
and is never deployed. Production only ever ingests the validated spreadsheet this
produces, which is why PDF parsing — and every one of its failure modes — stays out
of the running system.

## Usage

```bash
pip install pypdf pdfplumber openpyxl

python3 extract.py input/bank.pdf \
    --out output/questions-level-1.xlsx \
    --expected 300 \
    --strip "WATERMARK TEXT"          # optional, repeatable
```

Outputs:

| File | Contents |
|---|---|
| `questions-level-1.xlsx` | `questions` sheet (ready to import) + `needs_review` sheet (rows a human must fix) |
| `questions-level-1-report.md` | Honest counts, every issue found, and what the tool refused to do |

Add `--json` for a machine-readable dump, or `--text file.txt` to run against
pre-extracted text (used by the fixture).

## Source format handled

```
Ques No: 1, QuesID: 799622
Subject: Pathology
Topic: Cell Injury
Sub-Topic:

Which of the following Ions is involved in cell injury
O1:
Calcium
O2:
Copper
O3:
Selenium
O4:
All above
Ans: 1
Explanation: ...              <- optional
```

### Field mapping

| Source | Import column | Note |
|---|---|---|
| `Ques No` | `question_no` | your reference, not stored |
| `QuesID` | `source` | written as `QuesID 799622` so any row traces back to the PDF |
| `Subject` | `topic` | normalised to the canonical subject list (`Medicine` → `General Medicine`) |
| `Topic`, `Sub-Topic` | `tags` | semicolon-separated |
| stem | `question` | multi-line stems are joined |
| `O1`–`O4` | `option_a`–`option_d` | text may sit on the label line or the next |
| `Ans: 1` | `correct_option` | `1→A 2→B 3→C 4→D`; letters also accepted |
| `Explanation:` | `explanation` | **blank is flagged, never invented** |

## Two rules the tool follows

1. **It never writes medical content.** A missing or truncated explanation becomes a
   `missing_explanation` row on `needs_review`. Fabricated rationale would read
   convincingly and could teach a future doctor something wrong.
2. **It never guesses an answer.** An unresolvable `Ans:` is flagged, not inferred.

## Shuffle safety

Because answer choices are reshuffled on every exposure
([ADR 003](../../docs/adr/003-per-exposure-option-shuffling.md)), two kinds of option
need special handling. The tool flags both, using patterns kept identical to
`App\Support\OptionTextAnalyser` so the tool and the application never disagree:

| Option text | Flag | Effect |
|---|---|---|
| `All above`, `None of these`, `Any of the above` | `pin_last_option_N` | pinned last, other choices shuffle around it |
| `Both 1 and 2`, `A and C only`, `1 & 3` | `cross_reference_option_N` | shuffling disabled for that question |

Genuine content is left alone: `60-100 bpm`, `Vitamin B12`, `1,25-dihydroxyvitamin D`
and `Type 1 and type 2 diabetes` all shuffle normally. Covered by
`api/tests/Unit/OptionTextAnalyserTest.php` (39 cases).

## Testing

```bash
python3 extract.py --text fixtures/sample.txt --out output/sample.xlsx \
    --expected 4 --strip "Placeholder"
```

The fixture deliberately contains a clean question, a missing explanation, a missing
option, a `Both 1 and 2` cross-reference, an `All above`, a multi-line stem, a
multi-line explanation and a watermark line.
