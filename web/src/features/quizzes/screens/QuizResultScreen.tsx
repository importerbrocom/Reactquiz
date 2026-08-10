import { useParams, Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { getQuizResult } from '@/api/endpoints/quiz.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { PageSpinner } from '@/components/ui/Spinner';

function QuizResultScreen() {
  const { attemptId } = useParams<{ attemptId: string }>();

  const { data: result, isLoading } = useQuery({
    queryKey: queryKeys.quiz.result(attemptId!),
    queryFn: () => getQuizResult(attemptId!),
    enabled: !!attemptId,
  });

  if (isLoading || !result) return <PageSpinner />;

  const minutes = Math.floor(result.time_spent_seconds / 60);
  const seconds = result.time_spent_seconds % 60;

  return (
    <div className="flex flex-1 flex-col items-center justify-center p-6">
      <div className="w-full max-w-md space-y-6 text-center">
        {/* Celebration */}
        <span className="text-5xl">🎉</span>
        <h1 className="text-2xl font-bold text-white">Day {result.day_number} Complete!</h1>

        {/* Score card */}
        <Card variant="elevated" className="space-y-4">
          <div className="text-4xl font-bold text-success-500">
            {result.score}/{result.required}
          </div>
          <p className="text-sm text-surface-400">Questions Mastered</p>

          <div className="flex justify-center gap-4 text-sm">
            <div>
              <span className="text-surface-400">Time: </span>
              <span className="font-medium text-surface-200">
                {minutes}m {seconds}s
              </span>
            </div>
          </div>
        </Card>

        {/* Streak */}
        <Card variant="outlined" className="flex items-center justify-between">
          <span className="text-sm text-surface-400">Streak</span>
          <Badge variant="success">{result.streak.current} days 🔥</Badge>
        </Card>

        {/* Next day info */}
        {result.next_day.unlocked ? (
          <Card variant="outlined">
            <p className="text-sm text-surface-300">
              Day {result.next_day.number} is now unlocked!
            </p>
          </Card>
        ) : result.next_day.unlocks_at ? (
          <Card variant="outlined">
            <p className="text-sm text-surface-300">
              Day {result.next_day.number} unlocks tomorrow
            </p>
          </Card>
        ) : null}

        {/* Final test unlocked */}
        {result.final_test.unlocked && (
          <Card variant="outlined" className="border-success-500/30 bg-success-500/5">
            <p className="font-medium text-success-500">
              Month-end test unlocked! 🏆
            </p>
          </Card>
        )}

        {/* Actions */}
        <div className="space-y-3">
          {result.next_day.unlocked && (
            <Link to={ROUTES.DASHBOARD}>
              <Button fullWidth>Continue to Dashboard</Button>
            </Link>
          )}
          <Link to={ROUTES.DASHBOARD}>
            <Button variant="ghost" fullWidth>
              Back to Dashboard
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
}

export const Component = QuizResultScreen;
export default QuizResultScreen;
