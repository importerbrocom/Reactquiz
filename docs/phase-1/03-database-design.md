# 03 — Database Design

MySQL 8.4, InnoDB, `utf8mb4` / `utf8mb4_0900_ai_ci`, all timestamps stored **UTC** (`timestamp` columns), student-facing
times rendered in `users.timezone`.

Conventions:
- `id` = `BIGINT UNSIGNED AUTO_INCREMENT` primary key everywhere; additionally a `uuid CHAR(36)` on entities that appear
  in URLs handed to students (`quiz_attempts`, `final_test_attempts`, `pdf_imports`, `report_exports`) so sequential IDs
  are not enumerable.
- `created_at` / `updated_at` on every table; `deleted_at` only where soft deletes are genuinely needed.
- Audit columns `created_by` / `updated_by` (`BIGINT UNSIGNED NULL`, FK → `users.id`, `ON DELETE SET NULL`) on
  admin-authored content.
- Money/score values as integers where possible; percentages as `DECIMAL(5,2)`.
- Enum-like columns are `VARCHAR(32)` + application enum + a CHECK constraint, **not** MySQL `ENUM` (MySQL enums make
  adding a value a table-rebuild migration; a varchar + CHECK is cheap to evolve and readable in dumps).

---

## 5. Database design — overview

### 5.1 Domain groups

| Group | Tables | Growth profile |
|---|---|---|
| Identity & access | `users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `personal_access_tokens`, `refresh_tokens`, `login_attempts` | small (≈ students) |
| Catalogue | `exam_categories`, `courses`, `settings` | tiny (tens) |
| Content | `questions`, `question_options`, `pdf_imports`, `pdf_import_items` | medium (10k–1M options) |
| Course structure | `daily_quizzes`, `daily_quiz_questions`, `final_tests`, `final_test_questions` | medium, deterministic |
| Enrolment | `course_enrollments`, `onboarding_preferences`, `student_streaks` | small |
| **Attempt data (hot)** | `quiz_attempts`, `quiz_attempt_questions`, `quiz_attempt_answers`, `student_question_progress`, `final_test_attempts`, `final_test_answers` | **largest and hottest** |
| Engagement | `push_subscriptions`, `notifications`, `notification_deliveries`, `notification_campaigns` | medium-high |
| Ops & audit | `activity_logs`, `certificates`, `report_exports`, `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks` | append-only, pruned |

### 5.2 Volume model (sizing the hot tables)

Assumptions: 5,000 students, one 30-day/300-question course each, average 1.35 answer submissions per question
(i.e. ~35% of questions get at least one retry).

| Table | Rows per student | At 5,000 students | Notes |
|---|---|---|---|
| `student_question_progress` | 300 | 1.5 M | one row per student+question — the workhorse for progress, mistakes, mastery |
| `quiz_attempt_questions` | 300 | 1.5 M | one row per attempt+question, current state |
| `quiz_attempt_answers` | ~405 | ~2.0 M | append-only audit of every submission incl. retries |
| `final_test_answers` | 300 | 1.5 M | one row per attempt+question |
| `quiz_attempts` | 30 | 150 K | |
| `notification_deliveries` | ~40/mo | 200 K/mo | pruned after 90 days |

Total hot-row count at 5,000 students ≈ 7 M rows — comfortable for a single well-indexed MySQL instance (single-digit GB).
The first thing to scale is **read traffic for reports** (→ read replica), not row count. Partitioning is unnecessary
until `quiz_attempt_answers` passes ~100 M rows; at that point range-partition by `created_at` (documented in
[09](./09-performance-and-scalability.md#7-when-to-partition)).

### 5.3 The three design decisions worth arguing about

**(a) `question_options` is canonical, `questions.correct_option` is a maintained denormalisation.**
The brief lists both `option_a..option_d` columns and a `question_options` table. Storing four fixed columns blocks
per-option images, 5-option questions and option shuffling; a pure child table forces a join on the hottest read path.
Resolution:
- `question_options` is the source of truth (`option_key` a–d…, `option_text`, `option_image_path`, `display_order`,
  `is_correct`).
- `questions.correct_option` (`CHAR(1)`) and `questions.correct_answer_text` are **denormalised copies**, written in the
  same transaction as the options by `QuestionWriteService`, and used by `AnswerEvaluationService` so grading is a
  single-row primary-key read with **no join**.
- Invariant enforced three ways: (1) the write service is the only path that mutates either side, (2) a nightly
  `quiz:verify-question-integrity` command reports drift, (3) a feature test asserts drift is impossible via the API.

**(b) `quiz_attempt_answers` is append-only; per-question current state lives in `quiz_attempt_questions`.**
The brief asks for "one answer record per question per attempt" *and* for every retry to be recorded — these conflict.
Splitting them satisfies both: the log table keeps full retry history (audit, anti-cheat, difficulty analytics), and the
state table gives an O(1) unique row per `(attempt, question)` for resume, "mastered" checks and the 10/10 test, without
aggregating the log on every request.

**(c) Daily quizzes and final tests are *snapshotted*, not computed at read time.**
`daily_quiz_questions` and `final_test_questions` are materialised join rows with an explicit `position`. If an admin
later edits or deactivates a question, students mid-course keep a stable, reproducible quiz, and the final test is a
provable snapshot of days 1–30. This is what makes "300 questions from the previous 30 days" deterministic and
auditable, and it makes the final-test fetch a single indexed range scan.

---

## 6. Table definitions

Abbreviated DDL (types, keys and constraints; Laravel migrations in Phase 2 will match exactly).

### 6.1 Identity & access

```sql
CREATE TABLE users (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid                  CHAR(36)     NOT NULL,
  name                  VARCHAR(120) NOT NULL,
  email                 VARCHAR(190) NOT NULL,
  email_verified_at     TIMESTAMP    NULL,
  password              VARCHAR(255) NOT NULL,          -- bcrypt cost 12 (or argon2id)
  phone                 VARCHAR(20)  NULL,
  avatar_path           VARCHAR(255) NULL,
  role                  VARCHAR(16)  NOT NULL DEFAULT 'student',  -- fast-path copy of primary role
  status                VARCHAR(24)  NOT NULL DEFAULT 'pending_verification',
  timezone              VARCHAR(64)  NOT NULL DEFAULT 'Asia/Kolkata',
  locale                VARCHAR(10)  NOT NULL DEFAULT 'en',
  onboarding_completed_at TIMESTAMP  NULL,
  last_login_at         TIMESTAMP    NULL,
  last_active_at        TIMESTAMP    NULL,              -- updated at most once / 5 min (throttled)
  failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until          TIMESTAMP    NULL,
  remember_token        VARCHAR(100) NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_uuid (uuid),
  KEY ix_users_role_status (role, status),
  KEY ix_users_last_active (last_active_at),
  KEY ix_users_deleted (deleted_at)
) ENGINE=InnoDB;
```
> `users.role` duplicates the spatie role for one reason only: the `EnsureRole` middleware runs on every request and a
> single column read beats a pivot join. Spatie's `model_has_roles` remains authoritative for *permissions*; the column
> is written by the same service that assigns roles and verified by a test.

```sql
-- spatie/laravel-permission standard tables
roles(id, name, guard_name, created_at, updated_at, UNIQUE(name, guard_name))
permissions(id, name, guard_name, ..., UNIQUE(name, guard_name))
model_has_roles(role_id, model_type, model_id, PRIMARY KEY(role_id, model_id, model_type),
                KEY ix_mhr_model (model_id, model_type))
