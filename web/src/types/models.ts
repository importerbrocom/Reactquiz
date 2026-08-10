import type {
  UserRole,
  UserStatus,
  ContentStatus,
  AttemptStatus,
  FinalTestAttemptStatus,
  DayStatus,
  NextAction,
  QuestionOption,
  Difficulty,
  NotificationType,
  UnlockMode,
} from './enums';

// ─── Auth ────────────────────────────────────────────────────────────────────

export interface User {
  id: string;
  name: string;
  email: string;
  role: UserRole;
  status: UserStatus;
  timezone: string;
  locale: string;
  avatar_url: string | null;
  email_verified_at: string | null;
  created_at: string;
}

export interface AuthTokens {
  access_token: string;
  expires_in: number;
}

export interface LoginResponse {
  user: User;
  access_token: string;
  expires_in: number;
}

export interface RegisterResponse {
  user: User;
  access_token: string;
  expires_in: number;
}

// ─── Exam Categories & Courses ───────────────────────────────────────────────

export interface ExamCategory {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  icon_url: string | null;
  status: ContentStatus;
  courses_count: number;
}

export interface Course {
  id: string;
  exam_category_id: string;
  name: string;
  slug: string;
  description: string | null;
  image_url: string | null;
  status: ContentStatus;
  total_questions: number;
  levels_count: number;
  days_per_level: number;
  questions_per_day: number;
  unlock_mode: UnlockMode;
  duration_label: string;
}

export interface CourseSummary {
  id: string;
  name: string;
  slug: string;
  image_url: string | null;
  current_level: number;
  current_day: number;
  total_levels: number;
  days_per_level: number;
  overall_progress_percent: number;
}

// ─── Onboarding ──────────────────────────────────────────────────────────────

export interface OnboardingState {
  current_step: string;
  exam_category_id: string | null;
  course_id: string | null;
  preferences: OnboardingPreferences | null;
}

export interface OnboardingPreferences {
  reminder_time: string | null;
  notifications_opt_in: boolean;
  language: string;
  timezone: string;
  study_goal: string | null;
}

// ─── Dashboard ───────────────────────────────────────────────────────────────

export interface Dashboard {
  user: Pick<User, 'id' | 'name' | 'avatar_url'>;
  enrolment: DashboardEnrolment;
  today: DashboardToday;
  streak: StreakInfo;
  next_action: NextAction;
  final_test: FinalTestEligibilitySummary;
  recent_scores: DayScore[];
}

export interface DashboardEnrolment {
  course_id: string;
  course_name: string;
  current_level: number;
  current_day: number;
  total_levels: number;
  days_per_level: number;
  cycle: number;
}

export interface DashboardToday {
  day_number: number;
  status: DayStatus;
  attempt_uuid: string | null;
  mastered_count: number;
  required_count: number;
}

export interface FinalTestEligibilitySummary {
  eligible: boolean;
  completed_days: number;
  required_days: number;
}

export interface DayScore {
  day_number: number;
  score: number;
  completed_at: string;
}

export interface StreakInfo {
  current: number;
  longest: number;
  last_activity_date: string | null;
}

// ─── Days Timeline ───────────────────────────────────────────────────────────

export interface DayTimelineEntry {
  day_number: number;
  status: DayStatus;
  score: number | null;
  completed_at: string | null;
}

// ─── Quiz ────────────────────────────────────────────────────────────────────

export interface QuizQuestion {
  id: string;
  text: string;
  image_url: string | null;
  options: QuizQuestionOption[];
}

export interface QuizQuestionOption {
  key: QuestionOption;
  text: string;
  display_position: number;
}

export interface QuizDayPayload {
  day_number: number;
  questions: QuizQuestion[];
  attempt: QuizAttemptState;
}

export interface QuizAttemptState {
  uuid: string;
  status: AttemptStatus;
  current_position: number;
  mastered_question_ids: string[];
  retry_required_question_ids: string[];
}

export interface AnswerResult {
  is_correct: boolean;
  correct_option: QuestionOption | null;
  correct_answer_text: string | null;
  explanation: string | null;
  retry_required: boolean;
  progress: QuizProgress;
  can_complete: boolean;
}

export interface QuizProgress {
  mastered_count: number;
  required_count: number;
  retry_required_question_ids: string[];
}

