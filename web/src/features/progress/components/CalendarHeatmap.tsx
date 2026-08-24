import { DayStatus } from '@/types/enums';
import { Card } from '@/components/ui/Card';
import { cn } from '@/utils/cn';
import type { ProgressCalendarDay } from '@/types/models';

interface CalendarHeatmapProps {
  days: ProgressCalendarDay[];
}

/**
 * A 6×5 grid calendar showing day status with colour-coded score intensity.
 * Green for completed (darker = higher score), blue for in-progress,
 * neutral for available/locked.
 */
export function CalendarHeatmap({ days }: CalendarHeatmapProps) {
  // Group by levels of 30
  const levels: ProgressCalendarDay[][] = [];
  for (let i = 0; i < days.length; i += 30) {
    levels.push(days.slice(i, i + 30));
  }

  return (
    <div className="space-y-6">
      {levels.map((levelDays, levelIndex) => {
        const completedInLevel = levelDays.filter((d) => d.status === DayStatus.Completed).length;
        return (
          <Card key={levelIndex} variant="outlined">
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-medium text-white">
                Level {levelIndex + 1}
              </h3>
              <span className="text-xs text-surface-400">
                {completedInLevel}/{levelDays.length} days
              </span>
            </div>

            {/* 6 columns × 5 rows grid */}
            <div className="grid grid-cols-6 gap-1.5">
              {levelDays.map((day) => (
                <CalendarCell key={day.day_number} day={day} />
              ))}
            </div>

            {/* Legend */}
            <div className="mt-3 flex items-center justify-between text-[10px] text-surface-500">
              <div className="flex items-center gap-3">
                <span className="flex items-center gap-1">
                  <span className="h-2.5 w-2.5 rounded-sm bg-success-500/80" /> 10/10
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2.5 w-2.5 rounded-sm bg-success-500/40" /> 7–9
                </span>
                <span className="flex items-center gap-1">
                  <span className="h-2.5 w-2.5 rounded-sm bg-warning-500/40" /> &lt;7
                </span>
              </div>
              <span className="flex items-center gap-1">
                <span className="h-2.5 w-2.5 rounded-sm bg-primary-500/40" /> In progress
              </span>
            </div>
          </Card>
        );
      })}
    </div>
  );
}

function CalendarCell({ day }: { day: ProgressCalendarDay }) {
  const cellClasses = getCellClasses(day);

  return (
    <div
      className={cn(
        'flex aspect-square flex-col items-center justify-center rounded-md text-center transition-colors',
        cellClasses,
      )}
      title={getTooltip(day)}
    >
      <span className="text-[10px] font-medium leading-none">{day.day_number}</span>
      {day.status === DayStatus.Completed && day.score !== null && (
        <span className="mt-0.5 text-[8px] leading-none opacity-75">
          {day.score}/10
        </span>
      )}
    </div>
  );
}

/**
 * Score-based colour intensity:
 * - 10/10: bright green
 * - 7–9: medium green
 * - <7: dim warning (they'll need to retry)
 */
function getCellClasses(day: ProgressCalendarDay): string {
  switch (day.status) {
    case DayStatus.Completed: {
      if (day.score === null) return 'bg-success-500/20 text-success-500';
      if (day.score === 10) return 'bg-success-500/30 text-success-500 ring-1 ring-success-500/40';
      if (day.score >= 7) return 'bg-success-500/20 text-success-500/80';
      return 'bg-warning-500/15 text-warning-500/80';
    }
    case DayStatus.InProgress:
      return 'bg-primary-500/15 text-primary-400 ring-1 ring-primary-500/30';
    case DayStatus.Available:
      return 'bg-surface-800 text-surface-300';
    case DayStatus.Locked:
      return 'bg-surface-900 text-surface-600 opacity-50';
    default:
      return 'bg-surface-900 text-surface-600';
  }
}

function getTooltip(day: ProgressCalendarDay): string {
  switch (day.status) {
    case DayStatus.Completed:
      return `Day ${day.day_number}: Completed${day.score !== null ? ` (${day.score}/10)` : ''}`;
    case DayStatus.InProgress:
      return `Day ${day.day_number}: In progress`;
    case DayStatus.Available:
      return `Day ${day.day_number}: Available`;
    case DayStatus.Locked:
      return `Day ${day.day_number}: Locked`;
    default:
      return `Day ${day.day_number}`;
  }
}
