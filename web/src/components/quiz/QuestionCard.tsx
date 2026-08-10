import { cn } from '@/utils/cn';
import type { QuizQuestion } from '@/types/models';

interface QuestionCardProps {
  question: QuizQuestion;
  questionNumber: number;
  totalQuestions: number;
  className?: string;
}

/**
 * Displays the question text and optional image.
 * Presentational only — no answer logic.
 */
export function QuestionCard({ question, questionNumber, totalQuestions, className }: QuestionCardProps) {
  return (
    <div className={cn('space-y-4', className)}>
      <div className="flex items-center justify-between text-sm text-surface-400">
        <span>Question {questionNumber} of {totalQuestions}</span>
      </div>

      {question.image_url && (
        <div className="overflow-hidden rounded-lg">
          <img
            src={question.image_url}
            alt="Question illustration"
            className="h-auto max-h-64 w-full object-contain"
            loading="lazy"
          />
        </div>
      )}

      <h2 className="text-lg font-medium leading-relaxed text-white">
        {question.text}
      </h2>
    </div>
  );
}