model_has_permissions(permission_id, model_type, model_id, PRIMARY KEY(...))
role_has_permissions(permission_id, role_id, PRIMARY KEY(permission_id, role_id))

personal_access_tokens(  -- Sanctum
  id, tokenable_type, tokenable_id, name, token CHAR(64) UNIQUE, abilities TEXT,
  last_used_at, expires_at, created_at, updated_at,
  KEY ix_pat_tokenable (tokenable_type, tokenable_id), KEY ix_pat_expires (expires_at))

CREATE TABLE refresh_tokens (          -- rotating refresh-token family (see 08-security)
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       BIGINT UNSIGNED NOT NULL,
  family_id     CHAR(36)     NOT NULL,          -- rotation family; reuse of a rotated token kills the family
  token_hash    CHAR(64)     NOT NULL,          -- sha256 of the raw token, never the raw value
  access_token_id BIGINT UNSIGNED NULL,         -- paired PAT, revoked together
  device_name   VARCHAR(120) NULL,
  device_hash   CHAR(64)     NULL,              -- ua+platform hash, for "manage devices"
  ip_address    VARBINARY(16) NULL,
  expires_at    TIMESTAMP    NOT NULL,
  revoked_at    TIMESTAMP    NULL,
  rotated_at    TIMESTAMP    NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_refresh_hash (token_hash),
  KEY ix_refresh_user_active (user_id, revoked_at, expires_at),
  KEY ix_refresh_family (family_id),
  CONSTRAINT fk_refresh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE login_attempts (          -- lockout + security analytics (also mirrored in Redis for speed)
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_hash CHAR(64) NOT NULL,        -- sha256(lower(email)) — no plaintext email for failed unknown users
  user_id BIGINT UNSIGNED NULL,
  ip_address VARBINARY(16) NOT NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  KEY ix_login_email_time (email_hash, created_at),
  KEY ix_login_ip_time (ip_address, created_at)
) ENGINE=InnoDB;
```

### 6.2 Catalogue

```sql
CREATE TABLE exam_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(120) NOT NULL,
  slug         VARCHAR(140) NOT NULL,
  description  TEXT NULL,
  icon_path    VARCHAR(255) NULL,
  image_path   VARCHAR(255) NULL,
  color_token  VARCHAR(24)  NULL,                       -- design-system accent for the card
  display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status       VARCHAR(16) NOT NULL DEFAULT 'active',    -- active | inactive | archived
  courses_count INT UNSIGNED NOT NULL DEFAULT 0,         -- maintained counter cache
  created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_categories_slug (slug),
  KEY ix_categories_status_order (status, display_order),
  KEY ix_categories_deleted (deleted_at),
  CONSTRAINT chk_categories_status CHECK (status IN ('active','inactive','archived'))
) ENGINE=InnoDB;

CREATE TABLE courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_category_id BIGINT UNSIGNED NOT NULL,
  title        VARCHAR(160) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  description  TEXT NULL,
  thumbnail_path VARCHAR(255) NULL,
  -- quiz configuration (per course, per rule 6 of the brief)
  total_quiz_days        SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  daily_question_count   SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  final_test_day         SMALLINT UNSIGNED NOT NULL DEFAULT 31,
  final_test_question_count SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  pass_percentage        DECIMAL(5,2) NOT NULL DEFAULT 60.00,
  final_test_time_limit_minutes SMALLINT UNSIGNED NULL,     -- NULL = untimed
  final_test_attempt_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  shuffle_final_questions TINYINT(1) NOT NULL DEFAULT 1,
  shuffle_final_options   TINYINT(1) NOT NULL DEFAULT 0,
  allow_final_pause       TINYINT(1) NOT NULL DEFAULT 1,
  show_answers_after_submit TINYINT(1) NOT NULL DEFAULT 1,
  release_results_immediately TINYINT(1) NOT NULL DEFAULT 1,
  issue_certificate       TINYINT(1) NOT NULL DEFAULT 1,
  -- unlock + retry behaviour
  unlock_mode   VARCHAR(24) NOT NULL DEFAULT 'immediate',   -- immediate | next_calendar_day | scheduled
  full_retry_mode TINYINT(1) NOT NULL DEFAULT 0,            -- 1 = re-answer already-correct questions on retry
  start_rule    VARCHAR(24) NOT NULL DEFAULT 'on_enrolment',-- on_enrolment | fixed_date
  starts_on     DATE NULL,
  duration_days SMALLINT UNSIGNED NULL,
  -- notifications
  reminders_enabled TINYINT(1) NOT NULL DEFAULT 1,
  default_reminder_time TIME NOT NULL DEFAULT '19:00:00',
  missed_quiz_reminder_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',              -- draft | active | inactive | archived
  content_version INT UNSIGNED NOT NULL DEFAULT 1,          -- bumped when questions/days change → cache key + final-test version
  questions_count INT UNSIGNED NOT NULL DEFAULT 0,
  enrollments_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_courses_slug (slug),
  KEY ix_courses_category_status (exam_category_id, status, display_order),
  KEY ix_courses_status (status),
  CONSTRAINT fk_courses_category FOREIGN KEY (exam_category_id)
      REFERENCES exam_categories(id) ON DELETE RESTRICT,
  CONSTRAINT chk_courses_days CHECK (total_quiz_days BETWEEN 1 AND 365),
  CONSTRAINT chk_courses_daily CHECK (daily_question_count BETWEEN 1 AND 100)
) ENGINE=InnoDB;
```
> `ON DELETE RESTRICT` on the category FK is deliberate: deleting a category that owns courses with live student
> progress must fail loudly. Categories use soft delete for the admin "delete" action.

```sql
CREATE TABLE settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `group` VARCHAR(48) NOT NULL,          -- app | quiz | pdf | push | reports | security
  `key`   VARCHAR(96) NOT NULL,
  value   JSON NULL,
  type    VARCHAR(16) NOT NULL DEFAULT 'string',
  is_public TINYINT(1) NOT NULL DEFAULT 0,   -- exposed to the SPA bootstrap endpoint
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_settings_group_key (`group`, `key`)
) ENGINE=InnoDB;
```

### 6.3 Content

```sql
CREATE TABLE questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_category_id BIGINT UNSIGNED NOT NULL,
  course_id        BIGINT UNSIGNED NOT NULL,
  quiz_day_number  SMALLINT UNSIGNED NULL,        -- NULL = in the bank, not yet assigned to a day
  question_text    TEXT NOT NULL,
  question_image_path VARCHAR(255) NULL,
  correct_option      CHAR(1) NOT NULL,           -- denormalised from question_options.is_correct
  correct_answer_text VARCHAR(500) NULL,          -- denormalised text of the correct option
  explanation      TEXT NULL,
  difficulty       VARCHAR(8)  NOT NULL DEFAULT 'medium',   -- easy | medium | hard
  topic            VARCHAR(120) NULL,
  tags             JSON NULL,
  source           VARCHAR(160) NULL,             -- e.g. "PSC 2023 prelims", or pdf import ref
  status           VARCHAR(16) NOT NULL DEFAULT 'active',   -- draft | active | inactive | archived
  question_hash    CHAR(64) NOT NULL,             -- sha256 of normalised text+options → dedupe
  display_order    INT UNSIGNED NOT NULL DEFAULT 0,
  pdf_import_id    BIGINT UNSIGNED NULL,
  -- rolling analytics counters (updated by queued rollup, never per-request)
  times_served     INT UNSIGNED NOT NULL DEFAULT 0,
  times_correct    INT UNSIGNED NOT NULL DEFAULT 0,
  times_incorrect  INT UNSIGNED NOT NULL DEFAULT 0,
  difficulty_score DECIMAL(5,2) NULL,             -- observed % incorrect, for "most difficult questions"
  created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_questions_course_hash (course_id, question_hash),
  KEY ix_questions_course_day_status (course_id, quiz_day_number, status),
  KEY ix_questions_course_status_diff (course_id, status, difficulty),
  KEY ix_questions_category (exam_category_id),
  KEY ix_questions_topic (topic),
  KEY ix_questions_hash (question_hash),
  KEY ix_questions_import (pdf_import_id),
  KEY ix_questions_difficulty_score (course_id, difficulty_score),
  KEY ix_questions_deleted (deleted_at),
  FULLTEXT KEY ft_questions_text (question_text),          -- admin search
  CONSTRAINT fk_questions_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_questions_category FOREIGN KEY (exam_category_id) REFERENCES exam_categories(id) ON DELETE RESTRICT,
  CONSTRAINT fk_questions_import FOREIGN KEY (pdf_import_id) REFERENCES pdf_imports(id) ON DELETE SET NULL,
  CONSTRAINT chk_questions_option CHECK (correct_option IN ('a','b','c','d','e')),
  CONSTRAINT chk_questions_difficulty CHECK (difficulty IN ('easy','medium','hard'))
) ENGINE=InnoDB;

