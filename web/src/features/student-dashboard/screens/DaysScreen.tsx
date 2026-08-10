import { useParams } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { getCourseDays } from '@/api/endpoints/student-dashboard.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { PageSpinner } from '@/components/ui/Spinner';
import { cn } from '@/utils/cn';
import { DayStatus } from '@/types/enums';

function DaysScreen() {
  const { courseId } = useParams<{ courseId: string }>();

  const { data: days, isLoading } = useQuery({
    queryKey: queryKeys.days.list(courseId!),
    queryFn: () => getCourseDays(courseId!),
    enabled: !!courseId,
  });

  if (isLoading || !days) return <PageSpinner />;

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-bold text-white">Your Days</h1>
      <p className="text-sm text-surface-400">
        Complete each day at 10/10 to unlock the next
      </p>

      <div className="grid grid-cols-5 gap-2 sm:grid-cols-6">
        {days.map((day) => (
          <DayCell
            key={day.day_number}
            dayNumber={day.day_number}
            status={day.status}
            score={day.score}
            courseId={courseId!}
          />
        ))}
      </div>

      {/* Legend */}
      <div className="flex flex-wrap gap-4 text-xs text-surface-400">
        <span className="flex items-center gap-1.5">
          <span className="h-3 w-3 rounded-sm bg-success-500" /> Completed
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-3 w-3 rounded-sm bg-primary-500" /> In Progress
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-3 w-3 rounded-sm bg-surface-700" /> Available
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-3 w-3 rounded-sm bg-surface-800 opacity-50" /> Locked
        </span>
      </div>
    </div>
  );
}

interface DayCellProps {
  dayNumber: number;
  status: DayStatus;
  score: number | null;
  courseId: string;
}

function DayCell({ dayNumber, status, score, courseId }: DayCellProps) {
  const isClickable = status !== DayStatus.Locked;

  const cellStyles = {
    [DayStatus.Completed]: 'border-success-500/50 bg-success-500/10 text-success-500',
    [DayStatus.InProgress]: 'border-primary-500/50 bg-primary-500/10 text-primary-400',
    [DayStatus.Available]: 'border-surface-600 bg-surface-800 text-surface-200 hover:border-surface-500',
    [DayStatus.Locked]: 'border-surface-800 bg-surface-900 text-surface-600 opacity-60',
  };

  const content = (
    <div
      className={cn(
        'flex aspect-square flex-col items-center justify-center rounded-lg border text-center transition-colors',
        cellStyles[status],
        isClickable && 'cursor-pointer',
      )}
    >
      <span className="text-xs font-medium">{dayNumber}</span>
      {status === DayStatus.Completed && score !== null && (
        <span className="text-[10px] opacity-75">{score}/10</span>
      )}
      {status === DayStatus.Locked && (
        <svg className="mt-0.5 h-3 w-3 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
      )}
    </div>
  );

  if (isClickable) {
    return (
      <Link to={ROUTES.QUIZ_DAY(courseId, dayNumber)} aria-label={`Day ${dayNumber}`}>
        {content}
      </Link>
    );
  }

  return content;
}

export const Component = DaysScreen;
export default DaysScreen;