export interface QuizCompleteResult {
  score: number;
  required: number;
  day_number: number;
  next_day: {
    number: number;
    unlocked: boolean;
    unlocks_at: string | null;
  };
  streak: StreakInfo;
  final_test: {
    unlocked: boolean;
    url: string | null;
  };
  time_spent_seconds: number;
}

// ─── Final Test ──────────────────────────────────────────────────────────────

export interface FinalTestEligibility {
  eligible: boolean;
  completed_days: number;
  required_days: number;
  missing_days: number[];
  attempts_used: number;
  attempts_allowed: number;
}

export interface FinalTestAttempt {
  attempt_uuid: string;
  total: number;
  current_position: number;
  expires_at: string;
  status: FinalTestAttemptStatus;
}

export interface FinalTestQuestion {
  id: string;
  position: number;
  text: string;
  image_url: string | null;
  options: QuizQuestionOption[];
}

export interface FinalTestNavigatorItem {
  position: number;
  question_id: string;
  answered: boolean;
  flagged: boolean;
}

export interface FinalTestResult {
  score: number;
  total: number;
  percentage: number;
  passed: boolean;
  time_spent_seconds: number;
  topic_breakdown: TopicBreakdown[];
  wrong_answers: FinalTestWrongAnswer[];
}

export interface TopicBreakdown {
  topic: string;
  correct: number;
  total: number;
  percentage: number;
}

export interface FinalTestWrongAnswer {
  question_id: string;
  question_text: string;
  selected_option: QuestionOption;
  correct_option: QuestionOption;
  correct_answer_text: string;
  explanation: string | null;
}

// ─── Progress ────────────────────────────────────────────────────────────────

export interface ProgressOverview {
  total_questions_seen: number;
  total_mastered: number;
  accuracy_percent: number;
  days_completed: number;
  current_streak: number;
  time_spent_hours: number;
}

export interface ProgressCalendarDay {
  day_number: number;
  status: DayStatus;
  score: number | null;
  attempts_count: number;
}

export interface TopicProgress {
  topic: string;
  correct: number;
  total: number;
  accuracy_percent: number;
  strength: 'strong' | 'moderate' | 'weak';
}

// ─── Mistakes ────────────────────────────────────────────────────────────────

export interface Mistake {
  id: string;
  question_id: string;
  question_text: string;
  selected_option: QuestionOption;
  correct_option: QuestionOption;
  correct_answer_text: string;
  explanation: string | null;
  topic: string | null;
  difficulty: Difficulty | null;
  wrong_count: number;
  last_wrong_at: string;
  resolved: boolean;
}

// ─── Notifications ───────────────────────────────────────────────────────────

export interface Notification {
  id: string;
  type: NotificationType;
  title: string;
  body: string;
  data: Record<string, unknown> | null;
  read_at: string | null;
  created_at: string;
}

// ─── Level Advancement ───────────────────────────────────────────────────────

export interface LevelAdvanceInfo {
  can_advance: boolean;
  next_level: number | null;
  next_cycle: number | null;
  reason: string | null;
}

// ─── Offline / IndexedDB ─────────────────────────────────────────────────────

export interface PendingAnswer {
  client_answer_uuid: string;
  idempotency_key: string;
  attempt_uuid: string;
  question_id: string;
  selected_option: QuestionOption;
  time_spent_ms: number;
  answered_at: string;
  was_offline: boolean;
  attempts: number;
  next_retry_at: number;
  status: 'pending' | 'inflight' | 'failed';
}

export interface SyncedAnswer {
  client_answer_uuid: string;
  synced_at: number;
  is_correct: boolean;
}

export interface AttemptStateCache {
  attempt_uuid: string;
  course_id: string;
  day_number: number;
  current_position: number;
  mastered_question_ids: string[];
  retry_required_question_ids: string[];
  updated_at: number;
}

export interface FinalTestStateCache {
  attempt_uuid: string;
  question_order_hash: string;
  current_position: number;
  answers: Record<string, {
    option: QuestionOption;
    flagged: boolean;
    time_spent_ms: number;
    dirty: boolean;
  }>;
  last_sync_at: number;
}

export interface QuizCacheEntry {
  course_id: string;
  day_number: number;
  payload: QuizDayPayload;
  fetched_at: number;
}

export interface SyncMeta {
  last_sync_at: number;
  pending_count: number;
  schema_version: number;
}
