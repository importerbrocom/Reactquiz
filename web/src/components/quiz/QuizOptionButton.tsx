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
  idle: 'border-surface-700 bg-surface-900/60 hover:border-primary-500/60 hover:bg-surface-800',
  selected: 'border-primary-500 bg-primary-500/15',
  correct: 'border-success-500 bg-success-500/15',
  incorrect: 'border-danger-500 bg-danger-500/15',
  disabled: 'border-surface-800 bg-surface-900/60 opacity-60',
};

const iconStyles: Record<OptionState, string> = {
  idle: 'border-surface-600 text-surface-300',
  selected: 'border-primary-500 bg-primary-500 text-white',
  correct: 'border-success-500 bg-success-500 text-white',
  incorrect: 'border-danger-500 bg-danger-500 text-white',
  disabled: 'border-surface-700 text-surface-500',
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
        'flex min-h-[56px] w-full items-center gap-3.5 rounded-2xl border px-4 py-3.5 text-left transition-all',
        stateStyles[state],
        !disabled && state === 'idle' && 'active:scale-[0.99]',
      )}
      disabled={disabled}
      onClick={() => onSelect(optionKey)}
      aria-pressed={state === 'selected'}
      aria-label={`Option ${optionKey.toUpperCase()}: ${text}`}
    >
      <span
        className={cn(
          'flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold',
          iconStyles[state],
        )}
      >
        {optionKey.toUpperCase()}
      </span>
      <span className="flex-1 text-[15px] leading-relaxed text-surface-100">{text}</span>
      {state === 'correct' && (
        <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-success-500 text-white">
          <CheckIcon />
        </span>
      )}
      {state === 'incorrect' && (
        <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-danger-500 text-white">
          <XIcon />
        </span>
      )}
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
