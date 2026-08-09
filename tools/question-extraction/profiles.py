#!/usr/bin/env python3
"""
Source-format profiles.

Two very different question-bank layouts have turned up, and a third is likely, so
each gets its own profile rather than piling conditionals into one parser.

  numbered   "Ques No: 1, QuesID: 799622" / "Subject:" / "O1:" / "Ans: 1"
             Answers only, no explanations.                     (FMGE Dec 2019)

  sectioned  "Q." stems, "● A." options, "✅ Correct Answer: 1",
             "🧠 Solution:" with a full explanation.            (FMGE Jan 2024)

The `sectioned` document is internally inconsistent in three ways that defeat a
straightforward top-down parse:

  * only 49 of its 61 stems carry a "Q." prefix;
  * stem font size varies by page (11pt, 13pt, 14pt), and on some pages the stem is
    styled identically to body text;
  * the subject appears sometimes as a section heading and sometimes as an inline
    "Subject: ENT" line.

The one thing that is perfectly consistent is the option block. So this profile
anchors on each "● A." line and walks UPWARDS to recover the stem, which yields all
61 questions where pattern matching found 7.
"""

from __future__ import annotations

import html
import re
from collections import Counter

# --------------------------------------------------------------------------- #

BODY_SIZE = 11.0
ACCENT_MIN_BLUE_DELTA = 0.15     # blue channel exceeds red by at least this much
TITLE_MIN_SIZE = 20.0
MAX_STEM_LINES = 8               # a stem longer than this is a parse failure, not a stem
SMALL_PRINT_MAX_SIZE = 10.5      # footers and marketing copy are set below body size

# Stem markers seen so far: "Q.", "Q)", "Question 4.", and bare ids like "Q859982."
RE_Q_PREFIX = re.compile(r"^\s*(?:Q\s*\d*\s*[.)]|Question\s*\d*\s*[.)])\s*", re.IGNORECASE)
RE_BULLET_OPTION = re.compile(r"^\s*[●•▪◦*]\s*([A-Da-d])\s*[.):]\s*(.+)$")
RE_ANSWER_LINE = re.compile(
    r"^\s*[^\w]*\s*Correct\s*Answer\s*:?\s*\(?\s*([1-4]|[A-Da-d])\s*\)?\s*$",
    re.IGNORECASE,
)
RE_SOLUTION_LINE = re.compile(
    r"^\s*[^\w]*\s*(?:Solution|Explanation)\s*:\s*(.*)$", re.IGNORECASE
)
RE_INLINE_SUBJECT = re.compile(r"^\s*Subject\s*:\s*(.+?)\s*$", re.IGNORECASE)


class Line:
    """A text line plus the styling facts needed to classify it."""

    __slots__ = ("page", "text", "size", "accent", "bold")

    def __init__(self, page: int, raw: dict):
        counts: Counter = Counter()
        for char in raw["chars"]:
            counts[(
                round(char.get("size") or 0, 1),
                _is_accent(char.get("non_stroking_color")),
                "Bold" in str(char.get("fontname", "")),
            )] += 1
        size, accent, bold = (
            max(counts.items(), key=lambda kv: kv[1])[0] if counts else (0.0, False, False)
        )
        self.page = page
        self.text = html.unescape(raw["text"]).strip()
        self.size = size
        self.accent = accent
        self.bold = bold

    # -- classification ----------------------------------------------------- #

    @property
    def is_small_print(self) -> bool:
        """Page footers and promotional copy, set smaller than body text."""
        return 0 < self.size <= SMALL_PRINT_MAX_SIZE

    @property
    def is_title(self) -> bool:
        return self.size >= TITLE_MIN_SIZE

    @property
    def is_option(self) -> bool:
        return bool(RE_BULLET_OPTION.match(self.text))

    @property
    def is_answer(self) -> bool:
        return bool(RE_ANSWER_LINE.match(self.text))

    @property
    def is_solution(self) -> bool:
        return bool(RE_SOLUTION_LINE.match(self.text))

    @property
    def is_subject(self) -> bool:
        return bool(RE_INLINE_SUBJECT.match(self.text))

    @property
    def starts_stem(self) -> bool:
        return bool(RE_Q_PREFIX.match(self.text))

    @property
    def is_emphasised(self) -> bool:
        """Stems are set in an accent colour and bold — where the document bothers."""
        return self.accent and self.bold

    def option(self) -> tuple[str, str] | None:
        if m := RE_BULLET_OPTION.match(self.text):
            return str("abcd".index(m.group(1).lower()) + 1), m.group(2).strip()
        return None


