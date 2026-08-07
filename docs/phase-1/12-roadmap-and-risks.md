# 12 — Development Roadmap & Risk Register

## 29. Development roadmap

Estimates assume **one full-stack developer working with an AI pair** (this setup), full-time-equivalent days. A team of
two (one backend, one frontend) compresses the calendar by roughly 40% because Phases 3/4 and 5/6 parallelise.

### Phase plan

| Phase | Deliverable | Est. | Depends on | Exit criteria ("done" means) |
|---|---|---|---|---|
| **1** | Architecture & planning (this document set) | 1–2 d | — | Client sign-off on schema, unlock rules and API shape |
| **2** | Laravel foundation: install, env, packages, 35 migrations, enums, models + relationships, factories, seeders, Sanctum auth, roles, middleware, policies, response envelope, exception handling | 4–5 d | 1 | `migrate:fresh --seed` works; auth suite green; a seeded student can log in and read `/auth/me` |
| **3** | Core APIs: admin CRUD, student endpoints, Form Requests, Resources, `DailyQuizService`, `QuizUnlockService`, `AnswerEvaluationService`, `QuizCompletionService`, `StudentProgressService`, `FinalTestService`, feature tests | 8–10 d | 2 | Full quiz lifecycle passes tests via HTTP incl. locked-day 423, 10/10 completion, unlock, final-test eligibility, concurrency and leakage tests |
| **4** | React foundation: Vite/TS/Tailwind, routing + guards, Axios + interceptors, TanStack Query config, Zustand stores, auth flow, layouts, ~25 UI primitives, theme + dark mode | 5–6 d | 3 (contract) | Login → protected dashboard shell works; component library rendered in a `/dev/ui` gallery route; a11y basics verified |
| **5** | Student app: onboarding (5 screens), dashboard, daily quiz runner with wrong-answer feedback + retry, progress page, mistake review, notifications UI, profile, final test | 9–11 d | 4 | A real student can complete day 1 → day 2 unlock → …; final test runnable end-to-end on a phone |
| **6** | Admin app: dashboard + charts, category/course management, question table with bulk ops, PDF import UI, student management, notification composer, reports UI | 8–10 d | 4 | An admin can build a 30-day course from scratch without touching the database |
| **7** | PDF processing: upload, private storage, queue job, `PdfQuestionExtractionService` + parsers, preview/correction, approval bulk insert, duplicate detection, failure handling | 5–7 d | 3, 6 | The three fixture PDFs (clean, messy, large) import with ≥ 85% clean-parse rate on the clean fixture and produce correct failure states for encrypted/scanned/corrupt |
| **8** | PWA & offline: manifest, hand-written service worker, IndexedDB layer, outbox + background sync, install prompt, update handling, cache cleanup, network status | 4–5 d | 5 | Airplane-mode test: 10 answers offline → reconnect → exactly 10 rows, day completes |
| **9** | Push notifications: subscription flow, storage, scheduler dispatcher, batched delivery, timezone handling, retries, cleanup | 3–4 d | 8 | Reminder arrives at the chosen local time on a real Android device; expired endpoints pruned |
| **10** | Performance & deployment: Redis tuning, Horizon, cache strategy, query review, bundle/image optimisation, Nginx/PHP-FPM/OPcache, Supervisor, object storage, CDN, backups, monitoring, production deploy | 5–6 d | 5–9 | Staging = production config; deploy pipeline green; monitoring dashboards live; restore drill done |
| **11** | Testing & final review: backend + frontend test completion, security review, a11y review, load tests, PWA/Lighthouse audits, checklists | 5–7 d | 10 | Every box in [11 §28](./11-testing-and-load-plan.md#28-performance-acceptance-criteria-pre-production-gate) passed or waived in writing |

**Total: ~57–73 working days (≈ 12–15 weeks solo, ≈ 8–9 weeks for two).**

### Milestones worth demoing to stakeholders

| Milestone | After | Why it matters |
|---|---|---|
| M1 "Rules work" | Phase 3 | The whole product risk is the unlock/evaluation logic; proving it via tests before any UI is the cheapest possible order |
| M2 "A student can learn" | Phase 5 | First real user value; usable on a phone |
| M3 "An admin can operate it" | Phase 6 | The product becomes maintainable without a developer |
| M4 "Content at scale" | Phase 7 | PDF import removes the content bottleneck |
| M5 "Works on a bad train connection" | Phase 8 | The differentiator for the target audience |
| M6 "Production ready" | Phase 11 | Launch gate |

### Sequencing rationale

- **Backend rules before any UI.** Every rule in section 49 of the brief is a server rule; building the UI first would
  mean building it twice.
- **Design system before feature screens.** 25 primitives built once in Phase 4 make Phases 5–6 assembly work rather
  than styling work, and they are where accessibility is centralised.
- **PDF import after the admin shell** so the review UI has a table, filters and toasts to build on.
- **PWA after the student app is real.** Offline behaviour is meaningless before there is a quiz to take offline, and
  retrofitting the outbox is easier than designing screens around a hypothetical one.
- **Push after PWA** — it needs a registered service worker.
- **Load tests last** but scenarios written in Phase 1, so the acceptance targets shape the implementation.

### Cut-scope levers (if the deadline compresses)

In the order they should be dropped, with the honest consequence of each:

1. Certificates (Phase 5 tail) → students still see pass/fail; add later. *Low pain.*
2. Reports export to Excel/PDF; keep CSV → admins lose formatting. *Low pain.*
3. Practice-mistakes mode → mistake review stays read-only. *Medium; hurts retention.*
4. Admin chart panels; keep summary cards → less insight, no functional loss. *Low.*
5. Push notifications → replaced by in-app reminders only. *High; measurably hurts daily return rate.*
6. Offline support → **do not cut.** It is the core differentiator for the stated audience.
7. Never cut: server-side rule enforcement, answer confidentiality, idempotency, accessibility basics.

---

## 30. Risk register

Scored: **L**ikelihood × **I**mpact (1–5). Score ≥ 12 = actively managed with a named mitigation in the build plan.

### Product & correctness risks

| ID | Risk | L | I | Score | Mitigation | Early-warning signal |
|---|---|---|---|---|---|---|
| R1 | **Answer keys leak** through a new endpoint or a careless Resource | 3 | 5 | **15** | Whitelist Resources, `$hidden` on the model, route-sweeping `AnswerLeakageTest` in CI, code-review checklist item | Test failure; unexpected fields in a diff |
| R2 | **Unlock bypass** through a route added later without the middleware | 3 | 5 | **15** | Middleware applied at the route-**group** level, policy as a second gate, a test that enumerates all student routes and asserts locked-day rejection | New route appears in the enumeration test without a guard |
| R3 | **Concurrent completion** double-counts streaks/unlocks | 3 | 4 | **12** | Row-level `FOR UPDATE` + idempotency + a 3-way race test | Duplicate streak reports from students |
| R4 | **Offline sync creates duplicate answers** | 3 | 4 | **12** | `client_answer_uuid` unique + Redis ledger + drain lock; airplane-mode test in CI (Playwright) | `quiz.idempotent_replay` metric climbing above 5% |
| R5 | Students stuck because a day has fewer than 10 questions | 3 | 4 | **12** | Course readiness check blocks publishing; `daily_quiz_questions` count validated at generation; admin banner | Readiness endpoint failures; support tickets on one specific day |
| R6 | Admin edits a question mid-course and breaks a student's in-flight attempt | 3 | 3 | 9 | Snapshotted `daily_quiz_questions` + versioned final tests; edits don't remove a question from an active attempt | Content-version churn during term time |
| R7 | 10/10 requirement feels punishing; students churn at a hard question | 3 | 3 | 9 | Explanations after every wrong answer, unlimited retries, encouraging copy, streak mechanics; measure day-N drop-off and let admins soften `pass` rules per course | Funnel drop concentrated on specific question ids |

### Technical risks

| ID | Risk | L | I | Score | Mitigation | Early-warning signal |
|---|---|---|---|---|---|---|
| R8 | **PDF extraction accuracy is poor** on real-world files (the biggest unknown in the project) | **4** | 4 | **16** | Confidence scoring + admin review UI designed as a *first-class* workflow, not an afterthought; two extractor backends; test corpus of ≥ 10 real PDFs collected **before** Phase 7 starts; explicit "manual entry is always available" fallback | Clean-parse rate < 70% on the fixture corpus |
| R9 | **Scanned PDFs** dominate the client's real content | 3 | 4 | **12** | Detected and reported clearly rather than silently mangled; OCR seam ready; scope OCR as a costed follow-up | High `no_text_layer` failure ratio in `pdf_imports` |
| R10 | **Stale service worker** serves a bundle incompatible with the API | 3 | 4 | **12** | Build-id caches, `X-Api-Contract` handshake, forced update on mismatch, staged test of the mechanism each release | Spike in 4xx from one `X-Client-Build` value |
| R11 | iOS PWA limitations (push only when installed, storage eviction, no `beforeinstallprompt`) | **4** | 3 | **12** | Feature-detect and degrade: in-app reminders when push is unavailable, explicit "Add to Home Screen" guide, never rely on IndexedDB persistence for correctness (server is truth) | iOS share of missed-reminder complaints |
| R12 | Low-end Android performance below target (jank on transitions) | 3 | 3 | 9 | Bundle budgets in CI, one mounted question, virtualisation, reduced-motion, real-device testing on a Moto G-class phone before launch | Lighthouse/field INP regression |
| R13 | Queue backlog delays quiz completion follow-ups | 2 | 4 | 8 | Physical pool separation, wait-time alerts, `critical` queue never shares workers with `pdf` | `queue.wait critical` > 30 s |
| R14 | MySQL row-lock contention at peak | 2 | 4 | 8 | Per-attempt locks only; 7 single-row statements; load-tested; escalation path documented (conditional UPDATE instead of `FOR UPDATE`) | `Innodb_row_lock_waits` rising in load tests |
| R15 | Redis loss/restart loses queued jobs | 2 | 4 | 8 | AOF persistence on the queue instance, `noeviction`, jobs idempotent so replay is safe, `failed_jobs` retry path | Redis restart events; missing job outcomes |
| R16 | Push subscriptions silently rot (students stop getting reminders) | **4** | 2 | 8 | 404/410 handling → mark expired, `pushsubscriptionchange` re-subscribe, weekly health report, `/push-subscriptions/test` | Delivery success ratio trend down |
| R17 | Timezone/DST bugs cause reminders or `next_calendar_day` unlocks at the wrong time | 3 | 3 | 9 | Store UTC, compute in `users.timezone`, dedicated tests incl. a DST-transition case and a `UTC+5:30` case | Support reports of a day unlocking "a day late" |
| R18 | Frontend bundle grows past budget as admin features land | 3 | 2 | 6 | Separate admin chunk tree, CI size budget, `stats.html` per release | Budget check failing in PRs |

### Project & operational risks

| ID | Risk | L | I | Score | Mitigation |
|---|---|---|---|---|---|
| R19 | **Scope creep** (leaderboards, chat, video, payments, multi-language) mid-build | **4** | 4 | **16** | Non-goals written in [README](./README.md#explicit-non-goals-for-v1); every addition costed against the roadmap and traded, not appended; phase gates require sign-off |
| R20 | Content not ready when the app is (no questions to launch with) | 3 | 4 | **12** | Content production starts in parallel from Phase 2; manual entry available from Phase 6; seeder ships a demo course so the app is always demonstrable |
| R21 | Single-developer bus factor | 3 | 4 | **12** | Everything decided in writing here; ADRs for reversal-expensive choices; tests as executable specification; no undocumented environment steps |
| R22 | No staging environment ("we'll test in production") | 3 | 4 | **12** | Staging is a Phase 10 deliverable, not optional; deploy pipeline requires it |
| R23 | Backups never tested | 2 | 5 | 10 | Restore drill is an explicit acceptance-criteria checkbox before launch |
| R24 | Admin account compromise (admins can read all content and PII) | 2 | 5 | 10 | 2FA required for admins, IP allow-list option, `activity_logs` on every admin action, session revocation |
| R25 | Cost surprise from object storage/CDN egress on large PDFs | 2 | 2 | 4 | Private bucket + signed URLs (no hotlinking), file-size caps, lifecycle rules, cost alert on the provider |
| R26 | Dependency supply-chain compromise (npm/composer) | 2 | 4 | 8 | Pinned versions + lockfiles committed, `npm ci`, `composer audit` + `npm audit` in CI, Dependabot with review, no `postinstall` scripts from unknown packages |
| R27 | Accessibility treated as a late polish item and never actually done | 3 | 3 | 9 | Primitives are accessible from Phase 4; `axe` in component tests; keyboard-only quiz run is an acceptance checkbox |

### The three risks to watch weekly

1. **R8 — PDF extraction accuracy.** The only genuinely unpredictable technical unknown. Action now, before Phase 7:
   collect at least ten real PDFs from the client and add them to the fixture corpus. If clean-parse rates are poor, the
   product decision (invest in OCR/parsers vs lean on the review UI vs outsource data entry) should be made with data,
   early, not discovered in week 10.
2. **R19 — scope creep.** The brief is already large; the roadmap only holds if additions displace something.
3. **R1/R2 — answer leakage and unlock bypass.** Both are cheap to prevent with the tests specified and expensive to
   discover after launch, because the content asset cannot be un-leaked.

### Assumptions this plan rests on (flag immediately if any is false)

- Questions are single-correct-answer, 4-option MCQs. Multi-select, numeric-entry, match-the-pairs or passage-based
  comprehension sets would change the schema and the quiz UI materially.
- Content is text (with occasional images), not audio/video, and not heavy mathematical notation. If LaTeX/MathML is
  needed, add a rendering library and PDF-parsing complexity (~3–5 days).
- One student follows one course at a time in practice (the schema supports several; the UI is designed for a primary
  course).
- Students are on Android-dominant mobile with intermittent connectivity, in one or a few timezones.
- English UI at launch, with the language preference captured for later locales.
- No payments, no live classes, no teacher/institution hierarchy in v1.
