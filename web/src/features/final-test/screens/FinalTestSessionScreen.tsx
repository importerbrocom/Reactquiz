import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import {
  getQuestions,
  getNavigator,
  syncAnswerBatch,
  submitTest,
} from '@/api/endpoints/final-test.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { FINAL_TEST_WINDOW_SIZE, FINAL_TEST_SYNC_DEBOUNCE_MS, FINAL_TEST_FLUSH_THRESHOLD } from '@/config/constants';
import { QuestionCard } from '@/components/quiz/QuestionCard';
import { QuizOptionButton } from '@/components/quiz/QuizOptionButton';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { PageSpinner } from '@/components/ui/Spinner';
import { useNetworkStore } from '@/store/network.store';
import { generateUUID } from '@/utils/uuid';
import type { QuestionOption } from '@/types/enums';

interface LocalAnswer {
  question_id: string;
  selected_option: QuestionOption;
  flagged: boolean;
  time_spent_ms: number;
  dirty: boolean;
}

function FinalTestSessionScreen() {
  const { attemptId } = useParams<{ attemptId: string }>();
  const navigate = useNavigate();
  const { isOnline } = useNetworkStore();

  const [position, setPosition] = useState(1);
  const [answers, setAnswers] = useState<Map<string, LocalAnswer>>(new Map());
  const [showPalette, setShowPalette] = useState(false);
  const [showSubmitConfirm, setShowSubmitConfirm] = useState(false);
  const questionTimeRef = useRef(Date.now());
  const syncTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  // Fetch current window of questions
  const { data: questions, isLoading } = useQuery({
    queryKey: queryKeys.finalTest.questions(attemptId!, position),
    queryFn: () => getQuestions(attemptId!, position, FINAL_TEST_WINDOW_SIZE),
    enabled: !!attemptId,
  });

  // Fetch navigator/palette state
  const { data: paletteItems } = useQuery({
    queryKey: queryKeys.finalTest.navigator(attemptId!),
    queryFn: () => getNavigator(attemptId!),
    enabled: !!attemptId,
    refetchInterval: 30_000, // Refresh palette every 30s
  });

  // Batch sync mutation
  const syncMutation = useMutation<void, Error, void>({
    mutationFn: async () => {
      const dirtyAnswers = [...answers.entries()]
        .filter(([, a]) => a.dirty)
        .map(([, a]) => ({
          question_id: a.question_id,
          selected_option: a.selected_option,
          client_answer_uuid: generateUUID(),
          time_spent_ms: a.time_spent_ms,
          answered_at: new Date().toISOString(),
          was_offline: !window.navigator.onLine,
          flagged: a.flagged,
        }));

      if (dirtyAnswers.length === 0) return;

      await syncAnswerBatch(attemptId!, {
        batch_uuid: generateUUID(),
        answers: dirtyAnswers,
      });
      // Mark all as synced
      setAnswers((prev) => {
        const next = new Map(prev);
        for (const [key, val] of next) {
          if (val.dirty) next.set(key, { ...val, dirty: false });
        }
        return next;
      });
    },
  });

  // Submit test mutation
  const submitMutation = useMutation({
    mutationFn: () => submitTest(attemptId!),
    onSuccess: () => {
      navigate(ROUTES.FINAL_TEST_RESULT(attemptId!));
    },
  });

  // Auto-sync dirty answers (debounced)
  const scheduleSyncIfNeeded = useCallback(() => {
    const dirtyCount = [...answers.values()].filter((a) => a.dirty).length;
    if (dirtyCount >= FINAL_TEST_FLUSH_THRESHOLD) {
      syncMutation.mutate(undefined);
    } else if (dirtyCount > 0) {
      if (syncTimerRef.current) clearTimeout(syncTimerRef.current);
      syncTimerRef.current = setTimeout(() => {
        syncMutation.mutate(undefined);
      }, FINAL_TEST_SYNC_DEBOUNCE_MS);
    }
  }, [answers, syncMutation]);

  useEffect(() => {
    scheduleSyncIfNeeded();
  }, [answers.size]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync on blur / visibility change
  useEffect(() => {
    const handleVisibilityChange = () => {
      if (document.hidden) syncMutation.mutate(undefined);
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  const handleSelectOption = (questionId: string, option: QuestionOption) => {
    const timeSpent = Date.now() - questionTimeRef.current;
    setAnswers((prev) => {
      const next = new Map(prev);
      const existing = next.get(questionId);
      next.set(questionId, {
        question_id: questionId,
        selected_option: option,
        flagged: existing?.flagged ?? false,
        time_spent_ms: (existing?.time_spent_ms ?? 0) + timeSpent,
        dirty: true,
      });
      return next;
    });
  };

  const handleToggleFlag = (questionId: string) => {
    setAnswers((prev) => {
      const next = new Map(prev);
      const existing = next.get(questionId);
      if (existing) {
        next.set(questionId, { ...existing, flagged: !existing.flagged, dirty: true });
      }
      return next;
    });
  };

  const handleNavigate = (newPosition: number) => {
    questionTimeRef.current = Date.now();
    setPosition(newPosition);
    setShowPalette(false);
  };

  const handleSubmit = async () => {
    // Flush all dirty answers first
    await syncMutation.mutateAsync(undefined);
    submitMutation.mutate();
  };

  const currentQuestion = questions?.[0]; // We show one at a time from the window
  const currentQuestionInWindow = questions?.find((q) => q.position === position);
  const questionToShow = currentQuestionInWindow ?? currentQuestion;

  if (isLoading || !questionToShow) return <PageSpinner />;

  const totalQuestions = paletteItems?.length ?? 300;
  const answeredCount = [...answers.values()].filter((a) => a.selected_option).length;
  const flaggedCount = [...answers.values()].filter((a) => a.flagged).length;

  return (
    <div className="flex flex-1 flex-col">
      {/* Header with timer and palette toggle */}
      <header className="flex items-center justify-between border-b border-surface-800 bg-surface-950 px-4 py-3">
        <span className="text-sm text-surface-400">
          Q {position}/{totalQuestions}
        </span>
        <div className="flex items-center gap-3">
          <span className="text-xs text-surface-500">
            {answeredCount} answered
            {flaggedCount > 0 && ` · ${flaggedCount} flagged`}
          </span>
          <button
            onClick={() => setShowPalette(true)}
            className="min-h-[44px] min-w-[44px] rounded-lg bg-surface-800 px-3 py-2 text-sm text-surface-200"
          >
            Grid
          </button>
        </div>
      </header>

      {/* Question */}
      <div className="flex-1 overflow-y-auto px-4 py-6">
        <QuestionCard
          question={{
            id: questionToShow.id,
            text: questionToShow.text,
            image_url: questionToShow.image_url,
            options: questionToShow.options,
          }}
          questionNumber={position}
          totalQuestions={totalQuestions}
        />

        {/* Options */}
        <div className="mt-6 space-y-3">
          {questionToShow.options
            .sort((a, b) => a.display_position - b.display_position)
            .map((option) => {
              const localAnswer = answers.get(questionToShow.id);
              const isSelected = localAnswer?.selected_option === option.key;
              return (
                <QuizOptionButton
                  key={option.key}
                  optionKey={option.key}
                  text={option.text}
                  state={isSelected ? 'selected' : 'idle'}
                  disabled={false}
                  onSelect={(key) => handleSelectOption(questionToShow.id, key)}
                />
              );
            })}
        </div>

        {/* Flag toggle */}
        <button
          onClick={() => handleToggleFlag(questionToShow.id)}
          className={`mt-4 flex min-h-[44px] items-center gap-2 rounded-lg px-4 py-2 text-sm transition-colors ${
            answers.get(questionToShow.id)?.flagged
              ? 'bg-warning-500/15 text-warning-500'
              : 'bg-surface-800 text-surface-400 hover:text-surface-200'
          }`}
        >
          <span>🚩</span>
          {answers.get(questionToShow.id)?.flagged ? 'Flagged for review' : 'Flag for review'}
        </button>
      </div>

      {/* Navigation footer */}
      <footer className="flex items-center justify-between border-t border-surface-800 bg-surface-950 px-4 py-3">
        <Button
          variant="ghost"
          size="sm"
          disabled={position <= 1}
          onClick={() => handleNavigate(position - 1)}
        >
          ← Prev
        </Button>
        <Button
          variant="ghost"
          size="sm"
          onClick={() => setShowSubmitConfirm(true)}
        >
          Submit
        </Button>
        <Button
          variant="ghost"
          size="sm"
          disabled={position >= totalQuestions}
          onClick={() => handleNavigate(position + 1)}
        >
          Next →
        </Button>
      </footer>

      {/* Question palette modal */}
      <Modal
        open={showPalette}
        onClose={() => setShowPalette(false)}
        title="Question Navigator"
      >
        <div className="grid grid-cols-10 gap-1.5">
          {paletteItems?.map((item) => {
            const localAnswer = answers.get(item.question_id);
            const isAnswered = localAnswer?.selected_option || item.answered;
            const isFlagged = localAnswer?.flagged || item.flagged;
            const isCurrent = item.position === position;

            return (
              <button
                key={item.position}
                onClick={() => handleNavigate(item.position)}
                className={`flex h-8 w-8 items-center justify-center rounded text-xs font-medium transition-colors ${
                  isCurrent
                    ? 'bg-primary-500 text-white'
                    : isAnswered
                      ? 'bg-success-500/20 text-success-500'
                      : isFlagged
                        ? 'bg-warning-500/20 text-warning-500'
                        : 'bg-surface-800 text-surface-400'
                }`}
              >
                {item.position}
              </button>
            );
          })}
        </div>
        <div className="mt-4 flex gap-4 text-xs text-surface-400">
          <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-success-500/20" /> Answered</span>
          <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-warning-500/20" /> Flagged</span>
          <span className="flex items-center gap-1"><span className="h-3 w-3 rounded bg-primary-500" /> Current</span>
        </div>
      </Modal>

      {/* Submit confirmation modal */}
      <Modal
        open={showSubmitConfirm}
        onClose={() => setShowSubmitConfirm(false)}
        title="Submit Test?"
      >
        <div className="space-y-4">
          <p className="text-sm text-surface-300">
            You have answered {answeredCount} of {totalQuestions} questions.
            {flaggedCount > 0 && ` ${flaggedCount} are flagged for review.`}
          </p>
          {answeredCount < totalQuestions && (
            <p className="text-xs text-warning-500">
              ⚠️ {totalQuestions - answeredCount} questions are unanswered and will be marked incorrect.
            </p>
          )}
          {!isOnline && (
            <p className="text-xs text-danger-500">
              ⚠️ You are offline. Please connect to the internet before submitting.
            </p>
          )}
          <div className="flex gap-3">
            <Button variant="ghost" onClick={() => setShowSubmitConfirm(false)}>
              Review
            </Button>
            <Button
              fullWidth
              loading={submitMutation.isPending || syncMutation.isPending}
              disabled={!isOnline}
              onClick={handleSubmit}
            >
              Confirm Submit
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
}

export const Component = FinalTestSessionScreen;
export default FinalTestSessionScreen;