CREATE TABLE question_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id  BIGINT UNSIGNED NOT NULL,
  option_key   CHAR(1) NOT NULL,                  -- a | b | c | d (| e)
  option_text  VARCHAR(500) NOT NULL,
  option_image_path VARCHAR(255) NULL,
  is_correct   TINYINT(1) NOT NULL DEFAULT 0,
  display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_options_question_key (question_id, option_key),
  KEY ix_options_question_order (question_id, display_order),
  CONSTRAINT fk_options_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```
> **Exactly one correct option per question** cannot be expressed as a portable MySQL constraint. It is enforced by
> `QuestionWriteService` + a validation rule + the nightly integrity command. Documented as a known
> application-level invariant rather than pretended to be a DB constraint.

```sql
CREATE TABLE pdf_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  exam_category_id BIGINT UNSIGNED NULL,          -- may be assigned at approval time
  course_id        BIGINT UNSIGNED NULL,
  target_quiz_day  SMALLINT UNSIGNED NULL,
  uploaded_by      BIGINT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  storage_disk     VARCHAR(32) NOT NULL DEFAULT 's3_private',
  storage_path      VARCHAR(512) NOT NULL,
  mime_type        VARCHAR(96) NOT NULL,
  file_size_bytes  BIGINT UNSIGNED NOT NULL,
  file_checksum    CHAR(64) NOT NULL,             -- sha256 → duplicate upload detection
  page_count       INT UNSIGNED NULL,
  status           VARCHAR(24) NOT NULL DEFAULT 'uploaded',
  progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pages_processed  INT UNSIGNED NOT NULL DEFAULT 0,
  detected_count   INT UNSIGNED NOT NULL DEFAULT 0,
  complete_count   INT UNSIGNED NOT NULL DEFAULT 0,
  incomplete_count INT UNSIGNED NOT NULL DEFAULT 0,
  duplicate_count  INT UNSIGNED NOT NULL DEFAULT 0,
  approved_count   INT UNSIGNED NOT NULL DEFAULT 0,
  rejected_count   INT UNSIGNED NOT NULL DEFAULT 0,
  extractor        VARCHAR(32) NULL,              -- pdfparser | pdftotext | ocr(future)
  failure_reason   VARCHAR(64) NULL,              -- encrypted_pdf | no_text_layer | corrupt | timeout | empty
  failure_detail   TEXT NULL,
  started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL, approved_at TIMESTAMP NULL,
  approved_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_pdf_uuid (uuid),
  UNIQUE KEY uq_pdf_checksum_course (file_checksum, course_id),   -- same file may be legitimately reused in another course
  KEY ix_pdf_status_created (status, created_at),
  KEY ix_pdf_uploader (uploaded_by),
  KEY ix_pdf_course (course_id),
  KEY ix_pdf_checksum (file_checksum),
  CONSTRAINT fk_pdf_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
  CONSTRAINT fk_pdf_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE pdf_import_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pdf_import_id BIGINT UNSIGNED NOT NULL,
  sequence      INT UNSIGNED NOT NULL,            -- order found in the document
  page_number   INT UNSIGNED NULL,
  raw_block     TEXT NULL,                        -- original text block, for admin diffing
  question_text TEXT NULL,
  option_a VARCHAR(500) NULL, option_b VARCHAR(500) NULL,
  option_c VARCHAR(500) NULL, option_d VARCHAR(500) NULL,
  correct_option CHAR(1) NULL,
  explanation   TEXT NULL,
  difficulty    VARCHAR(8) NULL,
  topic         VARCHAR(120) NULL,
  quiz_day_number SMALLINT UNSIGNED NULL,
  question_hash CHAR(64) NULL,
  status        VARCHAR(16) NOT NULL DEFAULT 'parsed',  -- parsed|incomplete|duplicate|approved|rejected
  validation_errors JSON NULL,                    -- e.g. ["missing_option_c","no_answer_line"]
  duplicate_of_question_id BIGINT UNSIGNED NULL,
  confidence    DECIMAL(4,3) NULL,                -- parser confidence, drives review ordering
  edited_by BIGINT UNSIGNED NULL, edited_at TIMESTAMP NULL,
  created_question_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_item_import_sequence (pdf_import_id, sequence),
  KEY ix_item_import_status (pdf_import_id, status),
  KEY ix_item_hash (question_hash),
  CONSTRAINT fk_item_import FOREIGN KEY (pdf_import_id) REFERENCES pdf_imports(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_dup FOREIGN KEY (duplicate_of_question_id) REFERENCES questions(id) ON DELETE SET NULL
) ENGINE=InnoDB;
```
> `pdf_import_items` intentionally uses flat `option_a..option_d` columns: it is a staging table written by bulk insert
> from a parser, read only by the admin review UI, and discarded after approval. Normalising it would cost 4× the insert
> volume for no query benefit.

### 6.4 Course structure (snapshots)

```sql
CREATE TABLE daily_quizzes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id   BIGINT UNSIGNED NOT NULL,
  day_number  SMALLINT UNSIGNED NOT NULL,
  title       VARCHAR(160) NULL,                  -- optional "Day 7 — Indian Polity"
  question_count SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  status      VARCHAR(16) NOT NULL DEFAULT 'active',
  content_version INT UNSIGNED NOT NULL DEFAULT 1,
  available_from DATE NULL,                       -- used only by unlock_mode = scheduled
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_daily_course_day (course_id, day_number),
  KEY ix_daily_course_status (course_id, status),
  CONSTRAINT fk_daily_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE daily_quiz_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  daily_quiz_id BIGINT UNSIGNED NOT NULL,
  question_id   BIGINT UNSIGNED NOT NULL,
  position      SMALLINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_dqq_quiz_question (daily_quiz_id, question_id),
  UNIQUE KEY uq_dqq_quiz_position (daily_quiz_id, position),
  KEY ix_dqq_question (question_id),
  CONSTRAINT fk_dqq_quiz FOREIGN KEY (daily_quiz_id) REFERENCES daily_quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_dqq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE final_tests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  course_id BIGINT UNSIGNED NOT NULL,
  version   INT UNSIGNED NOT NULL DEFAULT 1,      -- new version when course content_version changes
  day_number SMALLINT UNSIGNED NOT NULL DEFAULT 31,
  title VARCHAR(160) NULL,
  question_count SMALLINT UNSIGNED NOT NULL DEFAULT 300,
  pass_percentage DECIMAL(5,2) NOT NULL DEFAULT 60.00,
  time_limit_minutes SMALLINT UNSIGNED NULL,
  attempt_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  shuffle_questions TINYINT(1) NOT NULL DEFAULT 1,
  shuffle_options   TINYINT(1) NOT NULL DEFAULT 0,
  allow_pause TINYINT(1) NOT NULL DEFAULT 1,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  materialised_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_final_course_version (course_id, version),
  KEY ix_final_course_status (course_id, status),
  CONSTRAINT fk_final_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE final_test_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  final_test_id BIGINT UNSIGNED NOT NULL,
  question_id   BIGINT UNSIGNED NOT NULL,
  source_day_number SMALLINT UNSIGNED NOT NULL,   -- kept for day-wise result breakdown without extra joins
  position      SMALLINT UNSIGNED NOT NULL,
  marks         DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_ftq_test_question (final_test_id, question_id),
  UNIQUE KEY uq_ftq_test_position (final_test_id, position),
  KEY ix_ftq_test_day (final_test_id, source_day_number),
  CONSTRAINT fk_ftq_test FOREIGN KEY (final_test_id) REFERENCES final_tests(id) ON DELETE CASCADE,
  CONSTRAINT fk_ftq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### 6.5 Enrolment

