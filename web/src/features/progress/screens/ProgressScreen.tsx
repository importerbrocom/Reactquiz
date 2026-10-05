import { useQuery } from '@tanstack/react-query';
import { getProgressOverview, getTopicProgress } from '@/api/endpoints/progress.api';
import { queryKeys } from '@/api/query-keys';
import { usePreferencesStore } from '@/store/preferences.store';
import { PageSpinner } from '@/components/ui/Spinner';
import type { TopicProgress } from '@/types/models';

/**
 * Progress screen — redesigned to match the ERO mockups: a large accuracy
 * ring, a stat grid, and subject-wise accuracy bars. All values come from the
 * existing /student/progress endpoints (overview + topics). The trend chart in
 * the mockup is intentionally omitted here because the /charts payload is
 * currently untyped — it can be added once the API shape is pinned down.
 */
function ProgressScreen() {
  const courseId = usePreferencesStore((s) => s.lastCourseId) ?? '';

  const { data: overview, isLoading } = useQuery({
    queryKey: queryKeys.progress.overview(courseId),
    queryFn: () => getProgressOverview(courseId),
    enabled: !!courseId,
  });

  const { data: topics } = useQuery({
    queryKey: queryKeys.progress.topics(courseId),
    queryFn: () => getTopicProgress(courseId),
    enabled: !!courseId,
  });

  if (isLoading || !overview) return <PageSpinner />;

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold text-white">Progress</h1>

      {/* Accuracy ring + headline */}
      <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-5">
        <div className="flex items-center gap-5">
          <AccuracyRing percent={overview.accuracy_percent} />
          <div className="flex-1">
            <p className="text-sm text-surface-400">Overall Accuracy</p>
            <p className="text-3xl font-bold text-white">{overview.accuracy_percent}%</p>
            <p className="mt-1 text-xs text-surface-400">
              {overview.total_mastered} of {overview.total_questions_seen} questions mastered
            </p>
          </div>
        </div>
      </div>

      {/* Stat grid */}
      <div className="grid grid-cols-2 gap-3">
        <StatTile value={overview.days_completed} label="Days Completed" accent="text-primary-400" icon="📅" />
        <StatTile value={`${overview.current_streak}d`} label="Current Streak" accent="text-accent-amber" icon="🔥" />
        <StatTile value={overview.total_questions_seen} label="Questions Seen" accent="text-accent-teal" icon="📄" />
        <StatTile value={`${overview.time_spent_hours}h`} label="Time Spent" accent="text-accent-purple" icon="⏱️" />
      </div>

      {/* Subject-wise accuracy */}
      {topics && topics.length > 0 && (
        <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-5">
          <h3 className="mb-4 font-semibold text-white">Subject-wise Accuracy</h3>
          <div className="space-y-4">
            {topics.map((topic) => (
              <SubjectBar key={topic.topic} topic={topic} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/** SVG accuracy ring — matches the circular gauge in the mockup. */
function AccuracyRing({ percent }: { percent: number }) {
  const size = 92;
  const stroke = 9;
  const r = (size - stroke) / 2;
  const circ = 2 * Math.PI * r;
  const offset = circ - (Math.min(100, Math.max(0, percent)) / 100) * circ;

  return (
    <svg width={size} height={size} className="flex-shrink-0 -rotate-90">
      <circle
        cx={size / 2}
        cy={size / 2}
        r={r}
        fill="none"
        stroke="var(--color-surface-800)"
        strokeWidth={stroke}
      />
      <circle
        cx={size / 2}
        cy={size / 2}
        r={r}
        fill="none"
        stroke="var(--color-primary-500)"
        strokeWidth={stroke}
        strokeDasharray={circ}
        strokeDashoffset={offset}
        strokeLinecap="round"
        className="transition-all duration-700"
      />
      <text
        x="50%"
        y="50%"
        dominantBaseline="central"
        textAnchor="middle"
        className="rotate-90 fill-white text-[18px] font-bold"
        style={{ transformOrigin: 'center' }}
      >
        {percent}%
      </text>
    </svg>
  );
}

function StatTile({
  value,
  label,
  accent,
  icon,
}: {
  value: string | number;
  label: string;
  accent: string;
  icon: string;
}) {
  return (
    <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-4">
      <span className="text-lg">{icon}</span>
      <p className={`mt-2 text-2xl font-bold ${accent}`}>{value}</p>
      <p className="text-xs text-surface-400">{label}</p>
    </div>
  );
}

function SubjectBar({ topic }: { topic: TopicProgress }) {
  const barColor =
    topic.strength === 'strong'
      ? 'bg-success-500'
      : topic.strength === 'moderate'
        ? 'bg-warning-500'
        : 'bg-danger-500';
  const textColor =
    topic.strength === 'strong'
      ? 'text-success-500'
      : topic.strength === 'moderate'
        ? 'text-warning-500'
        : 'text-danger-500';

  return (
    <div>
      <div className="mb-1.5 flex items-center justify-between">
        <span className="text-sm text-surface-200">{topic.topic}</span>
        <span className={`text-xs font-semibold ${textColor}`}>
          {topic.accuracy_percent}%{' '}
          <span className="text-surface-500">
            ({topic.correct}/{topic.total})
          </span>
        </span>
      </div>
      <div className="h-2 overflow-hidden rounded-full bg-surface-800">
        <div
          className={`h-full rounded-full ${barColor} transition-all duration-500`}
          style={{ width: `${topic.accuracy_percent}%` }}
        />
      </div>
    </div>
  );
}

export const Component = ProgressScreen;
export default ProgressScreen;
