# 02 — Folder Structures

Monorepo layout (single git repository, two deployables). This keeps the Zod schemas, TypeScript API types and the
Laravel API Resources reviewable in one pull request.

```
quizpath/
├── api/                      # Laravel 13 application  → deployed to API nodes
├── web/                      # React 19 + Vite 8 PWA   → built to static, deployed to CDN
├── load-tests/               # k6 scenarios (see 11-testing-and-load-plan.md)
├── docs/
│   ├── phase-1/              # this directory
│   ├── adr/                  # architecture decision records, one file per reversal-expensive choice
│   └── runbooks/             # incident runbooks (queue backlog, push failures, stale SW)
├── deploy/
│   ├── nginx/                # api.conf, app.conf
│   ├── php/                  # php.ini-production, php-fpm.d/www.conf, opcache.ini
│   ├── supervisor/           # horizon.conf, scheduler.conf
│   ├── docker/               # Dockerfile.api, Dockerfile.worker, compose.dev.yml
│   └── github/               # reusable CI workflow fragments
├── .github/workflows/        # ci.yml, deploy-api.yml, deploy-web.yml, lighthouse.yml
├── Makefile                  # make dev / make test / make fresh / make lint
└── README.md
```

---

## 3. Frontend folder structure (`web/`)

```
web/
├── index.html                        # single entry, preconnect to API, theme-color, no render-blocking JS
├── vite.config.ts                    # plugins: react, tailwind, pwa(injectManifest), visualizer(analyze mode)
├── tsconfig.json                     # strict, paths: "@/*" → "src/*"
├── tailwind.config.ts                # v4: mostly @theme in CSS; config holds content globs + plugins
├── vitest.config.ts
├── .env.example                      # VITE_* only — never secrets
├── public/
│   ├── manifest.webmanifest
│   ├── offline.html                  # standalone offline fallback (no JS dependency)
│   ├── icons/                        # 192, 256, 384, 512, maskable-512, apple-touch-180
│   └── splash/                       # iOS splash images
└── src/
    ├── main.tsx                      # createRoot, providers, SW registration
    ├── App.tsx                       # RouterProvider only
    ├── vite-env.d.ts
    │
    ├── api/                          # transport layer — no React, no business rules
    │   ├── client.ts                 # axios instance, baseURL, withCredentials for /auth, timeouts
    │   ├── interceptors/
    │   │   ├── auth.interceptor.ts           # attach in-memory access token
    │   │   ├── refresh.interceptor.ts        # single-flight 401 → POST /auth/refresh → retry
    │   │   ├── idempotency.interceptor.ts    # attach Idempotency-Key on flagged mutations
    │   │   ├── contract-version.interceptor.ts # read X-Api-Contract → trigger SW update if mismatch
    │   │   └── error.interceptor.ts          # normalise to ApiError, toast policy, Sentry breadcrumb
    │   ├── endpoints/                # one file per resource: pure functions returning typed data
    │   │   ├── auth.api.ts
    │   │   ├── categories.api.ts
    │   │   ├── courses.api.ts
    │   │   ├── onboarding.api.ts
    │   │   ├── student-dashboard.api.ts
    │   │   ├── quiz.api.ts
    │   │   ├── final-test.api.ts
    │   │   ├── mistakes.api.ts
    │   │   ├── progress.api.ts
    │   │   ├── notifications.api.ts
    │   │   ├── profile.api.ts
    │   │   ├── admin-dashboard.api.ts
    │   │   ├── admin-questions.api.ts
    │   │   ├── admin-pdf-imports.api.ts
    │   │   ├── admin-students.api.ts
    │   │   └── admin-reports.api.ts
    │   ├── query-keys.ts             # ALL query-key factories in one file (see 31 in brief)
    │   └── schemas/                  # zod response + request schemas, shared with forms
    │       ├── auth.schema.ts
    │       ├── quiz.schema.ts
    │       ├── question.schema.ts
    │       ├── course.schema.ts
    │       └── ...
    │
    ├── assets/                       # svg logo, illustrations (compressed), fonts (2 weights, woff2, subset)
    │
    ├── components/                   # design system — presentational, no data fetching
    │   ├── ui/
    │   │   ├── Button.tsx            # variants via cva; min 44px touch target; loading state
    │   │   ├── IconButton.tsx
    │   │   ├── Input.tsx             # label, description, error id wiring (aria-describedby)
    │   │   ├── PasswordInput.tsx
    │   │   ├── Select.tsx
    │   │   ├── Checkbox.tsx  Radio.tsx  Switch.tsx  Textarea.tsx
    │   │   ├── FormError.tsx         # role="alert"
    │   │   ├── Card.tsx  CardHeader.tsx  CardBody.tsx
    │   │   ├── Modal.tsx             # focus trap, Esc, aria-modal, scroll lock, returns focus
    │   │   ├── ConfirmDialog.tsx
    │   │   ├── Sheet.tsx             # mobile bottom sheet
    │   │   ├── Alert.tsx  Badge.tsx  StatusBadge.tsx  LockBadge.tsx
    │   │   ├── ProgressBar.tsx       # role="progressbar" + aria-valuenow
    │   │   ├── CircularProgress.tsx
    │   │   ├── Spinner.tsx
    │   │   ├── Skeleton.tsx  SkeletonText.tsx  SkeletonCard.tsx
    │   │   ├── EmptyState.tsx
    │   │   ├── ErrorState.tsx        # retry action, safe message
    │   │   ├── Toast.tsx  ToastViewport.tsx   # aria-live="polite"
    │   │   ├── Tabs.tsx  Accordion.tsx  Tooltip.tsx
    │   │   ├── Pagination.tsx
    │   │   ├── DataTable.tsx         # headless: columns, sort, empty, loading, optional virtualisation
    │   │   ├── Avatar.tsx
    │   │   ├── ImageUpload.tsx       # client-side resize before upload
    │   │   └── VisuallyHidden.tsx
    │   ├── quiz/
    │   │   ├── QuestionCard.tsx      # question text + optional image
    │   │   ├── QuizOptionButton.tsx  # states: idle/selected/correct/incorrect/disabled + icon + text label
    │   │   ├── AnswerFeedback.tsx    # correct answer, explanation, retry CTA, aria-live announcement
    │   │   ├── QuizProgressHeader.tsx
    │   │   ├── QuestionPalette.tsx   # virtualised, used by final test
    │   │   └── QuizSkeleton.tsx
    │   ├── charts/                   # each file is a lazy boundary; recharts imported here only
    │   │   ├── LazyChart.tsx         # Suspense wrapper + skeleton with fixed height (CLS guard)
    │   │   ├── ProgressAreaChart.tsx
    │   │   ├── AccuracyLineChart.tsx
    │   │   ├── TopicBarChart.tsx
    │   │   └── DifficultyPieChart.tsx
    │   └── feedback/
    │       ├── NetworkStatusBanner.tsx
    │       ├── OfflineSaveIndicator.tsx
    │       ├── PwaUpdatePrompt.tsx
    │       └── InstallPrompt.tsx
    │
    ├── config/
    │   ├── env.ts                    # zod-validated import.meta.env — fails the build if misconfigured
    │   ├── query-client.ts           # QueryClient defaults (staleTime, gcTime, retry policy)
    │   ├── routes.config.ts          # path constants (single source for links + guards)
    │   └── constants.ts              # touch sizes, debounce ms, batch sizes
    │
    ├── features/                     # vertical slices: hooks + screens + local components
    │   ├── auth/
    │   │   ├── components/LoginForm.tsx  RegisterForm.tsx  ForgotPasswordForm.tsx  ResetPasswordForm.tsx
    │   │   ├── hooks/useLogin.ts  useRegister.ts  useLogout.ts  useCurrentUser.ts  useSessionExpiry.ts
    │   │   └── screens/LoginScreen.tsx  RegisterScreen.tsx  VerifyEmailScreen.tsx  ...
    │   ├── onboarding/
    │   │   ├── components/OnboardingShell.tsx  StepIndicator.tsx  CategoryCard.tsx  CourseCard.tsx
    │   │   ├── hooks/useOnboardingState.ts  useSaveOnboardingStep.ts
    │   │   └── screens/WelcomeStep.tsx  CategoryStep.tsx  CourseStep.tsx  PreferencesStep.tsx  ConfirmStep.tsx
    │   ├── student-dashboard/
    │   ├── quizzes/
    │   │   ├── components/DailyQuizRunner.tsx  QuizDayList.tsx  LockedDayCard.tsx  QuizCompleteScreen.tsx
    │   │   ├── hooks/useQuizDay.ts  useSubmitAnswer.ts  useCompleteQuiz.ts  useQuizAutosave.ts
    │   │   └── machine/quizReducer.ts     # pure reducer for local runner state (testable, no re-render churn)
    │   ├── final-test/
    │   │   ├── components/FinalTestRunner.tsx  FinalTestReview.tsx  FinalTestResult.tsx  TimerBar.tsx
    │   │   └── hooks/useFinalTestSession.ts  useFinalTestBatchSync.ts
    │   ├── progress/
    │   ├── mistakes/
    │   ├── notifications/
    │   ├── profile/
    │   ├── admin/
    │   │   ├── dashboard/  categories/  courses/  questions/  pdf-imports/  students/
    │   │   ├── notifications/  reports/
    │   │   └── components/AdminTableToolbar.tsx  BulkActionBar.tsx  FilterPanel.tsx
    │   └── pwa/
    │       ├── hooks/useServiceWorker.ts  useInstallPrompt.ts  useNetworkStatus.ts  useOfflineOutbox.ts
    │       └── register-sw.ts
    │
    ├── hooks/                        # cross-feature primitives
    │   ├── useDebouncedValue.ts  useDebouncedCallback.ts
    │   ├── useMediaQuery.ts  usePrefersReducedMotion.ts
    │   ├── useIdleTimer.ts  useVisibility.ts  useBeforeUnloadGuard.ts
    │   ├── useAnnouncer.ts           # aria-live announcements for quiz results
    │   └── usePageTitle.ts
    │
    ├── layouts/
    │   ├── RootLayout.tsx            # theme, toasts, network banner, SW prompt
    │   ├── AuthLayout.tsx
    │   ├── OnboardingLayout.tsx
    │   ├── StudentLayout.tsx         # bottom nav (mobile) / sidebar (≥ lg)
    │   ├── QuizLayout.tsx            # distraction-free, no bottom nav
    │   └── AdminLayout.tsx           # sidebar + topbar, desktop-first
    │
    ├── pages/                        # thin route modules; default-export lazily loaded screens
    │   └── (mirrors routes.config.ts)
    │
    ├── routes/
    │   ├── router.tsx                # createBrowserRouter, lazy() per route
    │   ├── guards/RequireAuth.tsx  RequireRole.tsx  RequireOnboarded.tsx  RequireEnrolment.tsx
    │   └── error/RouteErrorBoundary.tsx  NotFound.tsx
    │
    ├── services/                     # browser-side domain services (no React)
    │   ├── db/
    │   │   ├── indexed-db.ts         # idb schema + migrations, DB name includes user hash
    │   │   ├── pending-answers.repo.ts
    │   │   ├── attempt-state.repo.ts
    │   │   └── purge.ts              # logout / completion / expiry cleanup
    │   ├── sync/
    │   │   ├── outbox.ts             # enqueue, drain, backoff, dedupe by client_answer_uuid
    │   │   └── conflict.ts           # server-wins reconciliation rules
    │   ├── push/subscribe.ts         # VAPID, permission flow, key conversion
    │   ├── image/resize.ts           # canvas/OffscreenCanvas resize + webp encode before upload
    │   └── telemetry/sentry.ts       # init, PII scrubbing, web-vitals reporting
    │
    ├── store/                        # zustand — small, global, client-only
    │   ├── auth.store.ts             # user summary + in-memory access token (never persisted)
    │   ├── theme.store.ts            # 'light' | 'dark' | 'system'  (persist: localStorage, tiny)
    │   ├── network.store.ts          # online, effectiveType, pendingSyncCount
    │   ├── pwa.store.ts              # installable, updateAvailable
    │   └── preferences.store.ts      # last selected course id, list density
    │
    ├── styles/
    │   ├── index.css                 # @import "tailwindcss"; @theme tokens; base layer
    │   └── motion.css                # reduced-motion overrides
    │
    ├── types/
    │   ├── api.ts                    # ApiEnvelope<T>, ApiError, Paginated<T>, CursorPaginated<T>
    │   ├── models.ts                 # inferred from zod schemas (z.infer) — no hand-drift
    │   └── enums.ts                  # mirrors backend PHP enums exactly (single generator later)
    │
    ├── utils/
    │   ├── cn.ts  format.ts  date.ts  number.ts
    │   ├── uuid.ts                   # crypto.randomUUID with fallback
    │   ├── shuffle.ts                # seeded shuffle (server supplies the seed)
    │   └── a11y.ts
    │
    └── workers/
        ├── service-worker.ts         # hand-written, injectManifest target
        └── sw-strategies.ts          # per-route-pattern cache policy table (auditable in one place)
```