```sql
CREATE TABLE course_enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  exam_category_id BIGINT UNSIGNED NOT NULL,      -- denormalised for category-filtered reports
  status    VARCHAR(16) NOT NULL DEFAULT 'active',-- active | paused | completed | cancelled
  is_active TINYINT(1) NOT NULL DEFAULT 1,        -- generated-ish flag used by the unique index
  -- authoritative progress pointers (maintained transactionally on completion)
  current_day        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  highest_unlocked_day SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  completed_days     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_completed_day SMALLINT UNSIGNED NULL,
  last_completed_at  TIMESTAMP NULL,
  progress_percent   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  accuracy_percent   DECIMAL(5,2) NULL,
  total_study_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  final_test_unlocked_at TIMESTAMP NULL,
  final_test_passed_at   TIMESTAMP NULL,
  started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
  enrolled_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, deleted_at TIMESTAMP NULL,
  UNIQUE KEY uq_enrol_user_course_active (user_id, course_id, is_active),
  KEY ix_enrol_user_status (user_id, status),
  KEY ix_enrol_course_status (course_id, status),
  KEY ix_enrol_category (exam_category_id),
  KEY ix_enrol_last_completed (last_completed_at),
  CONSTRAINT fk_enrol_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrol_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```
> `is_active` is `1` for a live enrolment and set to `NULL` when cancelled/superseded. Because MySQL treats `NULL`s as
> distinct in unique indexes, `uq_enrol_user_course_active` enforces **exactly one active enrolment per student per
> course** while still allowing a history of cancelled ones. This is the standard portable trick for
> "unique where active".

```sql
CREATE TABLE onboarding_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  step             VARCHAR(24) NOT NULL DEFAULT 'welcome',  -- resume point
  completed_steps  JSON NULL,
  selected_exam_category_id BIGINT UNSIGNED NULL,
  selected_course_id BIGINT UNSIGNED NULL,
  reminder_time TIME NULL,
  notifications_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  language VARCHAR(10) NOT NULL DEFAULT 'en',
  timezone VARCHAR(64) NULL,
  study_goal VARCHAR(32) NULL,                    -- casual | steady | intense
  daily_goal_minutes SMALLINT UNSIGNED NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_onboarding_user (user_id),
  CONSTRAINT fk_onboarding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE student_streaks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,             -- streaks are per course (a student may enrol in two)
  current_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  longest_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_activity_date DATE NULL,                   -- in the student's timezone at write time
  freeze_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_streak_user_course (user_id, course_id),
  KEY ix_streak_last_activity (last_activity_date),
  CONSTRAINT fk_streak_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_streak_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### 6.6 Attempt data (the hot path)

```sql
CREATE TABLE quiz_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  user_id       BIGINT UNSIGNED NOT NULL,
  course_id     BIGINT UNSIGNED NOT NULL,
  enrollment_id BIGINT UNSIGNED NOT NULL,
  daily_quiz_id BIGINT UNSIGNED NOT NULL,
  day_number    SMALLINT UNSIGNED NOT NULL,       -- denormalised: avoids joining daily_quizzes on every read
  attempt_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status        VARCHAR(16) NOT NULL DEFAULT 'in_progress',  -- in_progress|completed|abandoned|expired
  required_count SMALLINT UNSIGNED NOT NULL,       -- snapshot of course.daily_question_count
  mastered_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_submissions SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  wrong_submissions   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  retry_count         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  score          SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- = mastered_count at completion
  current_position SMALLINT UNSIGNED NOT NULL DEFAULT 1,     -- resume pointer
  time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  started_at     TIMESTAMP NULL,
  completed_at   TIMESTAMP NULL,
  last_activity_at TIMESTAMP NULL,
  device_type    VARCHAR(24) NULL,
  device_hash    CHAR(64) NULL,
  sync_status    VARCHAR(16) NOT NULL DEFAULT 'synced',      -- synced | pending_client
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_attempt_uuid (uuid),
  UNIQUE KEY uq_attempt_user_quiz_number (user_id, daily_quiz_id, attempt_number),
  KEY ix_attempt_user_quiz_status (user_id, daily_quiz_id, status),
  KEY ix_attempt_user_course_day (user_id, course_id, day_number),
  KEY ix_attempt_status_activity (status, last_activity_at),  -- abandoned-attempt sweeper
  KEY ix_attempt_course_completed (course_id, completed_at),  -- reporting
  CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_attempt_quiz FOREIGN KEY (daily_quiz_id) REFERENCES daily_quizzes(id) ON DELETE CASCADE,
  CONSTRAINT fk_attempt_enrol FOREIGN KEY (enrollment_id) REFERENCES course_enrollments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- current state of each question within an attempt (one row per question, upserted)
CREATE TABLE quiz_attempt_questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_attempt_id BIGINT UNSIGNED NOT NULL,
  question_id     BIGINT UNSIGNED NOT NULL,
  position        SMALLINT UNSIGNED NOT NULL,
  state           VARCHAR(16) NOT NULL DEFAULT 'unanswered', -- unanswered|retry_required|mastered
  selected_option CHAR(1) NULL,                   -- last selection
  wrong_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  submission_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  first_answered_at TIMESTAMP NULL,
  mastered_at     TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_aq_attempt_question (quiz_attempt_id, question_id),
  KEY ix_aq_attempt_state (quiz_attempt_id, state),
  KEY ix_aq_attempt_position (quiz_attempt_id, position),
  CONSTRAINT fk_aq_attempt FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_aq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- append-only submission log: every answer and every retry (business rule 19)
