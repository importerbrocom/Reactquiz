import { ProgressBar } from '@/components/ui/ProgressBar';
import { ROUTES } from '@/config/routes.config';
import { Link } from 'react-router';

interface QuizProgressHeaderProps {
  mastered: number;
  required: number;
  courseId: string;
  dayNumber: number;
}

/**
 * Header shown during the daily quiz.
 * Shows mastered/required progress (not answered/total).
 */
export function QuizProgressHeader({ mastered, required, courseId, dayNumber }: QuizProgressHeaderProps) {
  return (
    <header className="space-y-3 border-b border-surface-800 bg-surface-950 px-4 pb-4 pt-4">
      <div className="flex items-center justify-between">
        <Link
          to={ROUTES.COURSE_DAYS(courseId)}
          className="min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg text-surface-400 hover:text-surface-200"
          aria-label="Exit quiz"
        >
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </Link>
        <span className="text-sm font-medium text-surface-300">Day {dayNumber}</span>
        <span className="min-w-[44px] text-right text-sm font-bold text-primary-400">
          {mastered}/{required}
        </span>
      </div>
      <ProgressBar
        value={mastered}
        max={required}
        variant="success"
        size="sm"
        ariaLabel={`${mastered} of ${required} questions mastered`}
      />
    </header>
  );
}
