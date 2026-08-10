import type { QuizQuestion, AnswerResult } from '@/types/models';
import type { QuestionOption } from '@/types/enums';

/**
 * Pure quiz state machine reducer — no React, no re-render churn.
 * Manages the correct/incorrect/retry state for the daily quiz runner.
 *
 * Key design decisions:
 * - Progress = mastered_count / required (not answered/total)
 * - Retry mode: requeue_at_end (wrong questions come back after first pass)
 * - Options always submit the canonical `key`, never the display position
 */

// ─── State ──────────────────────────────────────────────────────────────────

export interface QuizState {
  questions: QuizQuestion[];
  /** The current queue of question IDs in the order to be shown */
  questionQueue: string[];
  /** Index into questionQueue */
  currentIndex: number;
  /** Questions mastered (answered correctly) */
  masteredIds: Set<string>;
  /** Questions that need retry (answered incorrectly, waiting to be re-asked) */
  retryIds: Set<string>;
  /** Total required to complete the day */
  required: number;
  /** Current phase of the answer flow */
  answerPhase: 'selecting' | 'submitting' | 'feedback';
  /** Selected option (before submit) */
  selectedOption: QuestionOption | null;
  /** Last answer result from server */
  lastResult: AnswerResult | null;
  /** Whether the quiz can be completed (all mastered) */
  canComplete: boolean;
  /** Whether day is complete */
  isComplete: boolean;
}

// ─── Actions ────────────────────────────────────────────────────────────────

export type QuizAction =
  | { type: 'INIT'; questions: QuizQuestion[]; masteredIds: string[]; retryIds: string[]; required: number }
  | { type: 'SELECT_OPTION'; option: QuestionOption }
  | { type: 'SUBMIT_START' }
  | { type: 'SUBMIT_SUCCESS'; result: AnswerResult }
  | { type: 'SUBMIT_OFFLINE' }
  | { type: 'CONTINUE' }
  | { type: 'COMPLETE' }
  | { type: 'SYNC_STATE'; masteredIds: string[]; retryIds: string[] };

// ─── Initial state ──────────────────────────────────────────────────────────

export function createInitialState(): QuizState {
  return {
    questions: [],
    questionQueue: [],
    currentIndex: 0,
    masteredIds: new Set(),
    retryIds: new Set(),
    required: 10,
    answerPhase: 'selecting',
    selectedOption: null,
    lastResult: null,
    canComplete: false,
    isComplete: false,
  };
}

// ─── Reducer ────────────────────────────────────────────────────────────────

export function quizReducer(state: QuizState, action: QuizAction): QuizState {
  switch (action.type) {
    case 'INIT': {
      // Build the initial queue: all questions minus already mastered
      const allIds = action.questions.map((q) => q.id);
      const mastered = new Set(action.masteredIds);
      const retry = new Set(action.retryIds);
      // Queue = questions not yet mastered, then retries at the end
      const firstPass = allIds.filter((id) => !mastered.has(id) && !retry.has(id));
      const retryQueue = allIds.filter((id) => retry.has(id));
      const queue = [...firstPass, ...retryQueue];

      return {
        ...state,
        questions: action.questions,
        questionQueue: queue.length > 0 ? queue : allIds,
        currentIndex: 0,
        masteredIds: mastered,
        retryIds: retry,
        required: action.required,
        answerPhase: 'selecting',
        selectedOption: null,
        lastResult: null,
        canComplete: mastered.size >= action.required,
        isComplete: false,
      };
    }

    case 'SELECT_OPTION':
      if (state.answerPhase !== 'selecting') return state;
      return { ...state, selectedOption: action.option };

    case 'SUBMIT_START':
      return { ...state, answerPhase: 'submitting' };

    case 'SUBMIT_SUCCESS': {
      const { result } = action;
      const currentQuestionId = state.questionQueue[state.currentIndex];
      const newMastered = new Set(state.masteredIds);
      const newRetry = new Set(state.retryIds);

      if (result.is_correct && currentQuestionId) {
        newMastered.add(currentQuestionId);
        newRetry.delete(currentQuestionId);
      } else if (currentQuestionId) {
        newRetry.add(currentQuestionId);
      }

      return {
        ...state,
        answerPhase: 'feedback',
        lastResult: result,
        masteredIds: newMastered,
        retryIds: newRetry,
        canComplete: result.can_complete,
      };
    }

    case 'SUBMIT_OFFLINE': {
      // Offline: mark as pending, don't show feedback (we don't know correctness)
      return {
        ...state,
        answerPhase: 'feedback',
        lastResult: null, // null means "pending — saved offline"
      };
    }

    case 'CONTINUE': {
      // Move to next question
      const nextIndex = state.currentIndex + 1;

      // If we've gone through all in the queue
      if (nextIndex >= state.questionQueue.length) {
        // Check if there are retries to requeue
        const remainingRetries = [...state.retryIds].filter(
          (id) => !state.masteredIds.has(id),
        );

        if (remainingRetries.length > 0 && state.masteredIds.size < state.required) {
          // Requeue retries at the end (requeue_at_end mode)
          return {
            ...state,
            questionQueue: [...state.questionQueue, ...remainingRetries],
            currentIndex: nextIndex,
            answerPhase: 'selecting',
            selectedOption: null,
            lastResult: null,
          };
        }

        // All done or all mastered
        return {
          ...state,
          currentIndex: nextIndex,
          answerPhase: 'selecting',
          selectedOption: null,
          lastResult: null,
          canComplete: state.masteredIds.size >= state.required,
        };
      }

      return {
        ...state,
        currentIndex: nextIndex,
        answerPhase: 'selecting',
        selectedOption: null,
        lastResult: null,
      };
    }

    case 'COMPLETE':
      return { ...state, isComplete: true };

    case 'SYNC_STATE': {
      // Server state replaces local derived state
      const mastered = new Set(action.masteredIds);
      const retry = new Set(action.retryIds);
      return {
        ...state,
        masteredIds: mastered,
        retryIds: retry,
        canComplete: mastered.size >= state.required,
      };
    }

    default:
      return state;
  }
}

// ─── Selectors ──────────────────────────────────────────────────────────────

export function getCurrentQuestion(state: QuizState): QuizQuestion | null {
  const id = state.questionQueue[state.currentIndex];
  if (!id) return null;
  return state.questions.find((q) => q.id === id) ?? null;
}

export function getQuestionNumber(state: QuizState): number {
  return state.currentIndex + 1;
}

export function getTotalInQueue(state: QuizState): number {
  return state.questionQueue.length;
}
