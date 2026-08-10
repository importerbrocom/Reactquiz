import { useQuery } from '@tanstack/react-query';
import { getProgressOverview, getTopicProgress } from '@/api/endpoints/progress.api';
import { queryKeys } from '@/api/query-keys';
import { usePreferencesStore } from '@/store/preferences.store';
import { Card } from '@/components/ui/Card';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { PageSpinner } from '@/components/ui/Spinner';

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
    <div className="space-y-6">
      <h1 className="text-xl font-bold text-white">Progress</h1>

      {/* Headline stats */}
      <div className="grid grid-cols-2 gap-3">
        <StatCard label="Days Completed" value={overview.days_completed.toString()} />
        <StatCard label="Accuracy" value={`${overview.accuracy_percent}%`} />
        <StatCard label="Questions Seen" value={overview.total_questions_seen.toString()} />
        <StatCard label="Mastered" value={overview.total_mastered.toString()} />
        <StatCard label="Current Streak" value={`${overview.current_streak} days`} />
        <StatCard label="Time Spent" value={`${overview.time_spent_hours}h`} />
      </div>

      {/* Topic breakdown */}
      {topics && topics.length > 0 && (
        <Card variant="outlined">
          <h3 className="mb-4 font-medium text-white">Topic Performance</h3>
          <div className="space-y-4">
            {topics.map((topic) => (
              <div key={topic.topic}>
                <div className="mb-1 flex items-center justify-between">
                  <span className="text-sm text-surface-200">{topic.topic}</span>
                  <span className={`text-xs font-medium ${
                    topic.strength === 'strong' ? 'text-success-500' :
                    topic.strength === 'moderate' ? 'text-warning-500' : 'text-danger-500'
                  }`}>
                    {topic.accuracy_percent}% ({topic.correct}/{topic.total})
                  </span>
                </div>
                <ProgressBar
                  value={topic.accuracy_percent}
                  max={100}
                  variant={
                    topic.strength === 'strong' ? 'success' :
                    topic.strength === 'moderate' ? 'warning' : 'danger'
                  }
                  size="sm"
                />
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string }) {
  return (
    <Card variant="outlined" className="text-center">
      <p className="text-lg font-bold text-white">{value}</p>
      <p className="text-xs text-surface-400">{label}</p>
    </Card>
  );
}

export const Component = ProgressScreen;
export default ProgressScreen;
