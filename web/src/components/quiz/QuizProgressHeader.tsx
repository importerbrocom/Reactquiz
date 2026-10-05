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
    <header className="space-y-3 border-b border-surface-800 bg-surface-950/95 px-4 pb-4 pt-4 backdrop-blur-sm">
      <div className="flex items-center justify-between gap-3">
        <Link
          to={ROUTES.COURSE_DAYS(courseId)}
          className="flex min-h-[44px] min-w-[44px] items-center justify-center rounded-full text-surface-400 hover:bg-surface-800 hover:text-surface-200"
          aria-label="Exit quiz"
        >
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </Link>
        <span className="text-sm font-semibold text-surface-200">Day {dayNumber}</span>
        <span className="flex items-center gap-1.5 rounded-full bg-primary-500/15 px-3 py-1.5 text-sm font-bold text-primary-400">
          <span aria-hidden>✓</span>
          {mastered}/{required}
        </span>
      </div>
      <ProgressBar
        value={mastered}
        max={required}
        variant="success"
        size="md"
        ariaLabel={`${mastered} of ${required} questions mastered`}
      />
    </header>
  );
}
