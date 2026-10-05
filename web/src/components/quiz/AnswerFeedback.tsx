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
    <div className="mt-4 space-y-3" role="status" aria-live="polite">
      {/* Verdict banner */}
      <div
        className={cn(
          'flex items-center gap-3 rounded-2xl border p-4',
          isCorrect
            ? 'border-success-500/40 bg-success-500/10'
            : 'border-danger-500/40 bg-danger-500/10',
        )}
      >
        <span
          className={cn(
            'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-base font-bold',
            isCorrect ? 'bg-success-500 text-white' : 'bg-danger-500 text-white',
          )}
          aria-hidden="true"
        >
          {isCorrect ? '✓' : '✗'}
        </span>
        <div className="flex-1">
          <p className={cn('font-bold', isCorrect ? 'text-success-500' : 'text-danger-500')}>
            {isCorrect ? 'Correct!' : 'Incorrect'}
          </p>
          {correctAnswerText && (
            <p className="text-sm text-surface-200">
              <span className="text-surface-400">Correct answer: </span>
              <span className={isCorrect ? 'text-success-400' : 'text-surface-100'}>
                {correctAnswerText}
              </span>
            </p>
          )}
        </div>
      </div>

      {/* Explanation card */}
      {explanation && (
        <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-4">
          <p className="mb-1.5 flex items-center gap-2 text-sm font-semibold text-white">
            <span aria-hidden>📖</span> Explanation
          </p>
          <p className="text-sm leading-relaxed text-surface-300">{explanation}</p>
        </div>
      )}

      {/* Manual continue button (for incorrect answers) */}
      {!isCorrect && (
        <button
          type="button"
          onClick={onContinue}
          className="min-h-[48px] w-full rounded-xl bg-surface-800 px-4 py-3 text-sm font-semibold text-surface-100 transition-colors hover:bg-surface-700"
        >
          {continueLabel}
        </button>
      )}
    </div>
  );
}
