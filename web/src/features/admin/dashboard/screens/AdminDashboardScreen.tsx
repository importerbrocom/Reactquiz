import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import {
  getDashboardSummary,
  getDifficultQuestions,
} from '@/api/endpoints/admin-dashboard.api';
import { Card } from '@/components/ui/Card';
import { PageSpinner } from '@/components/ui/Spinner';

function AdminDashboardScreen() {
  const { data: summary, isLoading } = useQuery({
    queryKey: queryKeys.admin.dashboard.summary(),
    queryFn: getDashboardSummary,
  });

  const { data: difficult } = useQuery({
    queryKey: queryKeys.admin.dashboard.difficult(),
    queryFn: getDifficultQuestions,
  });

  if (isLoading || !summary) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-white">Dashboard</h1>

      {/* Summary cards */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Students" value={summary.students_total} />
        <StatCard label="Active Today" value={summary.students_active_today} />
        <StatCard label="Categories" value={summary.exam_categories} />
        <StatCard label="Courses" value={summary.courses} />
        <StatCard label="Questions" value={summary.questions} />
        <StatCard label="Quizzes Today" value={summary.quizzes_completed_today} />
        <StatCard label="Avg Score" value={`${summary.avg_score_percent}%`} />
        <StatCard label="Completion" value={`${summary.completion_rate_percent}%`} />
      </div>

      {/* Difficult questions */}
      {difficult && difficult.length > 0 && (
        <Card variant="outlined">
          <h3 className="mb-3 font-medium text-white">Most Difficult Questions</h3>
          <div className="space-y-2">
            {difficult.slice(0, 5).map((q) => (
              <div
                key={q.id}
                className="flex items-center justify-between rounded-lg bg-surface-800 px-3 py-2"
              >
                <span className="text-sm text-surface-200 line-clamp-1 flex-1">
                  {q.text}
                </span>
                <span className="ml-3 flex-shrink-0 text-xs text-danger-500">
                  {q.wrong_rate_percent}% wrong
                </span>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}

function StatCard({ label, value }: { label: string; value: string | number }) {
  return (
    <Card variant="outlined" className="text-center">
      <p className="text-2xl font-bold text-white">{value}</p>
      <p className="mt-0.5 text-xs text-surface-400">{label}</p>
    </Card>
  );
}

export const Component = AdminDashboardScreen;
export default AdminDashboardScreen;
