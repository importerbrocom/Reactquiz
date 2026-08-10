import { cn } from '@/utils/cn';
import type { QuestionOption } from '@/types/enums';

export type OptionState = 'idle' | 'selected' | 'correct' | 'incorrect' | 'disabled';

interface QuizOptionButtonProps {
  optionKey: QuestionOption;
  text: string;
  state: OptionState;
  disabled: boolean;
  onSelect: (key: QuestionOption) => void;
}

const stateStyles: Record<OptionState, string> = {
  idle: 'border-surface-700 bg-surface-900 hover:border-surface-500 hover:bg-surface-800',
  selected: 'border-primary-500 bg-primary-500/10',
  correct: 'border-success-500 bg-success-500/10',
  incorrect: 'border-danger-500 bg-danger-500/10',
  disabled: 'border-surface-700 bg-surface-900 opacity-60',
};

const iconStyles: Record<OptionState, string> = {
  idle: 'border-surface-600 text-surface-400',
  selected: 'border-primary-500 bg-primary-500 text-white',
  correct: 'border-success-500 bg-success-500 text-white',
  incorrect: 'border-danger-500 bg-danger-500 text-white',
  disabled: 'border-surface-600 text-surface-500',
};

/**
 * Quiz answer option button.
 * States: idle, selected, correct, incorrect, disabled.
 * Always submits the canonical `key` (a/b/c/d), never the display position.
 */
export function QuizOptionButton({
  optionKey,
  text,
  state,
  disabled,
  onSelect,
}: QuizOptionButtonProps) {
  return (
    <button
      type="button"
      className={cn(
        'flex min-h-[44px] w-full items-start gap-3 rounded-xl border-2 px-4 py-3 text-left transition-all',
        stateStyles[state],
        !disabled && state === 'idle' && 'active:scale-[0.98]',
      )}
      disabled={disabled}
      onClick={() => onSelect(optionKey)}
      aria-pressed={state === 'selected'}
      aria-label={`Option ${optionKey.toUpperCase()}: ${text}`}
    >
      <span
        className={cn(
          'flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold',
          iconStyles[state],
        )}
      >
        {state === 'correct' ? (
          <CheckIcon />
        ) : state === 'incorrect' ? (
          <XIcon />
        ) : (
          optionKey.toUpperCase()
        )}
      </span>
      <span className="flex-1 pt-0.5 text-sm leading-relaxed text-surface-100">
        {text}
      </span>
    </button>
  );
}

function CheckIcon() {
  return (
    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
    </svg>
  );
}

function XIcon() {
  return (
    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={3}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
    </svg>
  );
}
