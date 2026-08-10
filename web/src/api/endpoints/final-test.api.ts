import { apiClient } from '../client';
import type { ApiEnvelope } from '@/types/api';
import type {
  FinalTestEligibility,
  FinalTestAttempt,
  FinalTestQuestion,
  FinalTestNavigatorItem,
  FinalTestResult,
} from '@/types/models';
import type { QuestionOption } from '@/types/enums';

export interface FinalTestBatchAnswerPayload {
  batch_uuid: string;
  answers: Array<{
    question_id: string;
    selected_option: QuestionOption;
    client_answer_uuid: string;
    time_spent_ms: number;
    answered_at: string;
    was_offline: boolean;
    flagged: boolean;
  }>;
}

/**
 * Check eligibility for the level/final test.
 */
export async function getEligibility(courseId: string): Promise<FinalTestEligibility> {
  const { data } = await apiClient.get<ApiEnvelope<FinalTestEligibility>>(
    `/student/courses/${courseId}/final-test/eligibility`,
  );
  return data.data;
}

/**
 * Start or resume a final test attempt (idempotent).
 */
export async function startOrResumeAttempt(courseId: string): Promise<FinalTestAttempt> {
  const { data } = await apiClient.post<ApiEnvelope<FinalTestAttempt>>(
    `/student/courses/${courseId}/final-test/attempts`,
    {},
    { idempotent: true },
  );
  return data.data;
}

/**
 * Fetch a window of questions (answer-free).
 */
export async function getQuestions(
  attemptUuid: string,
  position: number,
  limit: number = 20,
): Promise<FinalTestQuestion[]> {
  const { data } = await apiClient.get<ApiEnvelope<FinalTestQuestion[]>>(
    `/student/final-test-attempts/${attemptUuid}/questions`,
    { params: { position, limit } },
  );
  return data.data;
}

/**
 * Get the navigator/palette state (answered/flagged grid).
 */
export async function getNavigator(attemptUuid: string): Promise<FinalTestNavigatorItem[]> {
  const { data } = await apiClient.get<ApiEnvelope<FinalTestNavigatorItem[]>>(
    `/student/final-test-attempts/${attemptUuid}/state`,
  );
  return data.data;
}

/**
 * Batch sync answers (up to 20, upsert, no correctness returned).
 */
export async function syncAnswerBatch(
  attemptUuid: string,
  payload: FinalTestBatchAnswerPayload,
): Promise<void> {
  await apiClient.post(
    `/student/final-test-attempts/${attemptUuid}/answers/batch`,
    payload,
    { idempotent: true, idempotencyKey: payload.batch_uuid },
  );
}

/**
 * Pause the attempt (preserves time).
 */
export async function pauseAttempt(attemptUuid: string): Promise<void> {
  await apiClient.patch(`/student/final-test-attempts/${attemptUuid}/pause`);
}

/**
 * Resume a paused attempt.
 */
export async function resumeAttempt(attemptUuid: string): Promise<FinalTestAttempt> {
  const { data } = await apiClient.patch<ApiEnvelope<FinalTestAttempt>>(
    `/student/final-test-attempts/${attemptUuid}/resume`,
  );
  return data.data;
}

/**
 * Submit the final test for grading.
 * Returns 202 — grading may be async on the critical queue.
 */
export async function submitTest(attemptUuid: string): Promise<{ result_url: string }> {
  const { data } = await apiClient.post<ApiEnvelope<{ result_url: string }>>(
    `/student/final-test-attempts/${attemptUuid}/submit`,
    {},
    { idempotent: true },
  );
  return data.data;
}

/**
 * Get the graded result.
 */
export async function getResult(attemptUuid: string): Promise<FinalTestResult> {
  const { data } = await apiClient.get<ApiEnvelope<FinalTestResult>>(
    `/student/final-test-attempts/${attemptUuid}/result`,
  );
  return data.data;
}
