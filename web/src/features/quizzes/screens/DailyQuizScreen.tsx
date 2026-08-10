import { useReducer, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router';
import { useQuizDay, useStartAttempt, useCompleteQuiz } from '../hooks/useQuizDay';
import { useSubmitAnswer } from '../hooks/useSubmitAnswer';
import {
  quizReducer,
  createInitialState,
  getCurrentQuestion,
} from '../machine/quizReducer';
import { QuestionCard } from '@/components/quiz/QuestionCard';
import { QuizOptionButton, type OptionState } from '@/components/quiz/QuizOptionButton';
import { AnswerFeedback } from '@/components/quiz/AnswerFeedback';
import { QuizProgressHeader } from '@/components/quiz/QuizProgressHeader';
import { OfflineSaveIndicator } from '@/components/feedback/OfflineSaveIndicator';
import { Button } from '@/components/ui/Button';
import { PageSpinner } from '@/components/ui/Spinner';
import { ROUTES } from '@/config/routes.config';
import { CORRECT_ANSWER_ADVANCE_MS } from '@/config/constants';
import type { QuestionOption } from '@/types/enums';

function DailyQuizScreen() {
  const { courseId, day } = useParams<{ courseId: string; day: string }>();
  const navigate = useNavigate();
  const dayNumber = parseInt(day!, 10);
  const timeRef = useRef(Date.now());

  const [state, dispatch] = useReducer(quizReducer, undefined, createInitialState);

  // Fetch quiz day data
  const { data: quizDay, isLoading, isError } = useQuizDay(courseId!, dayNumber);

  // Start/resume attempt
  const startAttempt = useStartAttempt(courseId!, dayNumber);

  // Submit answer mutation
  const submitAnswer = useSubmitAnswer(
    (result) => {
      dispatch({ type: 'SUBMIT_SUCCESS', result });
      // Auto-advance on correct after delay
      if (result.is_correct) {
        setTimeout(() => dispatch({ type: 'CONTINUE' }), CORRECT_ANSWER_ADVANCE_MS);
      }
    },
    () => {
      dispatch({ type: 'SUBMIT_OFFLINE' });
    },
  );

  // Complete quiz mutation
  const completeQuiz = useCompleteQuiz();

  // Initialize state when data arrives
  useEffect(() => {
    if (!quizDay) return;
    dispatch({
      type: 'INIT',
      questions: quizDay.questions,
      masteredIds: quizDay.attempt.mastered_question_ids,
      retryIds: quizDay.attempt.retry_required_question_ids,
      required: 10,
    });
    // Start/resume the attempt
    startAttempt.mutate();
  }, [quizDay]); // eslint-disable-line react-hooks/exhaustive-deps

  // Reset timer when question changes
  useEffect(() => {
    timeRef.current = Date.now();
  }, [state.currentIndex]);

  const handleOptionSelect = useCallback(
    (option: QuestionOption) => {
      dispatch({ type: 'SELECT_OPTION', option });
    },
    [],
  );

  const handleSubmit = useCallback(() => {
    const question = getCurrentQuestion(state);
    if (!question || !state.selectedOption || !quizDay) return;

    dispatch({ type: 'SUBMIT_START' });
    const timeSpent = Date.now() - timeRef.current;

    submitAnswer.mutate({
      attemptUuid: quizDay.attempt.uuid,
      questionId: question.id,
      selectedOption: state.selectedOption,
      timeSpentMs: timeSpent,
    });
  }, [state, quizDay, submitAnswer]);

  const handleContinue = useCallback(() => {
    dispatch({ type: 'CONTINUE' });
  }, []);

  const handleComplete = useCallback(() => {
    if (!quizDay) return;
    completeQuiz.mutate(quizDay.attempt.uuid, {
      onSuccess: () => {
        navigate(ROUTES.QUIZ_RESULT(quizDay.attempt.uuid));
      },
    });
  }, [quizDay, completeQuiz, navigate]);

  // Loading / error states
  if (isLoading) return <PageSpinner />;
  if (isError) {
    return (
      <div className="flex flex-1 flex-col items-center justify-center gap-4 p-4 text-center">
        <p className="text-lg font-medium text-surface-200">This day is not available yet</p>
        <Button variant="secondary" onClick={() => navigate(-1)}>
          Go Back
        </Button>
      </div>
    );
  }

  const currentQuestion = getCurrentQuestion(state);

  // All mastered — show completion
  if (state.canComplete && !currentQuestion) {
    return (
      <div className="flex flex-1 flex-col">
        <QuizProgressHeader
          mastered={state.masteredIds.size}
          required={state.required}
          courseId={courseId!}
          dayNumber={dayNumber}
        />
        <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
          <span className="text-5xl">🎉</span>
          <h2 className="text-2xl font-bold text-white">All 10 Mastered!</h2>
          <p className="text-surface-400">Tap below to complete this day</p>
          <Button
            fullWidth
            loading={completeQuiz.isPending}
            onClick={handleComplete}
          >
            Complete Day {dayNumber}
          </Button>
          <OfflineSaveIndicator />
        </div>
      </div>
    );
  }

  if (!currentQuestion) return <PageSpinner />;

  // Determine option states for rendering
  const getOptionState = (key: QuestionOption): OptionState => {
    if (state.answerPhase === 'feedback' && state.lastResult) {
      if (key === state.lastResult.correct_option) return 'correct';
      if (key === state.selectedOption && !state.lastResult.is_correct) return 'incorrect';
      return 'disabled';
    }
    if (key === state.selectedOption) return 'selected';
    return 'idle';
  };

  return (
    <div className="flex flex-1 flex-col">
      <QuizProgressHeader
        mastered={state.masteredIds.size}
        required={state.required}
        courseId={courseId!}
        dayNumber={dayNumber}
      />

      <div className="flex flex-1 flex-col px-4 py-6">
        <QuestionCard
          question={currentQuestion}
          questionNumber={state.currentIndex + 1}
          totalQuestions={state.questionQueue.length}
        />

        {/* Options */}
        <div className="mt-6 space-y-3">
          {currentQuestion.options
            .sort((a, b) => a.display_position - b.display_position)
            .map((option) => (
              <QuizOptionButton
                key={option.key}
                optionKey={option.key}
                text={option.text}
                state={getOptionState(option.key)}
                disabled={state.answerPhase !== 'selecting'}
                onSelect={handleOptionSelect}
              />
            ))}
        </div>

        {/* Submit button (only in selecting phase with an option chosen) */}
        {state.answerPhase === 'selecting' && state.selectedOption && (
          <div className="mt-6">
            <Button fullWidth onClick={handleSubmit}>
              Submit Answer
            </Button>
          </div>
        )}

        {/* Submitting spinner */}
        {state.answerPhase === 'submitting' && (
          <div className="mt-6 text-center text-sm text-surface-400">
            Checking...
          </div>
        )}

        {/* Feedback */}
        {state.answerPhase === 'feedback' && state.lastResult && (
          <AnswerFeedback
            isCorrect={state.lastResult.is_correct}
            correctAnswerText={state.lastResult.correct_answer_text}
            explanation={state.lastResult.explanation}
            onContinue={handleContinue}
            continueLabel={state.lastResult.retry_required ? 'Got it — will retry later' : 'Continue'}
          />
        )}

        {/* Offline pending */}
        {state.answerPhase === 'feedback' && !state.lastResult && (
          <div className="mt-4 rounded-xl border border-warning-500/30 bg-warning-500/5 p-4 text-center">
            <p className="text-sm text-warning-500">Saved offline — will sync when connected</p>
            <Button variant="secondary" className="mt-3" onClick={handleContinue}>
              Continue
            </Button>
          </div>
        )}

        <div className="mt-4 flex justify-center">
          <OfflineSaveIndicator />
        </div>
      </div>
    </div>
  );
}

export const Component = DailyQuizScreen;
export default DailyQuizScreen;
