import { useMutation } from '@tanstack/react-query';
import { submitAnswer, type SubmitAnswerPayload } from '@/api/endpoints/quiz.api';
import { generateUUID } from '@/utils/uuid';
import type { QuestionOption } from '@/types/enums';
import type { AnswerResult } from '@/types/models';

interface SubmitAnswerArgs {
  attemptUuid: string;
  questionId: string;
  selectedOption: QuestionOption;
  timeSpentMs: number;
}

/**
 * Mutation to submit a single answer.
 * Generates the client_answer_uuid (used as idempotency key).
 */
export function useSubmitAnswer(
  onSuccess: (result: AnswerResult) => void,
  onOffline: () => void,
) {
  return useMutation({
    mutationFn: (args: SubmitAnswerArgs) => {
      const payload: SubmitAnswerPayload = {
        question_id: args.questionId,
        selected_option: args.selectedOption,
        client_answer_uuid: generateUUID(),
        time_spent_ms: args.timeSpentMs,
        answered_at: new Date().toISOString(),
        was_offline: !navigator.onLine,
      };
      return submitAnswer(args.attemptUuid, payload);
    },
    onSuccess: (data) => {
      onSuccess(data);
    },
    onError: (error) => {
      // If it's a network error (status 0), treat as offline
      if (typeof error === 'object' && error !== null && 'status' in error && (error as { status: number }).status === 0) {
        onOffline();
      }
    },
  });
}