### Frontend conventions

- `features/*` may import from `components/`, `hooks/`, `services/`, `api/`. **Never the reverse.** Enforced with
  `eslint-plugin-boundaries`.
- Every route module is `lazy()`-loaded. `recharts` and `framer-motion` may only be imported inside
  `components/charts/*` and `components/motion/*` respectively — enforced by an ESLint `no-restricted-imports` rule so
  they can never leak into the initial bundle.
- Server data lives **only** in TanStack Query. Zustand holds the five things listed in `store/`, nothing more.
- Local quiz runner state uses a pure reducer (`quizReducer.ts`), which keeps re-renders scoped and makes the
  correct/incorrect/retry state machine unit-testable without mounting React.

---

## 4. Backend folder structure (`api/`)

```
api/
├── app/
│   ├── Actions/                          # single-purpose, invokable, transaction-owning units
│   │   ├── Quiz/StartQuizAttemptAction.php
│   │   ├── Quiz/SubmitAnswerAction.php
│   │   ├── Quiz/CompleteDailyQuizAction.php
│   │   ├── FinalTest/StartFinalTestAction.php
│   │   ├── FinalTest/SyncFinalTestAnswersAction.php
│   │   ├── FinalTest/FinaliseFinalTestAction.php
│   │   ├── Pdf/CreatePdfImportAction.php
│   │   ├── Pdf/ApprovePdfImportAction.php
│   │   ├── Enrolment/EnrolStudentAction.php
│   │   └── Course/GenerateDailyQuizzesAction.php
│   │
│   ├── DTOs/
│   │   ├── Quiz/AnswerSubmissionData.php
│   │   ├── Quiz/AnswerResultData.php
│   │   ├── Quiz/QuizDayStateData.php
│   │   ├── Pdf/ParsedQuestionData.php
│   │   ├── Pdf/ExtractionResultData.php
│   │   └── Notification/PushPayloadData.php
│   │
│   ├── Enums/                            # backed string enums, mirrored in web/src/types/enums.ts
│   │   ├── UserRole.php                  # admin, student
│   │   ├── UserStatus.php                # active, suspended, pending_verification
│   │   ├── ContentStatus.php             # draft, active, inactive, archived
│   │   ├── Difficulty.php                # easy, medium, hard
│   │   ├── QuestionOption.php            # a, b, c, d
│   │   ├── AttemptStatus.php             # in_progress, completed, abandoned, expired
│   │   ├── FinalTestAttemptStatus.php    # in_progress, paused, submitted, graded, expired
│   │   ├── UnlockMode.php                # immediate, next_calendar_day, scheduled
│   │   ├── PdfImportStatus.php           # uploaded, queued, processing, needs_review, completed, partially_completed, failed
│   │   ├── PdfImportItemStatus.php       # parsed, incomplete, duplicate, approved, rejected
│   │   ├── NotificationType.php          # daily_reminder, missed_quiz, streak, final_test_unlocked, course_completed, announcement, system
│   │   ├── NotificationStatus.php        # queued, sent, delivered, failed, dismissed
│   │   ├── PushSubscriptionStatus.php    # active, expired, revoked
│   │   ├── ReportType.php / ReportStatus.php
│   │   └── QueueName.php                 # critical, default, notifications, pdf, reports, mail
│   │
│   ├── Events/
│   │   ├── DailyQuizCompleted.php  QuizDayUnlocked.php
│   │   ├── FinalTestSubmitted.php  FinalTestGraded.php
│   │   ├── PdfImportCompleted.php  PdfImportFailed.php
│   │   ├── StudentEnrolled.php     StreakUpdated.php
│   │   └── SuspiciousQuizActivityDetected.php
│   │
│   ├── Exceptions/
│   │   ├── ApiException.php              # base: http status + error code + safe message
│   │   ├── QuizDayLockedException.php    # → 423
│   │   ├── FinalTestNotEligibleException.php
│   │   ├── AttemptAlreadyCompletedException.php
│   │   ├── DuplicateSubmissionException.php   # → 409
│   │   ├── PdfExtractionException.php
│   │   └── Handler customisation in bootstrap/app.php (Laravel 11+ style)
│   │
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Auth/{Register,Login,Logout,Password,EmailVerification,CurrentUser}Controller.php
│   │   │   ├── Admin/{Dashboard,ExamCategory,Course,Question,QuestionBulk,PdfImport,Student,
│   │   │   │          Enrolment,DailyQuiz,FinalTestSetting,PushCampaign,Report,ActivityLog}Controller.php
│   │   │   ├── Student/{Onboarding,Dashboard,Course,QuizDay,QuizAttempt,Answer,QuizHistory,
│   │   │   │          Progress,Mistake,PracticeSession,FinalTest,Notification,PushSubscription,Profile}Controller.php
│   │   │   └── Shared/{ExamCategoryPublic,CoursePublic,SettingController}.php
│   │   ├── Middleware/
│   │   │   ├── EnsureRole.php                 # role:admin / role:student (server-side, DB-backed)
│   │   │   ├── EnsureEmailIsVerifiedApi.php
│   │   │   ├── EnsureEnrolmentActive.php
│   │   │   ├── EnsureQuizDayUnlocked.php      # route-level guard before the controller
│   │   │   ├── IdempotencyKey.php             # Redis-backed replay protection
│   │   │   ├── ApiContractVersion.php         # adds X-Api-Contract response header
│   │   │   ├── ForceJsonResponse.php
│   │   │   ├── SecurityHeaders.php            # CSP, HSTS, X-CTO, Referrer-Policy, Permissions-Policy
│   │   │   └── LogActivity.php                # writes activity_logs for sensitive routes
│   │   ├── Requests/                          # Form Requests, one per write endpoint
│   │   │   ├── Auth/  Admin/  Student/
│   │   └── Resources/
│   │       ├── QuestionForStudentResource.php  # WHITELIST: id, text, image_url, options[]
│   │       ├── QuestionAdminResource.php       # includes answers
│   │       ├── AnswerResultResource.php
│   │       ├── QuizDayResource.php  QuizDaySummaryResource.php
│   │       ├── StudentDashboardResource.php
│   │       ├── FinalTestQuestionResource.php  FinalTestResultResource.php
│   │       ├── CourseResource.php  CourseSummaryResource.php  ExamCategoryResource.php
│   │       ├── MistakeResource.php  ProgressResource.php
│   │       ├── PdfImportResource.php  PdfImportItemResource.php
│   │       └── NotificationResource.php
│   │
│   ├── Jobs/
│   │   ├── Quiz/RecalculateStudentProgressJob.php
│   │   ├── Quiz/GenerateDailyQuizzesJob.php
│   │   ├── FinalTest/GradeFinalTestJob.php        # queue: critical
│   │   ├── FinalTest/MaterialiseFinalTestJob.php
│   │   ├── Pdf/ProcessPdfImportJob.php            # queue: pdf, ShouldBeUnique
│   │   ├── Pdf/ExtractPdfPageBatchJob.php
│   │   ├── Pdf/CleanupAbandonedImportsJob.php
│   │   ├── Notifications/DispatchDailyRemindersJob.php    # scheduler → this only
│   │   ├── Notifications/SendPushBatchJob.php             # queue: notifications
│   │   ├── Notifications/PruneExpiredSubscriptionsJob.php
│   │   ├── Reports/GenerateReportExportJob.php    # queue: reports
│   │   ├── Reports/RefreshDashboardAggregatesJob.php
│   │   └── Media/OptimiseUploadedImageJob.php
│   │
│   ├── Listeners/
│   │   ├── UnlockNextQuizDay.php  UpdateStreakOnCompletion.php
│   │   ├── InvalidateStudentDashboardCache.php
│   │   ├── NotifyFinalTestUnlocked.php  IssueCertificate.php
│   │   └── LogSecurityEvent.php
│   │
│   ├── Models/
│   │   ├── User.php  Role.php  Permission.php
│   │   ├── ExamCategory.php  Course.php  CourseEnrollment.php  OnboardingPreference.php
│   │   ├── Question.php  QuestionOption.php
│   │   ├── PdfImport.php  PdfImportItem.php
│   │   ├── DailyQuiz.php  DailyQuizQuestion.php
│   │   ├── QuizAttempt.php  QuizAttemptAnswer.php  StudentQuestionProgress.php
│   │   ├── FinalTest.php  FinalTestQuestion.php  FinalTestAttempt.php  FinalTestAnswer.php
│   │   ├── PushSubscription.php  Notification.php  NotificationDelivery.php
│   │   ├── StudentStreak.php  ActivityLog.php  Certificate.php
│   │   ├── Setting.php  ReportExport.php
│   │   └── Concerns/{HasUuid,Auditable,Cacheable}.php
│   │
│   ├── Notifications/                     # Laravel notification classes (mail + database + push channel)
│   │   ├── Channels/WebPushChannel.php
│   │   ├── DailyQuizReminderNotification.php  MissedQuizNotification.php
│   │   ├── StreakReminderNotification.php     FinalTestUnlockedNotification.php
│   │   ├── CourseCompletedNotification.php    AdminAnnouncementNotification.php
│   │   └── ReportReadyNotification.php
│   │
│   ├── Policies/
│   │   ├── QuizAccessPolicy.php           # viewDay, startAttempt, submitAnswer, complete
│   │   ├── FinalTestPolicy.php
│   │   ├── CoursePolicy.php  ExamCategoryPolicy.php  QuestionPolicy.php
│   │   ├── PdfImportPolicy.php  StudentPolicy.php  ReportPolicy.php
│   │
│   ├── Services/
│   │   ├── Auth/{AuthenticationService,TokenService,LoginThrottleService,DeviceService}.php
│   │   ├── Quiz/{DailyQuizService,QuizUnlockService,AnswerEvaluationService,
│   │   │         QuizCompletionService,QuizAttemptResumeService,PracticeSessionService}.php
│   │   ├── FinalTest/{FinalTestService,FinalTestEligibilityService,FinalTestGradingService}.php
│   │   ├── Pdf/{PdfQuestionExtractionService,PdfTextExtractor,QuestionBlockParser,
│   │   │         AnswerLineParser,ExplanationParser,DuplicateDetectionService,
│   │   │         PdfImportApprovalService}.php
│   │   │   └── Contracts/{TextExtractorInterface,OcrEngineInterface}.php   # OCR seam for later
│   │   ├── Progress/{StudentProgressService,StreakService,MistakeReviewService,
│   │   │             DashboardAggregateService}.php
│   │   ├── Notifications/{PushNotificationService,ReminderSchedulerService,
│   │   │                  SubscriptionCleanupService,NotificationPreferenceService}.php
│   │   ├── Reports/{ReportExportService,ReportQueryBuilder,CsvWriter,ExcelWriter,PdfWriter}.php
│   │   ├── Media/{ImageOptimisationService,SignedUrlService}.php
│   │   └── Support/{CacheInvalidationService,IdempotencyService,ActivityLogger,SeededShuffler}.php
│   │
│   ├── Support/
│   │   ├── ApiResponse.php                # success()/error()/paginated() → the one envelope
│   │   ├── CacheKeys.php                  # every cache key string is built here, nowhere else
│   │   ├── QueryFilters/                  # reusable whitelisted filters for admin tables
│   │   └── Sanitizer.php                  # HTML purification for question text/explanations
│   │
│   └── Providers/
│       ├── AppServiceProvider.php         # Model::preventLazyLoading() in non-prod, strict mode
│       ├── AuthServiceProvider.php        # policies, Sanctum abilities
│       ├── HorizonServiceProvider.php     # gate: admin only
│       ├── TelescopeServiceProvider.php   # local only, guarded by APP_ENV check
│       └── RouteServiceProvider.php       # rate limiters (login, quiz-write, upload, api)
│
├── bootstrap/app.php                      # middleware groups, exception rendering, scheduling
├── config/                                # + custom: quiz.php, pdf.php, push.php, reports.php, media.php
├── database/
│   ├── migrations/                        # see 03-database-design.md for ordered list
│   ├── factories/
│   └── seeders/
│       ├── DatabaseSeeder.php  RoleSeeder.php  AdminUserSeeder.php  SettingSeeder.php
│       ├── ExamCategorySeeder.php  CourseSeeder.php
│       ├── QuestionSeeder.php             # 300+ questions for one demo course (bulk insert)
│       └── DemoStudentSeeder.php          # students at day 1, day 15, day 30, final-test-ready
├── routes/
│   ├── api.php                            # requires v1/{auth,admin,student,shared}.php
│   ├── api/v1/auth.php  admin.php  student.php  shared.php
│   ├── console.php                        # scheduler definitions (dispatch-only)
│   └── channels.php                       # (unused in v1)
├── storage/app/private/{pdf-imports,reports,tmp}
├── tests/
│   ├── Pest.php  TestCase.php
│   ├── Feature/{Auth,Admin,Student,Quiz,FinalTest,Pdf,Notifications,Api}/
│   └── Unit/{Services,Parsers,Policies,Enums}/
├── phpunit.xml  pint.json  phpstan.neon  rector.php
└── composer.json
```

### Backend conventions

- **Controllers are thin**: validate (Form Request) → authorise (Policy) → call one Action or Service → return a
  Resource. Target: no controller method longer than ~15 lines.
- **Actions vs Services**: an *Action* is a single write use-case and owns its `DB::transaction`. A *Service* holds
  reusable domain logic and read paths and does **not** open transactions. This avoids nested-transaction confusion
  around row locking during quiz completion.
- `Model::preventLazyLoading()` and `Model::preventSilentlyDiscardingAttributes()` are enabled outside production, so
  an N+1 becomes a failing test rather than a slow endpoint.
- Repository pattern is deliberately **not** used. Eloquent + Services is enough; a repository layer here would add
  indirection without a second data source to justify it.
- Every cache key comes from `App\Support\CacheKeys`, so `CacheInvalidationService` can never miss a key that some
  controller invented inline.