def _is_accent(colour) -> bool:
    if not isinstance(colour, (list, tuple)) or len(colour) != 3:
        return False
    r, g, b = colour
    if not all(isinstance(c, (int, float)) for c in (r, g, b)):
        return False
    return b - r >= ACCENT_MIN_BLUE_DELTA and b > 0.3


# --------------------------------------------------------------------------- #

def parse_sectioned(pages, question_factory, canonical_topics) -> list:
    """
    @param pages            [(page_no, [pdfplumber line dicts])]
    @param question_factory callable returning a fresh ParsedQuestion
    """
    lines: list[Line] = [
        Line(page_no, raw)
        for page_no, raw_lines in pages
        for raw in raw_lines
        if raw["text"].strip()
    ]

    # Subject in force at each line: section headings and inline "Subject:" lines.
    subject_at: list[str] = []
    subject = ""
    for line in lines:
        if m := RE_INLINE_SUBJECT.match(line.text):
            subject = m.group(1).strip()
        elif (
            line.is_emphasised
            and not line.is_answer
            and not line.starts_stem
            and line.text.strip().lower() in canonical_topics
        ):
            subject = line.text.strip()
        subject_at.append(subject)

    anchors = [i for i, line in enumerate(lines) if (line.option() or ("", ""))[0] == "1"]
    questions: list = []

    for number, anchor in enumerate(anchors, start=1):
        q = question_factory()
        q.source_no = str(number)
        q.page = lines[anchor].page
        q.subject = subject_at[anchor]

        # ---- options: the contiguous run starting at the anchor ------------ #
        cursor = anchor
        while cursor < len(lines):
            parsed = lines[cursor].option()
            if parsed is None:
                break
            key, text = parsed
            if key in q.options:          # next question's option A
                break
            q.options[key] = text
            cursor += 1

        # ---- answer + explanation, forwards ------------------------------- #
        explanation: list[str] = []
        collecting = False
        while cursor < len(lines):
            line = lines[cursor]
            if line.option() and line.option()[0] == "1":
                break                                    # next question
            if line.is_emphasised and not line.is_answer and not line.is_subject:
                break                                    # next stem
            if line.is_subject:
                break
            if line.is_small_print:
                break                                    # trailing footer / advert
            if line.is_answer:
                q.answer_raw = RE_ANSWER_LINE.match(line.text).group(1)
                cursor += 1
                continue
            if line.is_solution:
                collecting = True
                if inline := RE_SOLUTION_LINE.match(line.text).group(1).strip():
                    explanation.append(inline)
                cursor += 1
                continue
            if collecting:
                explanation.append(line.text)
            cursor += 1
        q.explanation = " ".join(" ".join(explanation).split()).strip()

        # ---- stem, walking upwards from the anchor ------------------------ #
        stem: list[str] = []
        plain_run = 0
        j = anchor - 1
        while j >= 0 and len(stem) < MAX_STEM_LINES:
            line = lines[j]
            if line.is_option or line.is_answer or line.is_solution \
                    or line.is_subject or line.is_title or line.is_small_print:
                break

            stem.insert(0, RE_Q_PREFIX.sub("", line.text))

            if line.starts_stem:
                break                        # reached the top of the stem
            if not line.is_emphasised:
                # Unstyled line: acceptable only as a continuation above which a
                # "Q." should appear. Bail out rather than swallow the previous
                # explanation, and say so.
                plain_run += 1
                if plain_run > 4:
                    stem.pop(0)
                    q.warnings.append("stem_boundary_uncertain")
                    break
            j -= 1

        q.stem = " ".join(" ".join(stem).split()).strip()
        questions.append(q)

    return questions


def detect_profile(pages_text: list[str]) -> str:
    sample = "\n".join(pages_text[:6])
    if re.search(r"Ques\s*No\s*:\s*\d+", sample, re.IGNORECASE):
        return "numbered"
    if re.search(r"Correct\s*Answer\s*:", sample, re.IGNORECASE):
        return "sectioned"
    return "numbered"
