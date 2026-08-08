#!/usr/bin/env python3
"""
Convert a question-bank PDF into the import spreadsheet defined by
docs/import-format.md.

This is a DEVELOPER TOOL. It lives outside api/ and is never deployed — the
application only ever ingests the validated spreadsheet this produces. That
keeps PDF parsing (and its failure modes) entirely out of production.

Source format handled (one bordered table per question):

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
    Explanation: ...            <- optional

Usage:
    python3 extract.py input/bank.pdf --out output/questions-level-1.xlsx
    python3 extract.py --text fixtures/sample.txt --out output/sample.xlsx
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from collections import Counter
from dataclasses import dataclass, field
from pathlib import Path

# --------------------------------------------------------------------------- #
# Import-format contract (must mirror docs/import-format.md)
# --------------------------------------------------------------------------- #

COLUMNS = [
    "question_no", "question", "option_a", "option_b", "option_c", "option_d",
    "correct_option", "explanation", "topic", "tags", "source",
    "image_filename", "status",
]

# Canonical subject list from docs/import-format.md section 10.
CANONICAL_TOPICS = {
    "anatomy": "Anatomy", "physiology": "Physiology", "biochemistry": "Biochemistry",
    "pathology": "Pathology", "pharmacology": "Pharmacology",
    "microbiology": "Microbiology", "forensic medicine": "Forensic Medicine",
    "community medicine": "Community Medicine", "psm": "Community Medicine",
    "preventive and social medicine": "Community Medicine",
    "general medicine": "General Medicine",
    "medicine": "General Medicine", "general surgery": "General Surgery",
    "surgery": "General Surgery", "obstetrics & gynaecology": "Obstetrics & Gynaecology",
    "obstetrics and gynaecology": "Obstetrics & Gynaecology",
    "gynaecology & obstetrics": "Obstetrics & Gynaecology",
    "gynecology & obstetrics": "Obstetrics & Gynaecology",
    "obstetrics & gynecology": "Obstetrics & Gynaecology",
    "obg": "Obstetrics & Gynaecology", "gynaecology": "Obstetrics & Gynaecology",
    "paediatrics": "Paediatrics", "pediatrics": "Paediatrics",
    "orthopaedics": "Orthopaedics", "orthopedics": "Orthopaedics",
    "ent": "ENT", "ophthalmology": "Ophthalmology", "psychiatry": "Psychiatry",
    "dermatology": "Dermatology", "anaesthesia": "Anaesthesia",
    "anesthesia": "Anaesthesia", "radiology": "Radiology",
}

OPTION_KEYS = ["a", "b", "c", "d"]

# Watermark discrimination thresholds (see is_watermark_char).
WATERMARK_MIN_SIZE = 40.0
WATERMARK_MIN_LIGHTNESS = 0.8

# --------------------------------------------------------------------------- #
# Patterns
# --------------------------------------------------------------------------- #

RE_BLOCK_START = re.compile(
    r"^\s*Ques(?:tion)?\s*No\.?\s*:?\s*(\d+)\s*(?:,\s*Ques(?:tion)?\s*ID\s*:?\s*(\w+))?",
    re.IGNORECASE,
)
RE_SUBJECT = re.compile(r"^\s*Subject\s*:\s*(.*)$", re.IGNORECASE)
RE_TOPIC = re.compile(r"^\s*Topic\s*:\s*(.*)$", re.IGNORECASE)
RE_SUBTOPIC = re.compile(r"^\s*Sub-?\s*Topic\s*:\s*(.*)$", re.IGNORECASE)
RE_OPTION = re.compile(r"^\s*(?:O|Option)\s*([1-9])\s*[:.)]\s*(.*)$", re.IGNORECASE)
RE_ANSWER = re.compile(
    r"^\s*(?:Ans|Answer|Correct\s*Answer|Key)\s*[:.\-]?\s*"
    r"\(?\s*([1-9]|[A-Da-d])\s*\)?\s*$",
    re.IGNORECASE,
)
RE_EXPLANATION = re.compile(
    r"^\s*(?:Explanation|Exp|Solution|Sol|Rationale|Reason|Note)\s*[:.\-]\s*(.*)$",
    re.IGNORECASE,
)

# Options that reference other options break per-exposure shuffling (ADR 003).
# This source numbers options O1..O4, so cross-references use DIGITS as well as
# letters — "Both 1 and 2" is as common here as "Both A and B" elsewhere.
# Kept byte-for-byte equivalent to App\Support\OptionTextAnalyser so the tool's
# verdicts and the application's agree. A cross-check test asserts this.
RE_ALL_OR_NONE = re.compile(
    r"^\s*(?:all|none|any)\s+(?:of\s+)?(?:the\s+)?"
    r"(?:above|below|these|those|option|options|answers?|statements?)"
    r"(?:\s+(?:are|is))?(?:\s+(?:correct|true|right))?\s*[.!]?\s*$",
    re.IGNORECASE,
)
RE_CROSS_REFERENCE = re.compile(
    r"^\s*(?:both|only|either|neither)?\s*"
    r"\(?[1-4a-dA-D]\)?\s*"
    r"(?:(?:,|&|\+|and|or)\s*\(?[1-4a-dA-D]\)?\s*)+"
    r"(?:only|alone)?(?:\s+(?:are|is))?(?:\s+(?:correct|true|right))?\s*[.!]?\s*$",
    re.IGNORECASE,
)
CROSS_REFERENCE_MAX_LEN = 48

# Stems that depend on a figure. Deliberately conservative: 'image is formed at'
# (optics) and 'PR interval in ECG' are about concepts, not pictures.
RE_MENTIONS_FIGURE = re.compile(
    r"(shown\s+(?:in|below)|given\s+below|in\s+the\s+(?:image|figure|picture)\s+below"
    r"|following\s+(?:image|figure|photograph)|fundus\s+picture|as\s+shown)",
    re.IGNORECASE,
)

# --------------------------------------------------------------------------- #


@dataclass
class ParsedQuestion:
    source_no: str = ""
    source_id: str = ""
    page: int = 0
    stem: str = ""
    options: dict[str, str] = field(default_factory=dict)   # "1".."4" -> text
    answer_raw: str = ""
    explanation: str = ""
    subject: str = ""
    topic: str = ""
    subtopic: str = ""
    figure: str = ""
    errors: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    # -- derived ----------------------------------------------------------- #

    @property
    def correct_letter(self) -> str:
        """Ans: 1 -> 'A'. Ans: B -> 'B'. Blank if unresolvable."""
        raw = self.answer_raw.strip()
        if raw.isdigit():
            idx = int(raw) - 1
            return OPTION_KEYS[idx].upper() if 0 <= idx < 4 else ""
        if raw.lower() in OPTION_KEYS:
            return raw.upper()
        return ""

    @property
    def canonical_topic(self) -> str:
        return CANONICAL_TOPICS.get(self.subject.strip().lower(), self.subject.strip())

    @property
    def tags(self) -> str:
        parts = [p.strip() for p in (self.topic, self.subtopic) if p and p.strip()]
        return ";".join(parts)

    @property
    def question_hash(self) -> str:
        """Mirrors App\\Models\\Question::makeHash so local dedupe matches the API."""
        def norm(v: str) -> str:
            return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", "", v)).strip().lower()

        opts = sorted(norm(v) for v in self.options.values())
        return hashlib.sha256(
            (norm(self.stem) + "|" + "|".join(opts)).encode()
        ).hexdigest()

    def validate(self, require_explanation: bool = True) -> None:
        """Apply the import spec. Flags rather than guesses — never invents content."""
        if len(self.stem) < 10:
            self.errors.append("missing_question")

        for i, key in enumerate(("1", "2", "3", "4"), start=0):
            if not self.options.get(key, "").strip():
                self.errors.append(f"missing_option_{OPTION_KEYS[i]}")

        if not self.answer_raw:
            self.errors.append("missing_correct_option")
        elif not self.correct_letter:
            self.errors.append("invalid_correct_option")

        # Explanations are required by the import spec. A missing one is reported,
        # never filled in: fabricating medical rationale would look convincing and
        # teach a future doctor something wrong.
        if not self.explanation.strip():
            (self.errors if require_explanation else self.warnings).append("missing_explanation")
        elif len(self.explanation.strip()) < 25:
            self.warnings.append("short_explanation")

        texts = [v.strip().lower() for v in self.options.values() if v.strip()]
        if len(texts) != len(set(texts)):
            self.errors.append("identical_options")

        if self.subject and self.subject.strip().lower() not in CANONICAL_TOPICS:
            self.warnings.append("non_canonical_topic")
        if not self.subject.strip():
            self.warnings.append("no_topic")

        # Shuffle-safety (ADR 003).
        for key, text in self.options.items():
            t = text.strip()
            if RE_CROSS_REFERENCE.match(t) and len(t) <= CROSS_REFERENCE_MAX_LEN:
                self.warnings.append(f"cross_reference_option_{key}")
            elif RE_ALL_OR_NONE.match(t):
                self.warnings.append(f"pin_last_option_{key}")

        if len(self.stem) > 600:
            self.warnings.append("long_question")

    def to_row(self) -> dict[str, str]:
        return {
            "question_no": self.source_no,
            "question": self.stem,
            "option_a": self.options.get("1", ""),
            "option_b": self.options.get("2", ""),
            "option_c": self.options.get("3", ""),
            "option_d": self.options.get("4", ""),
            "correct_option": self.correct_letter,
            "explanation": self.explanation,
            "topic": self.canonical_topic,
            "tags": self.tags,
            # QuesID is preserved so any row can be traced back to the source PDF
            # during subject-matter review.
            "source": f"QuesID {self.source_id}" if self.source_id else "",
            "image_filename": self.figure,
            "status": "active" if not self.errors else "draft",
        }


# --------------------------------------------------------------------------- #
# Text acquisition
# --------------------------------------------------------------------------- #

def probe_pdf(path: Path) -> dict:
    import pypdf

    reader = pypdf.PdfReader(str(path))
    info = {
        "pages": len(reader.pages),
        "encrypted": reader.is_encrypted,
        "chars_total": 0,
        "chars_per_page": 0.0,
        "has_text_layer": False,
    }
    if reader.is_encrypted:
        return info

    sample = reader.pages[: min(10, len(reader.pages))]
    chars = sum(len(p.extract_text() or "") for p in sample)
    info["chars_total"] = chars
    info["chars_per_page"] = chars / max(1, len(sample))
    info["has_text_layer"] = info["chars_per_page"] >= 20
    return info


def is_watermark_char(obj: dict) -> bool:
    """
    True for glyphs belonging to a diagonal watermark.

    Filtering has to happen at the CHARACTER level, not on the assembled text. A
    large rotated watermark interleaves its individual letters into the text stream,
    so "Ans: 1" arrives as "Ans: 1 a" and "O2:" as "O2: d" — which silently corrupts
    answer keys and option text. Regex cleanup after the fact cannot reliably tell a
    stray watermark letter from real content.

    Two discriminators, either is sufficient:
      * size    — watermarks are set very large (~100pt vs 11pt body text)
      * colour  — they are near-white so they sit behind the text
    """
    if obj.get("object_type") != "char":
        return False

    if (obj.get("size") or 0) >= WATERMARK_MIN_SIZE:
        return True

    colour = obj.get("non_stroking_color")
    if isinstance(colour, (list, tuple)) and len(colour) == 3:
        if all(isinstance(c, (int, float)) and c > WATERMARK_MIN_LIGHTNESS for c in colour):
            return True

    return False


def extract_text(path: Path) -> tuple[list[tuple[int, str]], dict]:
    """
    Page-by-page text with watermark glyphs removed.

    Also records which pages carry images beyond the per-page furniture, so questions
    that depend on a figure can be flagged — the text alone would read as a complete
    question while silently missing the thing being asked about.
    """
    import pdfplumber

    pages: list[tuple[int, str]] = []
    image_pages: dict[int, int] = {}
    removed = 0

    with pdfplumber.open(str(path)) as pdf:
        for n, page in enumerate(pdf.pages, start=1):
            before = len(page.chars)
            clean = page.filter(lambda obj: not is_watermark_char(obj))
            removed += before - len(clean.chars)

            text = clean.extract_text(layout=False, x_tolerance=1.5) or ""
            if len(text.strip()) < 20:
                # Bordered tables can defeat plain text extraction; read cells directly.
                for table in clean.extract_tables() or []:
                    for row in table:
                        for cell in row:
                            if cell:
                                text += cell + "\n"

            if page.images:
                image_pages[n] = len(page.images)
            pages.append((n, text))

    return pages, {"watermark_chars_removed": removed, "image_pages": image_pages}


def strip_watermark(
    pages: list[tuple[int, str]],
    explicit: list[str] | None = None,
) -> tuple[list[tuple[int, str]], list[str]]:
    """
    Remove page furniture — running headers, footers, page numbers and the diagonal
    watermark — all of which land in the extracted text stream and would otherwise
    be swallowed into whichever field was being accumulated at the time.

    Two detection routes:
      * a line present on >=60% of PAGES (the reliable signal in a real PDF)
      * a line named explicitly with --strip

    One important guard: a candidate is never removed if the same text is also used
    as an answer option somewhere in the document. Without it, a filter would
    happily delete every "All above" — a legitimate option that appears on a large
    fraction of pages in a bank like this.
    """
    explicit_set = {e.strip() for e in (explicit or []) if e.strip()}

    page_presence: Counter[str] = Counter()
    for _, text in pages:
        for line in {l.strip() for l in text.splitlines() if l.strip()}:
            if len(line) <= 60:
                page_presence[line] += 1

    # Collect every line that follows an option label, so option text is protected.
    protected: set[str] = set()
    for _, text in pages:
        lines = [l.strip() for l in text.splitlines()]
        for i, line in enumerate(lines):
            if m := RE_OPTION.match(line):
                if inline := m.group(2).strip():
                    protected.add(inline)
                elif i + 1 < len(lines) and lines[i + 1]:
                    protected.add(lines[i + 1])

    threshold = max(2, int(len(pages) * 0.6))
    noise = {
        line for line, count in page_presence.items()
        if count >= threshold
        and line not in protected
        and not RE_BLOCK_START.match(line)
        and not RE_ANSWER.match(line)
        and not RE_OPTION.match(line)
        and not RE_SUBJECT.match(line)
        and not RE_TOPIC.match(line)
        and not RE_SUBTOPIC.match(line)
        and not RE_EXPLANATION.match(line)
    } | (explicit_set - protected)

    cleaned = []
    for n, text in pages:
        keep = [l for l in text.splitlines() if l.strip() not in noise]
        cleaned.append((n, "\n".join(keep)))
    return cleaned, sorted(noise)


# --------------------------------------------------------------------------- #
# Figures
# --------------------------------------------------------------------------- #

def extract_figures(pdf_path: Path, out_dir: Path) -> dict[str, str]:
    """
    Save question figures and map each to the question it belongs to.

    Without this, a question like "Identify the condition shown in the image below"
    imports as a complete-looking row that is actually unanswerable — the worst kind
    of defect, because nothing about it looks wrong in the spreadsheet.

    Mapping is positional: a figure belongs to the last "Ques No:" heading at or
    above it on the page, or to the question carried over from the previous page.

    @return {question_no: filename}
    """
    import pdfplumber
    import pypdf

    out_dir.mkdir(parents=True, exist_ok=True)

    # Per-page question boundaries, by vertical position.
    boundaries: dict[int, list[tuple[float, str]]] = {}
    carried: dict[int, str] = {}
    with pdfplumber.open(str(pdf_path)) as pdf:
        current = ""
        for n, page in enumerate(pdf.pages, start=1):
            carried[n] = current
            marks: list[tuple[float, str]] = []
            clean = page.filter(lambda obj: not is_watermark_char(obj))
            for line in clean.extract_text_lines() or []:
                if m := RE_BLOCK_START.match(line["text"].strip()):
                    marks.append((line["top"], m.group(1)))
                    current = m.group(1)
            boundaries[n] = marks

    reader = pypdf.PdfReader(str(pdf_path))

    # The logo repeats on every page; the most common pixel footprint is therefore it.
    sizes: Counter = Counter()
    for page in reader.pages:
        for image in page.images:
            sizes[image.image.size] += 1
    logo_size = sizes.most_common(1)[0][0] if sizes else None

    mapping: dict[str, str] = {}
    with pdfplumber.open(str(pdf_path)) as pdf:
        for n, page in enumerate(reader.pages, start=1):
            plumber_figures = [
                im for im in (pdf.pages[n - 1].images or [])
                if (round(im["width"]), round(im["height"])) != (140, 40)
            ]
            embedded = [im for im in page.images if im.image.size != logo_size]

            for index, image in enumerate(embedded):
                top = plumber_figures[index]["top"] if index < len(plumber_figures) else 0.0

                owner = carried.get(n, "")
                for mark_top, number in boundaries.get(n, []):
                    if mark_top <= top:
                        owner = number
                if not owner:
                    continue

                suffix = Path(image.name).suffix or ".png"
                filename = f"q{owner.zfill(3)}{'' if index == 0 else f'-{index + 1}'}{suffix}"
                (out_dir / filename).write_bytes(image.data)
                mapping.setdefault(owner, filename)

    return mapping


# --------------------------------------------------------------------------- #
# Parsing
# --------------------------------------------------------------------------- #

def parse(pages: list[tuple[int, str]], require_explanation: bool = True) -> list[ParsedQuestion]:
    """
    State machine over the line stream. Values may sit on the same line as their
    label or on the following line (this source uses "O1:" then the text below),
    so every field accumulates until the next recognised label.
    """
    questions: list[ParsedQuestion] = []
    current: ParsedQuestion | None = None
    mode: str | None = None          # stem | option | explanation
    option_key: str | None = None
    buffer: list[str] = []

    def flush() -> None:
        nonlocal buffer
        if current is None or mode is None:
            buffer = []
            return
        value = " ".join(" ".join(buffer).split()).strip()
        if value:
            if mode == "stem":
                current.stem = (current.stem + " " + value).strip()
            elif mode == "option" and option_key:
                current.options[option_key] = (
                    current.options.get(option_key, "") + " " + value
                ).strip()
            elif mode == "explanation":
                current.explanation = (current.explanation + " " + value).strip()
        buffer = []

    for page_no, text in pages:
        for line in text.splitlines():
            stripped = line.strip()

            if m := RE_BLOCK_START.match(stripped):
                flush()
                if current is not None:
                    questions.append(current)
                current = ParsedQuestion(
                    source_no=m.group(1),
                    source_id=(m.group(2) or ""),
                    page=page_no,
                )
                mode, option_key = "stem", None
                continue

            if current is None:
                continue

            if m := RE_SUBJECT.match(stripped):
                flush(); current.subject = m.group(1).strip(); mode = "stem"; continue
            if m := RE_SUBTOPIC.match(stripped):     # before RE_TOPIC: "Sub-Topic" contains "Topic"
                flush(); current.subtopic = m.group(1).strip(); mode = "stem"; continue
            if m := RE_TOPIC.match(stripped):
                flush(); current.topic = m.group(1).strip(); mode = "stem"; continue

            if m := RE_OPTION.match(stripped):
                flush()
                option_key, mode = m.group(1), "option"
                if inline := m.group(2).strip():
                    buffer.append(inline)
                continue

            if m := RE_ANSWER.match(stripped):
                flush()
                current.answer_raw = m.group(1)
                mode, option_key = None, None
                continue

            if m := RE_EXPLANATION.match(stripped):
                flush()
                mode, option_key = "explanation", None
                if inline := m.group(1).strip():
                    buffer.append(inline)
                continue

            if stripped:
                buffer.append(stripped)

    flush()
    if current is not None:
        questions.append(current)

    for q in questions:
        q.validate(require_explanation)
    return questions


# --------------------------------------------------------------------------- #
# Output
# --------------------------------------------------------------------------- #

def write_xlsx(questions: list[ParsedQuestion], path: Path) -> None:
    from openpyxl import Workbook
    from openpyxl.styles import Alignment, Font, PatternFill
    from openpyxl.utils import get_column_letter

    clean = [q for q in questions if not q.errors]
    flagged = [q for q in questions if q.errors]

    wb = Workbook()
    header_fill = PatternFill("solid", fgColor="4F46E5")
    header_font = Font(bold=True, color="FFFFFF")
    widths = [10, 60, 26, 26, 26, 26, 13, 60, 22, 24, 18, 16, 10]

    def add_sheet(title: str, rows: list[ParsedQuestion], with_diagnostics: bool):
        ws = wb.create_sheet(title)
        cols = COLUMNS + (["_errors", "_warnings", "_page"] if with_diagnostics else [])
        ws.append(cols)
        for i, name in enumerate(cols, start=1):
            c = ws.cell(row=1, column=i)
            c.fill, c.font = header_fill, header_font
            c.alignment = Alignment(horizontal="center", wrap_text=True)
            ws.column_dimensions[get_column_letter(i)].width = (
                widths[i - 1] if i <= len(widths) else 30
            )
        ws.freeze_panes = "A2"

        for q in rows:
            row = q.to_row()
            values = [row[c] for c in COLUMNS]
            if with_diagnostics:
                values += [";".join(q.errors), ";".join(q.warnings), q.page]
            ws.append(values)
            for i in (2, 8):
                ws.cell(row=ws.max_row, column=i).alignment = Alignment(wrap_text=True, vertical="top")
        return ws

    wb.remove(wb.active)
    add_sheet("questions", clean, with_diagnostics=False)
    if flagged:
        add_sheet("needs_review", flagged, with_diagnostics=True)
    wb.save(path)


def write_report(questions: list[ParsedQuestion], noise: list[str], probe: dict,
                 path: Path, expected: int | None) -> str:
    clean = [q for q in questions if not q.errors]
    flagged = [q for q in questions if q.errors]
    err = Counter(e for q in questions for e in q.errors)
    warn = Counter(w for q in questions for w in q.warnings)

    hashes = Counter(q.question_hash for q in questions)
    dupes = [h for h, c in hashes.items() if c > 1]

    lines = [
        "# Extraction report", "",
        f"- Pages: {probe.get('pages', 'n/a')}",
        f"- Text layer: {'yes' if probe.get('has_text_layer') else 'n/a'}"
        + (f" ({probe['chars_per_page']:.0f} chars/page)" if probe else " (pre-extracted text)"),
        f"- Questions detected: **{len(questions)}**",
    ]
    if expected:
        delta = len(questions) - expected
        verdict = "matches" if delta == 0 else f"**{delta:+d} vs expected {expected}**"
        lines.append(f"- Expected: {expected} — {verdict}")
    lines += [
        f"- Ready to import: **{len(clean)}**",
        f"- Need review: **{len(flagged)}**",
        f"- Duplicate question hashes: {len(dupes)}",
        "",
    ]

    if err:
        lines += ["## Blocking issues", "", "| Issue | Rows |", "|---|---|"]
        lines += [f"| `{k}` | {v} |" for k, v in err.most_common()]
        lines.append("")
    if warn:
        lines += ["## Warnings (imported, but worth a look)", "", "| Warning | Rows |", "|---|---|"]
        lines += [f"| `{k}` | {v} |" for k, v in warn.most_common()]
        lines.append("")

    pins = [q for q in questions if any(w.startswith("pin_last") for w in q.warnings)]
    xrefs = [q for q in questions if any(w.startswith("cross_reference") for w in q.warnings)]
    if pins or xrefs:
        lines += [
            "## Shuffle safety (ADR 003)", "",
            f"- `All/None of the above` style options: **{len(pins)}** "
            "— these are pinned to the last position, other choices shuffle around them.",
            f"- Options referencing other options (e.g. \"Both 1 and 2\"): **{len(xrefs)}** "
            "— shuffling is disabled for these questions.",
            "",
        ]
        for q in xrefs[:10]:
            lines.append(f"  - Ques {q.source_no} (QuesID {q.source_id})")
        lines.append("")

    if noise:
        lines += ["## Repeating lines removed as page furniture / watermark", ""]
        lines += [f"- `{n}`" for n in noise[:15]]
        lines.append("")

    lines += [
        "## What was NOT done", "",
        "- **No explanation was written by the tool.** Rows with `missing_explanation` "
        "are listed on the `needs_review` sheet for a subject-matter expert. Inventing "
        "medical rationale would look convincing and could teach a student something wrong.",
        "- No answer was inferred. An unresolvable `Ans:` is flagged, not guessed.",
        "",
    ]
    report = "\n".join(lines)
    path.write_text(report, encoding="utf-8")
    return report


# --------------------------------------------------------------------------- #

def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    src = ap.add_mutually_exclusive_group(required=True)
    src.add_argument("pdf", nargs="?", type=Path, help="source PDF")
    src.add_argument("--text", type=Path, help="pre-extracted text (for testing)")
    ap.add_argument("--out", type=Path, required=True, help="output .xlsx")
    ap.add_argument("--expected", type=int, help="expected question count")
    ap.add_argument("--allow-missing-explanation", action="store_true",
                    help="treat a blank explanation as a warning instead of a blocker "
                         "(for previous-year papers that ship answers only)")
    ap.add_argument("--images", type=Path,
                    help="extract question figures to this directory and link them")
    ap.add_argument("--strip", action="append", default=[],
                    help="exact line to treat as watermark/furniture (repeatable)")
    ap.add_argument("--json", action="store_true", help="also dump parsed JSON")
    args = ap.parse_args()

    probe: dict = {}
    if args.text:
        pages = [(1, args.text.read_text(encoding="utf-8"))]
        extras = {}
    else:
        if not args.pdf.exists():
            print(f"error: {args.pdf} not found", file=sys.stderr)
            return 1
        probe = probe_pdf(args.pdf)
        if probe["encrypted"]:
            print("error: PDF is encrypted — remove the password and retry", file=sys.stderr)
            return 1
        if not probe["has_text_layer"]:
            print(
                f"error: no usable text layer ({probe['chars_per_page']:.0f} chars/page).\n"
                "This looks like a scanned document. OCR would be required, and accuracy "
                "on drug names and dosages would be poor — worth discussing first.",
                file=sys.stderr,
            )
            return 2
        pages, extras = extract_text(args.pdf)
        probe |= extras

    pages, noise = strip_watermark(pages, args.strip)
    questions = parse(pages, require_explanation=not args.allow_missing_explanation)

    if not questions:
        print("error: no question blocks detected — the format may differ from the sample",
              file=sys.stderr)
        return 3

    figure_map: dict[str, str] = {}
    if args.images and args.pdf:
        figure_map = extract_figures(args.pdf, args.images)
        for q in questions:
            if filename := figure_map.get(q.source_no):
                q.figure = filename
            elif RE_MENTIONS_FIGURE.search(q.stem):
                # The text asks about something visual but no image was found on the
                # page — flag it rather than let it import looking complete.
                q.errors.append("figure_referenced_but_missing")

    args.out.parent.mkdir(parents=True, exist_ok=True)
    write_xlsx(questions, args.out)
    report = write_report(
        questions, noise, probe,
        args.out.with_suffix("").with_name(args.out.stem + "-report.md"),
        args.expected,
    )
    if args.json:
        args.out.with_suffix(".json").write_text(
            json.dumps([q.to_row() | {"_errors": q.errors, "_warnings": q.warnings}
                        for q in questions], indent=2),
            encoding="utf-8",
        )

    print(report)
    print(f"\nwrote {args.out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