CREATE TABLE quiz_attempt_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quiz_attempt_id BIGINT UNSIGNED NOT NULL,
  question_id     BIGINT UNSIGNED NOT NULL,
  client_answer_uuid CHAR(36) NOT NULL,           -- client-generated → idempotent offline replay
  selected_option CHAR(1) NOT NULL,
  is_correct      TINYINT(1) NOT NULL,
  submission_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,    -- 1 = first try, 2+ = retry
  time_spent_ms   INT UNSIGNED NULL,
  answered_at     TIMESTAMP NOT NULL,             -- client-reported, clamped to server window
  recorded_at     TIMESTAMP NOT NULL,             -- server truth
  was_offline     TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  UNIQUE KEY uq_answer_client_uuid (quiz_attempt_id, client_answer_uuid),
  KEY ix_answer_attempt_question (quiz_attempt_id, question_id),
  KEY ix_answer_question_correct (question_id, is_correct),   -- difficulty analytics rollup
  KEY ix_answer_recorded (recorded_at),
  CONSTRAINT fk_answer_attempt FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- lifetime mastery per student per question (drives mistakes, weak topics, practice mode)
CREATE TABLE student_question_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     BIGINT UNSIGNED NOT NULL,
  course_id   BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  day_number  SMALLINT UNSIGNED NULL,
  attempts        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  wrong_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_selected_option CHAR(1) NULL,
  is_mastered  TINYINT(1) NOT NULL DEFAULT 0,
  mastered_at  TIMESTAMP NULL,
  first_attempt_correct TINYINT(1) NULL,           -- "got it right first time" analytics
  last_attempted_at TIMESTAMP NULL,
  total_time_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_sqp_user_question (user_id, question_id),
  KEY ix_sqp_user_course_question (user_id, course_id, question_id),
  KEY ix_sqp_user_course_mastered (user_id, course_id, is_mastered),
  KEY ix_sqp_user_wrong (user_id, course_id, wrong_count),
  KEY ix_sqp_question (question_id),
  CONSTRAINT fk_sqp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sqp_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sqp_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE final_test_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  final_test_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  enrollment_id BIGINT UNSIGNED NOT NULL,
  attempt_number SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status VARCHAR(16) NOT NULL DEFAULT 'in_progress', -- in_progress|paused|submitted|graded|expired
  question_order JSON NOT NULL,                    -- materialised shuffled order (server-authoritative)
  shuffle_seed INT UNSIGNED NOT NULL,
  current_position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  answered_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  flagged_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  correct_count  SMALLINT UNSIGNED NULL,
  incorrect_count SMALLINT UNSIGNED NULL,
  unanswered_count SMALLINT UNSIGNED NULL,
  score DECIMAL(7,2) NULL,
  percentage DECIMAL(5,2) NULL,
  passed TINYINT(1) NULL,
  time_limit_seconds INT UNSIGNED NULL,
  time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at TIMESTAMP NULL,                       -- server-computed deadline; client timer is cosmetic
  started_at TIMESTAMP NULL, paused_at TIMESTAMP NULL,
  submitted_at TIMESTAMP NULL, graded_at TIMESTAMP NULL,
  last_sync_at TIMESTAMP NULL,
  result_released_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_fta_uuid (uuid),
  UNIQUE KEY uq_fta_user_test_number (user_id, final_test_id, attempt_number),
  KEY ix_fta_user_test (user_id, final_test_id),
  KEY ix_fta_status_activity (status, last_sync_at),
  KEY ix_fta_course_submitted (course_id, submitted_at),
  CONSTRAINT fk_fta_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fta_test FOREIGN KEY (final_test_id) REFERENCES final_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE final_test_answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  final_test_attempt_id BIGINT UNSIGNED NOT NULL,
  question_id BIGINT UNSIGNED NOT NULL,
  selected_option CHAR(1) NULL,                    -- NULL = visited but unanswered
  is_correct TINYINT(1) NULL,                      -- filled at grading, not at save time
  is_flagged TINYINT(1) NOT NULL DEFAULT 0,
  marks_awarded DECIMAL(5,2) NULL,
  time_spent_ms INT UNSIGNED NULL,
  client_batch_uuid CHAR(36) NULL,                 -- idempotent batch sync
  answered_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_fta_answer (final_test_attempt_id, question_id),
  KEY ix_fta_answer_attempt (final_test_attempt_id, is_correct),
  KEY ix_fta_answer_question (question_id),
  CONSTRAINT fk_ftaa_attempt FOREIGN KEY (final_test_attempt_id)
      REFERENCES final_test_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ftaa_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```
> `final_test_answers.is_correct` is deliberately **not** computed on save. Saving 300 answers in batches must not leak
> correctness; grading happens once at finalisation (`GradeFinalTestJob`, `critical` queue). This also makes batch
> saves pure upserts with no reads of `questions`.

### 6.7 Engagement

```sql
CREATE TABLE push_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint TEXT NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,                 -- sha256(endpoint): TEXT can't be uniquely indexed usefully
  public_key VARCHAR(255) NOT NULL,                -- p256dh
  auth_token VARCHAR(255) NOT NULL,
  content_encoding VARCHAR(24) NOT NULL DEFAULT 'aes128gcm',
  device_label VARCHAR(120) NULL,
  user_agent VARCHAR(255) NULL,
  browser VARCHAR(48) NULL, platform VARCHAR(48) NULL,
  timezone VARCHAR(64) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',    -- active|expired|revoked
  failure_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_success_at TIMESTAMP NULL, last_failure_at TIMESTAMP NULL,
  last_seen_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  KEY ix_push_user_status (user_id, status),
  KEY ix_push_status_failures (status, failure_count),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (                       -- the student-visible inbox record
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  campaign_id BIGINT UNSIGNED NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body VARCHAR(500) NOT NULL,
  action_url VARCHAR(255) NULL,
  data JSON NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',    -- queued|sent|delivered|failed|dismissed
  read_at TIMESTAMP NULL,
  scheduled_for TIMESTAMP NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY ix_notif_user_status_created (user_id, status, created_at),
  KEY ix_notif_user_read (user_id, read_at),
  KEY ix_notif_type_created (type, created_at),
  KEY ix_notif_campaign (campaign_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_deliveries (             -- per-device delivery outcome (push transport log)
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NOT NULL,
  push_subscription_id BIGINT UNSIGNED NULL,
  channel VARCHAR(16) NOT NULL DEFAULT 'webpush',  -- webpush | mail | database
  status VARCHAR(16) NOT NULL DEFAULT 'queued',
  http_status SMALLINT UNSIGNED NULL,
  error_code VARCHAR(48) NULL,
  attempt SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  dispatched_at TIMESTAMP NULL, settled_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY ix_delivery_notification (notification_id),
  KEY ix_delivery_status_created (status, created_at),
  KEY ix_delivery_subscription (push_subscription_id),
  CONSTRAINT fk_delivery_notification FOREIGN KEY (notification_id)
      REFERENCES notifications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notification_campaigns (              -- admin announcements
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL, body VARCHAR(500) NOT NULL,
  action_url VARCHAR(255) NULL,
  audience VARCHAR(24) NOT NULL DEFAULT 'all',     -- all|course|category|inactive|custom
  audience_filter JSON NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',     -- draft|scheduled|sending|sent|cancelled
  scheduled_for TIMESTAMP NULL,
  target_count INT UNSIGNED NOT NULL DEFAULT 0,
  sent_count INT UNSIGNED NOT NULL DEFAULT 0,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  batch_id CHAR(36) NULL,                          -- Laravel job batch id for progress
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  KEY ix_campaign_status_scheduled (status, scheduled_for)
) ENGINE=InnoDB;
```

### 6.8 Ops & audit

```sql
CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event VARCHAR(64) NOT NULL,                      -- auth.login_failed, quiz.completed, admin.question.deleted ...
  subject_type VARCHAR(96) NULL, subject_id BIGINT UNSIGNED NULL,
  properties JSON NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  severity VARCHAR(16) NOT NULL DEFAULT 'info',    -- info|warning|security
  created_at TIMESTAMP NULL,
  KEY ix_activity_user_created (user_id, created_at),
  KEY ix_activity_event_created (event, created_at),
  KEY ix_activity_subject (subject_type, subject_id),
  KEY ix_activity_severity_created (severity, created_at)
) ENGINE=InnoDB;

CREATE TABLE certificates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  final_test_attempt_id BIGINT UNSIGNED NOT NULL,
  serial VARCHAR(32) NOT NULL,                     -- human-quotable, e.g. QP-2026-0000123
  score DECIMAL(7,2) NOT NULL, percentage DECIMAL(5,2) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'pending',   -- pending|issued|revoked
  storage_path VARCHAR(512) NULL,
  issued_at TIMESTAMP NULL, revoked_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_cert_uuid (uuid),
  UNIQUE KEY uq_cert_serial (serial),
  UNIQUE KEY uq_cert_attempt (final_test_attempt_id),
  KEY ix_cert_user_course (user_id, course_id),
  CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cert_attempt FOREIGN KEY (final_test_attempt_id)
      REFERENCES final_test_attempts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE report_exports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  type VARCHAR(48) NOT NULL,                       -- student_progress|course_completion|question_difficulty|...
  format VARCHAR(8) NOT NULL,                      -- csv|xlsx|pdf
  filters JSON NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'queued',    -- queued|processing|completed|failed|expired
  row_count INT UNSIGNED NULL,
  storage_disk VARCHAR(32) NULL, storage_path VARCHAR(512) NULL,
  file_size_bytes BIGINT UNSIGNED NULL,
  error_message VARCHAR(255) NULL,
  started_at TIMESTAMP NULL, completed_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,                       -- signed URL + file both expire
  downloaded_at TIMESTAMP NULL, download_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_report_uuid (uuid),
  KEY ix_report_user_status (requested_by, status),
  KEY ix_report_status_expires (status, expires_at)
) ENGINE=InnoDB;

-- Reporting rollups (written by RefreshDashboardAggregatesJob; read by the admin dashboard)
CREATE TABLE report_daily_metrics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  metric_date DATE NOT NULL,
  course_id BIGINT UNSIGNED NULL,                  -- NULL row = platform-wide total
  exam_category_id BIGINT UNSIGNED NULL,
  active_students INT UNSIGNED NOT NULL DEFAULT 0,
  new_students INT UNSIGNED NOT NULL DEFAULT 0,
  quizzes_completed INT UNSIGNED NOT NULL DEFAULT 0,
  final_tests_completed INT UNSIGNED NOT NULL DEFAULT 0,
  answers_submitted INT UNSIGNED NOT NULL DEFAULT 0,
  correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
  avg_score DECIMAL(5,2) NULL,
  avg_accuracy DECIMAL(5,2) NULL,
  study_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
  notifications_sent INT UNSIGNED NOT NULL DEFAULT 0,
  notifications_failed INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_metrics_date_course (metric_date, course_id),
  KEY ix_metrics_course_date (course_id, metric_date),
  KEY ix_metrics_category_date (exam_category_id, metric_date)
) ENGINE=InnoDB;

