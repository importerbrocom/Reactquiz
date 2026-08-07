# QuizPath

Daily-quiz PWA for medical licensing exam preparation (FMGE, AMC and others).
Students complete 10 questions a day, must master all 10 to unlock the next day,
and sit a month-end test over the 300 questions they practised.

## Repository layout

```
Reactquiz/
├── api/                    Laravel 13 REST API              (Phase 2 — done)
├── web/                    React 19 + Vite 8 PWA            (Phase 4)
├── mobile/                 Expo / React Native for iOS      (Phase 12)
├── packages/               Shared TS: contracts, api-client, quiz-core
├── docs/
│   ├── phase-1/            Architecture and planning (30 documents)
│   ├── adr/                Decisions that are expensive to reverse
│   ├── import-format.md    Question spreadsheet specification
│   └── templates/          Fillable .xlsx / .csv import template
└── load-tests/             k6 scenarios                     (Phase 11)
```

## Start here

| If you are… | Read |
|---|---|
| Writing questions | [`docs/import-format.md`](docs/import-format.md) + [`docs/templates/questions-import-template.xlsx`](docs/templates/questions-import-template.xlsx) |
| Reviewing the design | [`docs/phase-1/README.md`](docs/phase-1/README.md) |
| Wondering why questions differ per student | [`docs/adr/001-per-student-question-assignment.md`](docs/adr/001-per-student-question-assignment.md) |
| Wondering how levels and cycles work | [`docs/adr/002-programme-levels-and-cycles.md`](docs/adr/002-programme-levels-and-cycles.md) |
| Wondering why answer choices move around | [`docs/adr/003-per-exposure-option-shuffling.md`](docs/adr/003-per-exposure-option-shuffling.md) |
| Running the API | [`api/README.md`](api/README.md) |

## The model in one picture

```
Exam type (FMGE)
└── Programme "FMGE Complete — 6 Months"        1,800 questions, 2 cycles
    ├── Level 1  Month 1   300 questions   30 days x 10  + month-end test
    ├── Level 2  Month 2   300 questions   unlocks when Level 1 is complete
    ├── … Level 6
    └── Cycle 2: the same 1,800 questions, freshly shuffled
```

Each student receives their **own** shuffle of a level's 300 questions across its
30 days, so no two students share a Day 1, yet everyone completes all 300.
Answer choices are reshuffled on **every exposure**, so students learn the material
rather than the position of the correct option.
