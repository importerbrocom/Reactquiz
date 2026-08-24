import { useState } from 'react';
import { useParams, Link } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { getResult } from '@/api/endpoints/final-test.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { PageSpinner } from '@/components/ui/Spinner';
import { LevelAdvancementModal } from '@/features/progress/components/LevelAdvancementModal';

function FinalTestResultScreen() {
  const { attemptId } = useParams<{ attemptId: string }>();
  const [showAdvanceModal, setShowAdvanceModal] = useState(false);

  const { data: result, isLoading } = useQuery({
    queryKey: queryKeys.finalTest.result(attemptId!),
    queryFn: () => getResult(attemptId!),
    enabled: !!attemptId,
    refetchInterval: (query) => {
      // Poll until result is ready (grading may be async)
      return query.state.data ? false : 3000;
    },
  });

  if (isLoading || !result) return <PageSpinner />;

  const minutes = Math.floor(result.time_spent_seconds / 60);

  return (
    <div className="flex flex-1 flex-col overflow-y-auto p-6">
      <div className="mx-auto w-full max-w-lg space-y-6">
        {/* Header */}
        <div className="text-center">
          <span className="text-5xl">{result.passed ? '🏆' : '📊'}</span>
          <h1 className="mt-3 text-2xl font-bold text-white">
            {result.passed ? 'Congratulations!' : 'Test Complete'}
          </h1>
          <Badge variant={result.passed ? 'success' : 'neutral'} className="mt-2">
            {result.passed ? 'PASSED' : 'COMPLETED'}
          </Badge>
        </div>

        {/* Score card */}
        <Card variant="elevated" className="text-center">
          <div className="text-5xl font-bold text-primary-400">{result.percentage}%</div>
          <p className="mt-1 text-sm text-surface-400">
            {result.score}/{result.total} correct
          </p>
          <p className="mt-1 text-xs text-surface-500">{minutes} minutes</p>
        </Card>

        {/* Topic breakdown */}
        {result.topic_breakdown.length > 0 && (
          <Card variant="outlined">
            <h3 className="mb-3 font-medium text-white">Topic Performance</h3>
            <div className="space-y-3">
              {result.topic_breakdown.map((topic) => (
                <div key={topic.topic}>
                  <div className="mb-1 flex items-center justify-between text-xs">
                    <span className="text-surface-300">{topic.topic}</span>
                    <span className="text-surface-400">
                      {topic.correct}/{topic.total} ({topic.percentage}%)
                    </span>
                  </div>
                  <ProgressBar
                    value={topic.percentage}
                    max={100}
                    variant={topic.percentage >= 70 ? 'success' : topic.percentage >= 50 ? 'warning' : 'danger'}
                    size="sm"
                  />
                </div>
              ))}
            </div>
          </Card>
        )}

        {/* Wrong answers preview */}
        {result.wrong_answers.length > 0 && (
          <Card variant="outlined">
            <h3 className="mb-3 font-medium text-white">
              Review Mistakes ({result.wrong_answers.length})
            </h3>
            <div className="max-h-60 space-y-2 overflow-y-auto">
              {result.wrong_answers.slice(0, 10).map((wrong) => (
                <div key={wrong.question_id} className="rounded-lg bg-surface-800 p-3">
                  <p className="text-xs text-surface-200 line-clamp-2">{wrong.question_text}</p>
                  <p className="mt-1 text-xs text-success-500">
                    Correct: {wrong.correct_answer_text}
                  </p>
                </div>
              ))}
              {result.wrong_answers.length > 10 && (
                <p className="text-xs text-surface-500">
                  +{result.wrong_answers.length - 10} more (view in Mistakes)
                </p>
              )}
            </div>
          </Card>
        )}

        {/* Actions */}
        <div className="space-y-3">
          {result.passed && (
            <Button fullWidth onClick={() => setShowAdvanceModal(true)}>
              🚀 Advance to Next Level
            </Button>
          )}
          <Link to={ROUTES.DASHBOARD}>
            <Button variant={result.passed ? 'secondary' : 'primary'} fullWidth>
              Back to Dashboard
            </Button>
          </Link>
          <Link to={ROUTES.MISTAKES}>
            <Button variant="ghost" fullWidth>Review All Mistakes</Button>
          </Link>
        </div>
      </div>

      {/* Level advancement celebration */}
      <LevelAdvancementModal
        open={showAdvanceModal}
        onClose={() => setShowAdvanceModal(false)}
      />
    </div>
  );
}

export const Component = FinalTestResultScreen;
export default FinalTestResultScreen;