-- Laravel framework tables (standard): jobs, job_batches, failed_jobs, cache, cache_locks,
-- password_reset_tokens, sessions (only if SESSION_DRIVER=database; we use redis)
```

### 6.9 Migration order

```
01 users → 02 spatie(roles/permissions/pivots) → 03 personal_access_tokens → 04 refresh_tokens
→ 05 login_attempts → 06 password_reset_tokens → 07 settings → 08 exam_categories → 09 courses
→ 10 pdf_imports → 11 questions → 12 question_options → 13 pdf_import_items (FKs → questions)
→ 14 daily_quizzes → 15 daily_quiz_questions → 16 final_tests → 17 final_test_questions
→ 18 course_enrollments → 19 onboarding_preferences → 20 student_streaks
→ 21 quiz_attempts → 22 quiz_attempt_questions → 23 quiz_attempt_answers → 24 student_question_progress
→ 25 final_test_attempts → 26 final_test_answers
→ 27 push_subscriptions → 28 notification_campaigns → 29 notifications → 30 notification_deliveries
→ 31 activity_logs → 32 certificates → 33 report_exports → 34 report_daily_metrics
→ 35 jobs/job_batches/failed_jobs/cache
```
`pdf_imports` precedes `questions` because `questions.pdf_import_id` references it; `pdf_import_items` follows
`questions` because it references `questions.id` for duplicate marking.

---

## 7. Entity relationships

### 7.1 Relationship map

```
ExamCategory 1───n Course 1───n Question 1───n QuestionOption
     │                 │             └──n DailyQuizQuestion ──1 DailyQuiz
     │                 ├───n DailyQuiz 1───n DailyQuizQuestion
     │                 ├───1 FinalTest(version) 1───n FinalTestQuestion ──1 Question
     │                 └───n CourseEnrollment n───1 User
     └───n CourseEnrollment (denormalised category_id)

User 1───1 OnboardingPreference
User 1───n CourseEnrollment 1───n QuizAttempt 1───n QuizAttemptQuestion
                                        └────────n QuizAttemptAnswer
