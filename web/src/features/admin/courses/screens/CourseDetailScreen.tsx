import { useParams } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getCourse, getQuizConfig, getCourseReadiness, generateDailyQuizzes } from '@/api/endpoints/admin-courses.api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { StatusBadge } from '../../components/StatusBadge';
import { PageSpinner } from '@/components/ui/Spinner';

function CourseDetailScreen() {
  const { courseId } = useParams<{ courseId: string }>();
  const queryClient = useQueryClient();

  const { data: course, isLoading } = useQuery({
    queryKey: queryKeys.admin.courses.detail(courseId!),
    queryFn: () => getCourse(courseId!),
    enabled: !!courseId,
  });

  const { data: config } = useQuery({
    queryKey: queryKeys.admin.courses.config(courseId!),
    queryFn: () => getQuizConfig(courseId!),
    enabled: !!courseId,
  });

  const { data: readiness } = useQuery({
    queryKey: queryKeys.admin.courses.readiness(courseId!),
    queryFn: () => getCourseReadiness(courseId!),
    enabled: !!courseId,
  });

  const generateMutation = useMutation({
    mutationFn: () => generateDailyQuizzes(courseId!),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.admin.courses.detail(courseId!) });
    },
  });

  if (isLoading || !course) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">{course.name}</h1>
          <p className="mt-1 text-sm text-surface-400">{course.description}</p>
        </div>
        <StatusBadge status={course.status} />
      </div>

      {/* Stats */}
      <div className="grid grid-cols-4 gap-4">
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{course.questions_count}</p>
          <p className="text-xs text-surface-400">Questions</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{course.students_enrolled}</p>
          <p className="text-xs text-surface-400">Students</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{course.levels_count}</p>
          <p className="text-xs text-surface-400">Levels</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{course.days_per_level}</p>
          <p className="text-xs text-surface-400">Days/Level</p>
        </Card>
      </div>

      {/* Quiz config */}
      {config && (
        <Card variant="outlined">
          <h3 className="mb-3 font-medium text-white">Quiz Configuration</h3>
          <div className="grid grid-cols-2 gap-3 text-sm">
            <ConfigRow label="Questions/day" value={config.questions_per_day} />
            <ConfigRow label="Final test questions" value={config.final_test_questions} />
            <ConfigRow label="Pass %" value={`${config.pass_percent}%`} />
            <ConfigRow label="Timer" value={config.timer_minutes ? `${config.timer_minutes} min` : 'None'} />
            <ConfigRow label="Unlock mode" value={config.unlock_mode} />
            <ConfigRow label="Retry mode" value={config.retry_mode} />
            <ConfigRow label="Shuffle options" value={config.shuffle_options ? 'Yes' : 'No'} />
            <ConfigRow label="Pass to advance" value={config.require_test_pass_to_advance ? 'Yes' : 'No'} />
          </div>
        </Card>
      )}

      {/* Readiness */}
      {readiness && (
        <Card variant="outlined">
          <div className="flex items-center justify-between">
            <h3 className="font-medium text-white">Readiness Check</h3>
            <Badge variant={readiness.ready ? 'success' : 'warning'}>
              {readiness.ready ? 'Ready' : 'Issues found'}
            </Badge>
          </div>
          {readiness.issues.length > 0 && (
            <ul className="mt-3 space-y-1">
              {readiness.issues.map((issue, i) => (
                <li key={i} className={`text-xs ${issue.severity === 'error' ? 'text-danger-500' : 'text-warning-500'}`}>
                  {issue.message}
                </li>
              ))}
            </ul>
          )}
        </Card>
      )}

      {/* Actions */}
      <div className="flex gap-3">
        <Button
          onClick={() => generateMutation.mutate()}
          loading={generateMutation.isPending}
        >
          Generate Daily Quizzes
        </Button>
        <Button variant="secondary">Edit Course</Button>
      </div>
    </div>
  );
}

function ConfigRow({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="flex items-center justify-between border-b border-surface-800 pb-2">
      <span className="text-surface-400">{label}</span>
      <span className="font-medium text-surface-200">{String(value)}</span>
    </div>
  );
}

export const Component = CourseDetailScreen;
export default CourseDetailScreen;
