# Quiz PWA — Phase 1: Architecture & Planning

Working title: **QuizPath** (rename freely — the name only appears in the manifest, mail templates and seeded settings).

This directory is the **single source of truth for Phase 1**. Phases 2–11 will implement exactly what is described here.
If an implementation decision in a later phase contradicts this document, the document must be updated in the same commit.

## Document index

| # | Document | Covers Phase-1 deliverables |
|---|---|---|
| 01 | [System architecture](./01-system-architecture.md) | 1. System architecture, 2. Recommended technology versions |
| 02 | [Folder structures](./02-folder-structures.md) | 3. Frontend structure, 4. Backend structure |
| 03 | [Database design](./03-database-design.md) | 5. DB design, 6. Table definitions, 7. Relationships, 8. Indexing plan, 9. Unique-constraint plan |
| 04 | [API contract](./04-api-contract.md) | 10. API endpoint list |
| 05 | [Flows & algorithms](./05-flows-and-algorithms.md) | 11. Student flow, 12. Admin flow, 13. Unlock algorithm, 14. Answer evaluation, 15. Final-test generation, 16. PDF import workflow, 17. Push workflow |
| 06 | [PWA & offline](./06-pwa-and-offline.md) | 18. PWA caching strategy, 19. Offline sync strategy |
| 07 | [Redis & queues](./07-redis-and-queues.md) | 20. Redis caching strategy, 21. Queue architecture |
| 08 | [Security architecture](./08-security-architecture.md) | 22. Security architecture |
| 09 | [Performance & scalability](./09-performance-and-scalability.md) | 23. Performance architecture, 24. Scalability plan |
| 10 | [Deployment & monitoring](./10-deployment-and-monitoring.md) | 25. Deployment architecture, 26. Monitoring strategy |
| 11 | [Testing & load plan](./11-testing-and-load-plan.md) | 27. Load-testing plan, 28. Performance acceptance criteria |
| 12 | [Roadmap & risks](./12-roadmap-and-risks.md) | 29. Development roadmap, 30. Risks & mitigation |
| 13 | [Environment variables](./13-environment-variables.md) | Full env var inventory for API, worker and web |

## The five decisions that shape everything else

1. **The server is the only authority on quiz state.** Day unlocking, answer correctness, "10/10", final-test
   eligibility and scores are computed from `student_question_progress` and `quiz_attempts` rows inside transactions.
   The client is a renderer with a cache. Every rule in section 49 of the brief has a matching backend guard and a test.
2. **Correct answers never leave the server before submission.** `correct_option`, `correct_answer_text` and
   `explanation` live on `questions` but are excluded from the question-delivery API Resource by construction
   (a dedicated `QuestionForStudentResource` that only whitelists safe fields), and are only returned by
   `POST /answers` responses, which are marked `Cache-Control: no-store` and never cached in the service worker or IndexedDB.
