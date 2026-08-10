import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type {
  QuizDayPayload,
  QuizAttemptState,
  AnswerResult,
  QuizCompleteResult,
} from '@/types/models';
import type { QuestionOption } from '@/types/enums';

export interface SubmitAnswerPayload {
  question_id: string;
  selected_option: QuestionOption;
  client_answer_uuid: string;
  time_spent_ms: number;
  answered_at: string;
  was_offline: boolean;
}

export interface BatchAnswerPayload {
  batch_uuid: string;
  answers: SubmitAnswerPayload[];
}

export interface BatchAnswerResult {
  results: Array<{
    client_answer_uuid: string;
    is_correct: boolean;
    correct_option: QuestionOption | null;
    correct_answer_text: string | null;
    explanation: string | null;
    retry_required: boolean;
  }>;
  progress: {
    mastered_count: number;
    required_count: number;
    retry_required_question_ids: string[];
  };
}

/**
 * Fetch a quiz day. Returns 423 if locked (meta.unlock_hint explains how).
 */
export async function getQuizDay(courseId: string, day: number): Promise<QuizDayPayload> {
  const { data } = await apiClient.get<ApiEnvelope<QuizDayPayload>>(
    `/student/courses/${courseId}/quiz-days/${day}`,
  );
  return data.data;
}

/**
 * Start or resume a quiz attempt (idempotent).
 */
export async function startOrResumeAttempt(
  courseId: string,
  day: number,
): Promise<QuizAttemptState> {
  const { data } = await apiClient.post<ApiEnvelope<QuizAttemptState>>(
    `/student/courses/${courseId}/quiz-days/${day}/attempts`,
    {},
    { idempotent: true },
  );
  return data.data;
}

/**
 * Get attempt state for resume after reload.
 */
export async function getAttempt(attemptUuid: string): Promise<QuizAttemptState> {
  const { data } = await apiClient.get<ApiEnvelope<QuizAttemptState>>(
    `/student/quiz-attempts/${attemptUuid}`,
  );
  return data.data;
}

/**
 * Submit a single answer and receive the verdict.
 * Response is Cache-Control: no-store (never cached in SW).
 */
export async function submitAnswer(
  attemptUuid: string,
  payload: SubmitAnswerPayload,
): Promise<AnswerResult> {
  const { data } = await apiClient.post<ApiEnvelope<AnswerResult>>(
    `/student/quiz-attempts/${attemptUuid}/answers`,
    payload,
    { idempotent: true, idempotencyKey: payload.client_answer_uuid },
  );
  return data.data;
}

/**
 * Batch submit answers (offline drain). Up to 20 per request.
 */
export async function submitAnswerBatch(
  attemptUuid: string,
  payload: BatchAnswerPayload,
): Promise<BatchAnswerResult> {
  const { data } = await apiClient.post<ApiEnvelope<BatchAnswerResult>>(
    `/student/quiz-attempts/${attemptUuid}/answers/batch`,
    payload,
    { idempotent: true, idempotencyKey: payload.batch_uuid },
  );
  return data.data;
}

/**
 * Save current position (debounced resume pointer).
 */
export async function savePosition(
  attemptUuid: string,
  position: number,
): Promise<void> {
  await apiClient.patch(`/student/quiz-attempts/${attemptUuid}/position`, {
    current_position: position,
  });
}

/**
 * Claim the day as finished. Re-derives 10/10 server-side.
 * Returns 422 QUIZ_NOT_COMPLETE with meta.outstanding_question_ids if not ready.
 */
export async function completeQuiz(attemptUuid: string): Promise<QuizCompleteResult> {
  const { data } = await apiClient.post<ApiEnvelope<QuizCompleteResult>>(
    `/student/quiz-attempts/${attemptUuid}/complete`,
    {},
    { idempotent: true },
  );
  return data.data;
}

/**
 * Get the post-completion result summary with all answers and explanations.
 */
export async function getQuizResult(attemptUuid: string): Promise<QuizCompleteResult> {
  const { data } = await apiClient.get<ApiEnvelope<QuizCompleteResult>>(
    `/student/quiz-attempts/${attemptUuid}/result`,
  );
  return data.data;
}
