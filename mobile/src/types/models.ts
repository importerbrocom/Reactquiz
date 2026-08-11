/**
 * Shared model types — mirrors the web app's types/models.ts.
 * Same API contract, same TypeScript interfaces.
 */

export interface User {
  id: string;
  name: string;
  email: string;
  role: string;
  timezone: string;
  avatar_url: string | null;
}

export interface Dashboard {
  user: Pick<User, 'id' | 'name' | 'avatar_url'>;
  enrolment: {
    course_id: string;
    course_name: string;
    current_level: number;
    current_day: number;
    total_levels: number;
    days_per_level: number;
    cycle: number;
  };
  today: {
    day_number: number;
    status: string;
    attempt_uuid: string | null;
    mastered_count: number;
    required_count: number;
  };
  streak: { current: number; longest: number };
  next_action: string;
  final_test: { eligible: boolean; completed_days: number; required_days: number };
  recent_scores: Array<{ day_number: number; score: number }>;
}

export interface QuizQuestion {
  id: string;
  text: string;
  image_url: string | null;
  options: Array<{
    key: string;
    text: string;
    display_position: number;
  }>;
}

export interface AnswerResult {
  is_correct: boolean;
  correct_option: string | null;
  correct_answer_text: string | null;
  explanation: string | null;
  retry_required: boolean;
  progress: { mastered_count: number; required_count: number; retry_required_question_ids: string[] };
  can_complete: boolean;
}

export interface QuizCompleteResult {
  score: number;
  required: number;
  day_number: number;
  next_day: { number: number; unlocked: boolean; unlocks_at: string | null };
  streak: { current: number; longest: number };
  time_spent_seconds: number;
}

export interface DayTimelineEntry {
  day_number: number;
  status: 'locked' | 'available' | 'in_progress' | 'completed';
  score: number | null;
}

export interface ApiEnvelope<T = unknown> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, string[]> | null;
  meta: Record<string, unknown> | null;
}
