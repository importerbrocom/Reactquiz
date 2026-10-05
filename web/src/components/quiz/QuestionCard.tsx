import { cn } from '@/utils/cn';
import type { QuizQuestion } from '@/types/models';

interface QuestionCardProps {
  question: QuizQuestion;
  questionNumber: number;
  totalQuestions: number;
  /** optional subject/topic label shown as a chip (e.g. "Internal Medicine") */
  topic?: string | null;
  className?: string;
}

/**
 * Displays the question text and optional image.
 * Presentational only — no answer logic.
 */
export function QuestionCard({
  question,
  questionNumber,
  totalQuestions,
  topic,
  className,
}: QuestionCardProps) {
  return (
    <div
      className={cn(
        'space-y-4 rounded-2xl border border-surface-700 bg-surface-900/50 p-5',
        className,
      )}
    >
      <div className="flex items-center justify-between gap-3">
        <span className="text-lg font-bold text-primary-400">Q{questionNumber}.</span>
        {topic && (
          <span className="rounded-full border border-surface-700 bg-surface-800 px-3 py-1 text-xs font-medium text-surface-300">
            {topic}
          </span>
        )}
      </div>

      <h2 className="text-[17px] font-medium leading-relaxed text-white">{question.text}</h2>

      {question.image_url && (
        <div className="overflow-hidden rounded-xl border border-surface-700 bg-surface-950">
          <img
            src={question.image_url}
            alt="Question illustration"
            className="h-auto max-h-72 w-full object-contain"
            loading="lazy"
          />
        </div>
      )}

      <p className="text-right text-xs text-surface-500">
        Question {questionNumber} of {totalQuestions}
      </p>
    </div>
  );
}
