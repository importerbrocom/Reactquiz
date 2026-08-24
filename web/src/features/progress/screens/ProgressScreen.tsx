import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  getProgressOverview,
  getProgressCalendar,
  getTopicProgress,
} from '@/api/endpoints/progress.api';
import { queryKeys } from '@/api/query-keys';
import { usePreferencesStore } from '@/store/preferences.store';
import { Card } from '@/components/ui/Card';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { Badge } from '@/components/ui/Badge';
import { PageSpinner } from '@/components/ui/Spinner';
import { AccuracyTrendChart } from '../components/AccuracyTrendChart';
import { CalendarHeatmap } from '../components/CalendarHeatmap';
import { DayStatus } from '@/types/enums';
import { cn } from '@/utils/cn';

type Tab = 'overview' | 'calendar' | 'topics';

function ProgressScreen() {
  const courseId = usePreferencesStore((s) => s.lastCourseId) ?? '';
  const [activeTab, setActiveTab] = useState<Tab>('overview');

  const { data: overview, isLoading } = useQuery({
    queryKey: queryKeys.progress.overview(courseId),
    queryFn: () => getProgressOverview(courseId),
    enabled: !!courseId,
  });

  const { data: calendar } = useQuery({
    queryKey: queryKeys.progress.calendar(courseId),
    queryFn: () => getProgressCalendar(courseId),
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

      {/* Tab selector */}
      <div className="flex gap-1 rounded-lg bg-surface-800/50 p-1">
        <TabButton active={activeTab === 'overview'} onClick={() => setActiveTab('overview')}>
          Overview
        </TabButton>
        <TabButton active={activeTab === 'calendar'} onClick={() => setActiveTab('calendar')}>
          Calendar
        </TabButton>
        <TabButton active={activeTab === 'topics'} onClick={() => setActiveTab('topics')}>
          Topics
        </TabButton>
      </div>

      {/* Overview tab */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {/* Headline stats */}
          <div className="grid grid-cols-2 gap-3">
            <StatCard label="Days Completed" value={overview.days_completed.toString()} icon="📅" />
            <StatCard label="Accuracy" value={`${overview.accuracy_percent}%`} icon="🎯" />
            <StatCard label="Questions Seen" value={overview.total_questions_seen.toString()} icon="📝" />
            <StatCard label="Mastered" value={overview.total_mastered.toString()} icon="✅" />
            <StatCard label="Current Streak" value={`${overview.current_streak} days`} icon="🔥" />
            <StatCard label="Time Spent" value={`${overview.time_spent_hours}h`} icon="⏱️" />
          </div>

          {/* Accuracy trend chart */}
          {calendar && calendar.length > 0 && (
            <Card variant="outlined">
              <h3 className="mb-4 font-medium text-white">Daily Accuracy Trend</h3>
              <AccuracyTrendChart
                data={calendar
                  .filter((d) => d.status === DayStatus.Completed && d.score !== null)
                  .map((d) => ({
                    day: d.day_number,
                    score: d.score! * 10, // Convert 10/10 to percentage
                  }))}
              />
            </Card>
          )}

          {/* Mastery progress */}
          <Card variant="outlined">
            <h3 className="mb-3 font-medium text-white">Mastery Rate</h3>
            <ProgressBar
              value={overview.total_mastered}
              max={overview.total_questions_seen || 1}
              variant="success"
              size="lg"
              showLabel
              label={`${overview.total_mastered} of ${overview.total_questions_seen} questions mastered`}
            />
          </Card>
        </div>
      )}

      {/* Calendar tab */}
      {activeTab === 'calendar' && (
        <div className="space-y-4">
          {calendar && calendar.length > 0 ? (
            <CalendarHeatmap days={calendar} />
          ) : (
            <div className="py-12 text-center">
              <span className="text-4xl">📅</span>
              <p className="mt-3 text-surface-400">Complete your first day to see your calendar</p>
            </div>
          )}
        </div>
      )}

      {/* Topics tab */}
      {activeTab === 'topics' && (
        <div className="space-y-4">
          {topics && topics.length > 0 ? (
            <>
              {/* Summary chips */}
              <div className="flex flex-wrap gap-2">
                <Badge variant="success">
                  {topics.filter((t) => t.strength === 'strong').length} Strong
                </Badge>
                <Badge variant="warning">
                  {topics.filter((t) => t.strength === 'moderate').length} Moderate
                </Badge>
                <Badge variant="danger">
                  {topics.filter((t) => t.strength === 'weak').length} Weak
                </Badge>
              </div>

              {/* Topic cards sorted by weakness */}
              <div className="space-y-3">
                {[...topics]
                  .sort((a, b) => a.accuracy_percent - b.accuracy_percent)
                  .map((topic) => (
                    <Card key={topic.topic} variant="outlined">
                      <div className="mb-2 flex items-center justify-between">
                        <span className="text-sm font-medium text-surface-200">{topic.topic}</span>
                        <div className="flex items-center gap-2">
                          <span
                            className={cn('text-xs font-medium', {
                              'text-success-500': topic.strength === 'strong',
                              'text-warning-500': topic.strength === 'moderate',
                              'text-danger-500': topic.strength === 'weak',
                            })}
                          >
                            {topic.accuracy_percent}%
                          </span>
                          <span className="text-xs text-surface-500">
                            ({topic.correct}/{topic.total})
                          </span>
                        </div>
                      </div>
                      <ProgressBar
                        value={topic.accuracy_percent}
                        max={100}
                        variant={
                          topic.strength === 'strong'
                            ? 'success'
                            : topic.strength === 'moderate'
                              ? 'warning'
                              : 'danger'
                        }
                        size="sm"
                      />
                    </Card>
                  ))}
              </div>
            </>
          ) : (
            <div className="py-12 text-center">
              <span className="text-4xl">📊</span>
              <p className="mt-3 text-surface-400">Complete quizzes to see topic performance</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

function StatCard({ label, value, icon }: { label: string; value: string; icon: string }) {
  return (
    <Card variant="outlined" className="text-center">
      <span className="text-lg">{icon}</span>
      <p className="mt-1 text-lg font-bold text-white">{value}</p>
      <p className="text-xs text-surface-400">{label}</p>
    </Card>
  );
}

function TabButton({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'flex-1 min-h-[36px] rounded-md px-3 py-2 text-sm font-medium transition-colors',
        active
          ? 'bg-surface-700 text-white shadow-sm'
          : 'text-surface-400 hover:text-surface-200',
      )}
    >
      {children}
    </button>
  );
}

export const Component = ProgressScreen;
export default ProgressScreen;