3. **Sanctum in *token* mode with a refresh-cookie wrapper, not cookie/SPA mode.** Rationale and threat model in
   [08-security-architecture.md](./08-security-architecture.md#31-why-token-mode-and-not-cookiespa-mode). Short-lived
   access token held **in memory only**, long-lived rotating refresh token in an `HttpOnly; Secure; SameSite=Strict`
   cookie scoped to `/api/v1/auth`. This survives the PWA being served from a CDN on a different origin, works in
   standalone display mode, and keeps nothing in `localStorage`.
4. **Idempotency is a first-class API feature, not an afterthought.** Every write that can be replayed by an offline
   queue (`submit answer`, `save final-test answers`, `complete quiz`, `submit final test`) requires a client-generated
   `Idempotency-Key`, deduplicated in Redis + a unique DB constraint. This is what makes offline sync safe.
5. **Queues are segregated by blast radius.** `critical` (quiz completion, final-test finalisation) never shares a
   worker pool with `pdf` (minutes-long parsing) or `reports`. A 400-page PDF import cannot delay a student's 10/10.

## Business rules → enforcement map (section 49 of the brief)

| Rule | Enforced by | Verified by test |
|------|-------------|------------------|
| 1. 30 daily quizzes per course (default) | `courses.total_quiz_days`, seeded default 30; `DailyQuizService::generateForCourse()` | `CourseConfigurationTest` |
| 2. 10 questions per day (default) | `courses.daily_question_count`; `daily_quiz_questions` rows | `DailyQuizGenerationTest` |
| 3. Day 1 initially available | `QuizUnlockService::isDayUnlocked()` returns true for `day_number = 1` when enrolment active | `QuizUnlockTest::day_one_is_available` |
| 4. All 10 must be correct | `QuizCompletionService` requires `mastered_count === required_count` | `QuizCompletionTest` |
| 5/6. Wrong answer shows correct answer + explanation | `AnswerEvaluationService` response payload (only after submit) | `AnswerEvaluationTest` |
| 7. Retry allowed | `quiz_attempt_answers` append-only + `student_question_progress.attempts` | `RetryFlowTest` |
| 8/9. Complete only at 10/10 | `POST /complete` re-derives score server-side, rejects otherwise | `QuizCompletionTest` |
| 10/11/12. Sequential unlock, no URL skipping | `QuizAccessPolicy` + `EnsureQuizDayUnlocked` middleware → 423 Locked | `QuizUnlockTest::locked_day_is_rejected` |
| 13/14/15. Day 31 final test, 300 questions | `FinalTestService::materialise()` snapshots all `daily_quiz_questions` for days 1..30 | `FinalTestGenerationTest` |
| 16. Final test unlocks only after 30× 10/10 | `FinalTestEligibilityService` counts `quiz_attempts` with `status = completed AND score = required` | `FinalTestEligibilityTest` |
| 17/18. Backend validates all rules | Policies + middleware + services; no rule lives only in React | Whole `tests/Feature/Quiz` suite |
| 19. Every answer and retry recorded | `quiz_attempt_answers` is append-only (no updates) | `AnswerAuditTest` |
| 20. No early answer exposure | `QuestionForStudentResource` whitelist + `AnswerLeakageTest` asserts response JSON never contains answer keys | `AnswerLeakageTest` |
| 21/22. No duplicate submissions | `Idempotency-Key` + unique `(quiz_attempt_id, question_id, client_answer_uuid)` | `IdempotencyTest`, `OfflineSyncTest` |
| 23. Transaction-safe completion | `DB::transaction` + `SELECT ... FOR UPDATE` on `quiz_attempts` row | `ConcurrentCompletionTest` |
| 24. PDF never delays quiz ops | separate `pdf` queue + separate Horizon supervisor | load test scenario `pdf_vs_quiz_isolation` |
| 25. Final test does not render 300 components | single mounted `<QuestionCard>` + virtualised palette | `FinalTestScreen.test.tsx` render-count assertion |

## Explicit non-goals for v1

Called out so they never accidentally become scope creep or half-finished code:

- **OCR for scanned PDFs.** The parser pipeline has an `OcrStrategy` seam and returns `failed` with reason
  `scanned_pdf_no_text_layer`; no OCR engine ships in v1.
- **Real-time multiplayer / live leaderboards.** No WebSocket layer. Progress is request/response + polling.
- **Native app store wrappers.** PWA install only.
- **Payments / subscriptions.** Enrolment is admin- or self-service-free.
- **Multi-tenant white-labelling.** Single tenant, single brand.
- **Full i18n.** The language preference is captured in onboarding and the UI is wired through a translation
  function from day one, but only `en` ships as a complete locale in v1.

## How to read this with the client

Start with [01-system-architecture.md](./01-system-architecture.md) for the shape of the system, then
[05-flows-and-algorithms.md](./05-flows-and-algorithms.md) — those two contain every decision that is expensive to
change later. [03-database-design.md](./03-database-design.md) is the one to review most carefully, because migrations
are the hardest thing to walk back once students have data.
