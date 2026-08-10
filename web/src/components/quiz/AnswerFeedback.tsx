import { cn } from '@/utils/cn';

interface AnswerFeedbackProps {
  isCorrect: boolean;
  correctAnswerText: string | null;
  explanation: string | null;
  onContinue: () => void;
  continueLabel?: string;
}

/**
 * Feedback shown after answering a question.
 * Correct: brief positive, auto-advance after 450ms.
 * Incorrect: shows correct answer + explanation + manual "Try Again" / "Continue" button.
 */
export function AnswerFeedback({
  isCorrect,
  correctAnswerText,
  explanation,
  onContinue,
  continueLabel = 'Continue',
}: AnswerFeedbackProps) {
  return (
    <div
      className={cn(
        'mt-4 rounded-xl border p-4',
        isCorrect
          ? 'border-success-500/30 bg-success-500/5'
          : 'border-danger-500/30 bg-danger-500/5',
      )}
      role="status"
      aria-live="polite"
    >
      <div className="flex items-start gap-3">
        {/* Icon */}
        <span
          className={cn(
            'flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-sm',
            isCorrect ? 'bg-success-500 text-white' : 'bg-danger-500 text-white',
          )}
          aria-hidden="true"
        >
          {isCorrect ? '✓' : '✗'}
        </span>

        <div className="flex-1 space-y-2">
          <p className={cn('font-medium', isCorrect ? 'text-success-500' : 'text-danger-500')}>
            {isCorrect ? 'Correct!' : 'Incorrect'}
          </p>

          {!isCorrect && correctAnswerText && (
            <p className="text-sm text-surface-200">
              <span className="text-surface-400">Correct answer: </span>
              {correctAnswerText}
            </p>
          )}

          {explanation && (
            <p className="text-sm leading-relaxed text-surface-300">
              {explanation}
            </p>
          )}
        </div>
      </div>

      {/* Manual continue button (for incorrect answers) */}
      {!isCorrect && (
        <button
          type="button"
          onClick={onContinue}
          className="mt-4 min-h-[44px] w-full rounded-lg bg-surface-800 px-4 py-2.5 text-sm font-medium text-surface-200 transition-colors hover:bg-surface-700"
        >
          {continueLabel}
        </button>
      )}
    </div>
  );
}