User 1───n StudentQuestionProgress n───1 Question
User 1───n FinalTestAttempt 1───n FinalTestAnswer n───1 Question
User 1───n PushSubscription 1───n NotificationDelivery n───1 Notification
User 1───n Notification n───1 NotificationCampaign (nullable)
User 1───n StudentStreak (one per course)  ── unique(user_id, course_id)
User 1───n ActivityLog, Certificate, ReportExport(requested_by)
PdfImport 1───n PdfImportItem ──0..1 Question (duplicate_of / created_question)
```

### 7.2 Eloquent definitions (Phase 2 will implement exactly these)

| Model | Relationships |
|---|---|
| `ExamCategory` | `hasMany(Course)`, `hasMany(Question)`, `hasMany(CourseEnrollment)` |
| `Course` | `belongsTo(ExamCategory)`, `hasMany(Question)`, `hasMany(DailyQuiz)`, `hasOne(FinalTest)->latestOfMany('version')`, `hasMany(FinalTest)`, `hasMany(CourseEnrollment)`, `belongsToMany(User, 'course_enrollments')` |
| `Question` | `belongsTo(Course)`, `belongsTo(ExamCategory)`, `hasMany(QuestionOption)`, `belongsToMany(DailyQuiz, 'daily_quiz_questions')`, `hasMany(StudentQuestionProgress)`, `belongsTo(PdfImport)` |
| `DailyQuiz` | `belongsTo(Course)`, `belongsToMany(Question, 'daily_quiz_questions')->withPivot('position')->orderByPivot('position')`, `hasMany(QuizAttempt)` |
| `CourseEnrollment` | `belongsTo(User)`, `belongsTo(Course)`, `hasMany(QuizAttempt)`, `hasMany(FinalTestAttempt)`, `hasOne(StudentStreak)` |
| `QuizAttempt` | `belongsTo(User)`, `belongsTo(DailyQuiz)`, `belongsTo(CourseEnrollment)`, `hasMany(QuizAttemptQuestion)`, `hasMany(QuizAttemptAnswer)` |
| `FinalTest` | `belongsTo(Course)`, `belongsToMany(Question, 'final_test_questions')->withPivot('position','source_day_number','marks')`, `hasMany(FinalTestAttempt)` |
| `FinalTestAttempt` | `belongsTo(User)`, `belongsTo(FinalTest)`, `hasMany(FinalTestAnswer)`, `hasOne(Certificate)` |
| `User` | `hasMany(CourseEnrollment)`, `hasOne(OnboardingPreference)`, `hasMany(QuizAttempt)`, `hasMany(StudentQuestionProgress)`, `hasMany(FinalTestAttempt)`, `hasMany(PushSubscription)`, `hasMany(Notification)`, `hasMany(StudentStreak)`, `hasMany(Certificate)`, `hasMany(ActivityLog)`, spatie's `roles`/`permissions` |
| `PdfImport` | `belongsTo(User,'uploaded_by')`, `belongsTo(Course)`, `hasMany(PdfImportItem)`, `hasMany(Question)` |
| `Notification` | `belongsTo(User)`, `belongsTo(NotificationCampaign)`, `hasMany(NotificationDelivery)` |

### 7.3 Which relationships should be pivot tables — and why

| Relationship | Shape | Reason |
|---|---|---|
| DailyQuiz ↔ Question | **pivot with payload** (`daily_quiz_questions`: `position`) | A question could legitimately appear in a revision day later; `position` is pivot data. Pivot also makes "move question between days" a one-row update rather than a content edit. |
| FinalTest ↔ Question | **pivot with payload** (`final_test_questions`: `position`, `source_day_number`, `marks`) | Snapshot semantics + per-question marks + day-wise breakdown without joining back through `daily_quiz_questions`. |
| User ↔ Course | **rich pivot promoted to a model** (`course_enrollments`) | It carries progress state, timestamps and status — far beyond a pivot, so it is a first-class entity with its own ID that other tables (`quiz_attempts`) reference. |
| User ↔ Role | **pivot** (`model_has_roles`, spatie polymorphic) | Standard, and lets a future "content editor" role appear without schema change. |
| User ↔ Question | **not a pivot** — `student_question_progress` is an entity | It holds counters and mastery timestamps; it is the single most-queried table for progress and mistakes. |
| Question ↔ Option | **child table**, not pivot | Options belong to exactly one question. |
| Notification ↔ PushSubscription | **`notification_deliveries` join entity** | One inbox notification fans out to N devices with independent outcomes; the join row is the delivery record. |
| Student ↔ Streak | `hasMany` with `unique(user_id, course_id)` | Per-course streaks; a single global streak would misreport students in two courses. Choosing per-course is a product decision, recorded here. |

---

## 8. Indexing plan

Every index below states **why it exists** (which query it serves) and **what it costs** on writes. Rule applied
throughout: an index is justified only if a named query needs it, and left-most columns follow the equality-then-range
principle.

### 8.1 Hot-path indexes (must exist before launch)

| Index | Serves | Benefit | Write cost |
|---|---|---|---|
| `questions(course_id, quiz_day_number, status)` | daily quiz question fetch; admin day filter | Turns "10 questions for day 7" into a narrow range scan of ~10 rows instead of a course-wide scan | 1 extra B-tree write per question insert/update of those columns. Questions are written rarely (imports) and read constantly → clearly worth it |
| `questions(course_id, question_hash)` UNIQUE | duplicate detection at import | O(log n) dedupe check per parsed question, and makes duplicates impossible even under concurrent imports | Unique index maintenance on bulk insert; ~5–8% slower bulk import, acceptable because imports are queued |
| `questions(course_id, status, difficulty)` | admin filters, difficulty analytics | Avoids filesort on the admin table's most common filter combo | Low; same low write frequency |
| `questions(course_id, difficulty_score)` | "most difficult questions" report | Index-ordered top-N without sorting the table | Written only by the nightly rollup job |
| FULLTEXT `questions(question_text)` | admin question search | `MATCH ... AGAINST` instead of `LIKE '%…%'` full scan; ~100× faster at 100k questions | Fulltext index maintenance is the heaviest of these; mitigated by inserting via bulk import and rebuilding only there |
| `daily_quizzes(course_id, day_number)` UNIQUE | day lookup on every quiz request | Primary access path; also enforces one quiz per course/day | Trivial (≤ 31 rows per course) |
| `daily_quiz_questions(daily_quiz_id, position)` UNIQUE | ordered question fetch | Covering-ish index returning questions already in display order → no sort | Trivial |
| `quiz_attempts(user_id, daily_quiz_id, attempt_number)` UNIQUE | resume/start attempt; prevents duplicate attempts | Single-row lookup on the hottest write path; the unique constraint is what makes concurrent "start attempt" safe | One index write per attempt (30 per student) |
| `quiz_attempts(user_id, daily_quiz_id, status)` | "is there an in-progress attempt?" | Avoids scanning a student's attempt history | Low |
| `quiz_attempts(user_id, course_id, day_number)` | dashboard + progress calendar | One index scan returns the whole 30-day timeline | Low |
| `quiz_attempt_questions(quiz_attempt_id, question_id)` UNIQUE | per-question state upsert | Makes the answer-submission upsert a single-row operation and enforces "one state row per question per attempt" | 1 write per submission — unavoidable and cheap |
| `quiz_attempt_questions(quiz_attempt_id, state)` | resume payload: which are mastered / retry-required | Answers the 10/10 question without touching the answer log | Low |
| `quiz_attempt_answers(quiz_attempt_id, client_answer_uuid)` UNIQUE | **idempotent offline replay** | The single most important constraint for offline correctness: a replayed answer hits a duplicate-key error instead of double-recording | 1 unique index write per submission |
| `quiz_attempt_answers(quiz_attempt_id, question_id)` | retry history for a question | Range scan of 1–4 rows | Low |
| `quiz_attempt_answers(question_id, is_correct)` | difficulty rollup job | Lets the nightly job aggregate per question without a full scan | Moderate on a 2 M-row append-only table; justified because the alternative is a nightly full scan that would compete with student writes |
| `student_question_progress(user_id, question_id)` UNIQUE | mastery upsert; "already mastered?" | Single-row upsert; enforces one progress row per student/question | 1 write per submission |
| `student_question_progress(user_id, course_id, is_mastered)` | mistake review, mastered counts | Filtered scan of only the student's rows | Low |
| `student_question_progress(user_id, course_id, wrong_count)` | "frequently incorrect" list, practice mode | Ordered top-N per student without sort | Low |
| `final_test_attempts(user_id, final_test_id)` | resume/eligibility | Single-row | Trivial |
| `final_test_answers(final_test_attempt_id, question_id)` UNIQUE | batch upsert of answers | Makes batch sync a pure `INSERT ... ON DUPLICATE KEY UPDATE` of ≤ 20 rows; enforces one answer per question | 1 write per answer; batching amortises it |
| `course_enrollments(user_id, course_id, is_active)` UNIQUE | enrolment lookup on every student request; one-active-enrolment rule | Single-row access path for the middleware | Trivial |
| `users(email)` UNIQUE | login | Required | Trivial |
| `users(role, status)` | admin student lists, active counts | Avoids scanning all users | Trivial |
| `notifications(user_id, status, created_at)` | inbox list + unread count | Index-only ordering for the paginated inbox | 1 per notification |
| `push_subscriptions(endpoint_hash)` UNIQUE | subscribe/upsert; duplicate device prevention | Exact-match on a fixed-length hash (a `TEXT` endpoint cannot be usefully unique-indexed) | Trivial |
| `push_subscriptions(user_id, status)` | fan-out to a student's active devices | Small range scan | Trivial |
| `pdf_imports(status, created_at)` | admin import queue view | Ordered filter | Trivial |
| `pdf_imports(file_checksum)` | duplicate upload detection | Exact match before queuing work | Trivial |

### 8.2 Indexes deliberately **not** created

| Not created | Why |
|---|---|
| `questions(topic)` as a composite with course/status | `topic` is low-selectivity free text; a single-column index is enough for the report, and adding it to the hot composite would bloat it. Revisit when topics become a normalised table. |
| `quiz_attempt_answers(user_id, ...)` | `user_id` is not on the table by design — it is reachable via `quiz_attempt_id` and denormalising it would add a column and an index to the highest-volume table for queries that are all attempt-scoped. |
| Index on `questions.tags` (JSON) | Tag filtering is an admin-only, low-frequency query; a generated column + index will be added only if the admin table's tag filter shows up in slow-query logs. |
| `users(created_at)` | The admin dashboard reads new-student counts from `report_daily_metrics`, not from `users`. |
| Separate single-column indexes duplicating a composite's prefix (e.g. `questions(course_id)`) | Redundant: the leftmost prefix of `questions(course_id, quiz_day_number, status)` already serves it. Removing these avoids pure write overhead. |

### 8.3 EXPLAIN verification plan

Each of these must be captured in `docs/runbooks/query-baselines.md` with `EXPLAIN ANALYZE` output on a seeded
5,000-student dataset, and re-checked whenever the query changes:

1. Daily quiz question fetch (expect `ref` on `uq_dqq_quiz_position`, ≤ 10 rows examined, no `Using temporary`).
2. Answer submission's three statements (expect `const`/`eq_ref` on all three, 1 row each).
3. Completion check (expect a single covering read of `quiz_attempt_questions(quiz_attempt_id, state)`).
4. Student dashboard summary (expect ≤ 4 queries total, all `ref`, no `filesort`).
5. Final-test question page fetch (expect `range` on `uq_ftq_test_position`, ≤ 20 rows).
6. Mistake review list (expect `ref` on `ix_sqp_user_course_mastered`, `LIMIT` honoured without sorting the set).
7. Admin question search with filters (expect fulltext + index intersection, `rows` ≪ table size).
8. Admin dashboard (expect all reads from `report_daily_metrics`, never from `quiz_attempt_answers`).

Guardrails in CI: a Pest test asserts query **counts** for the quiz endpoints (`assertQueryCount(≤ 4)` style) so an
accidental N+1 fails the build rather than the load test.

---

## 9. Unique-constraint plan

| # | Rule from the brief | Implementation | Failure behaviour |
|---|---|---|---|
| 1 | Unique question hash within a course | `UNIQUE (course_id, question_hash)` | Import marks the item `duplicate` and links `duplicate_of_question_id`; API returns 422 with the existing question id |
| 2 | One active enrolment per student per course | `UNIQUE (user_id, course_id, is_active)` with `is_active` = 1 or NULL | Re-enrol returns the existing enrolment (idempotent) rather than erroring |
| 3 | One daily quiz per course per day | `UNIQUE (course_id, day_number)` | Generation is idempotent (`upsert`), so re-running the generator is safe |
| 4 | One answer *state* record per question per attempt | `UNIQUE (quiz_attempt_id, question_id)` on `quiz_attempt_questions` | Upsert path; never an error |
| 5 | No duplicate answer *submissions* (offline replay) | `UNIQUE (quiz_attempt_id, client_answer_uuid)` + Redis idempotency key | Duplicate insert caught → the original stored response is replayed with HTTP 200 and `X-Idempotent-Replay: true` |
| 6 | One progress record per student per question | `UNIQUE (user_id, question_id)` on `student_question_progress` | Upsert |
| 7 | One push subscription per endpoint | `UNIQUE (endpoint_hash)` | Re-subscribe updates `user_id`/`last_seen_at` instead of inserting (handles a shared device) |
| 8 | One final test per course version | `UNIQUE (course_id, version)` | New version row created when `courses.content_version` increments |
| 9 | One attempt per user/quiz/attempt-number | `UNIQUE (user_id, daily_quiz_id, attempt_number)` | Concurrent "start attempt" → one wins, the loser re-reads and resumes |
| 10 | One final-test answer per question per attempt | `UNIQUE (final_test_attempt_id, question_id)` | Batch upsert |
| 11 | One certificate per final-test attempt | `UNIQUE (final_test_attempt_id)` on `certificates` | Re-running issuance is idempotent |
| 12 | Unique slugs | `UNIQUE (slug)` on `exam_categories`, `courses` | 422 with a suggested slug |
| 13 | One onboarding record per user | `UNIQUE (user_id)` on `onboarding_preferences` | Upsert per step |
| 14 | One rollup row per date per course | `UNIQUE (metric_date, course_id)` | Rollup job upserts → safe to re-run for a backfill |
| 15 | Unique refresh-token hash | `UNIQUE (token_hash)` | Reuse of a rotated token revokes the whole `family_id` (theft detection) |

**Race conditions covered by these constraints** (each gets a test in `tests/Feature/Quiz/ConcurrencyTest.php`):
double-tap on "Submit" (#5), two devices starting the same day (#9), replayed offline outbox after reinstall (#5, #6),
simultaneous last-answer submissions racing to complete the day (#4 + row lock on `quiz_attempts`), duplicate PDF
upload from two admins (`uq_pdf_checksum_course`).

### 9.1 What is *not* enforceable in the schema

Documented honestly, with the compensating control:

| Invariant | Why not a DB constraint | Compensating control |
|---|---|---|
| Exactly one correct option per question | Needs a cross-row aggregate CHECK (unsupported) | Write service + validation rule + nightly `quiz:verify-question-integrity` + feature test |
| `questions.correct_option` matches `question_options.is_correct` | Same | Same as above; drift raises a `severity=warning` activity log and a Sentry event |
| A day's quiz has exactly `daily_question_count` questions | Cross-row count | `GenerateDailyQuizzesAction` validates before commit; admin UI blocks publishing a short day; `courses.status` cannot move to `active` while any day is short |
| Final test question count equals sum of days 1–30 | Cross-table aggregate | `FinalTestService::materialise()` asserts and logs; admin sees the actual count before publishing |
