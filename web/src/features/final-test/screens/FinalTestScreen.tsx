import { useParams, useNavigate } from 'react-router';
import { useQuery, useMutation } from '@tanstack/react-query';
import { getEligibility, startOrResumeAttempt } from '@/api/endpoints/final-test.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { PageSpinner } from '@/components/ui/Spinner';

/**
 * Final test eligibility and start screen.
 * Shows requirements, missing days, and start button.
 */
function FinalTestScreen() {
  const { courseId } = useParams<{ courseId: string }>();
  const navigate = useNavigate();

  const { data: eligibility, isLoading } = useQuery({
    queryKey: queryKeys.finalTest.eligibility(courseId!),
    queryFn: () => getEligibility(courseId!),
    enabled: !!courseId,
  });

  const startMutation = useMutation({
    mutationFn: () => startOrResumeAttempt(courseId!),
    onSuccess: (attempt) => {
      navigate(ROUTES.FINAL_TEST_SESSION(attempt.attempt_uuid));
    },
  });

  if (isLoading || !eligibility) return <PageSpinner />;

  return (
    <div className="flex flex-1 flex-col items-center justify-center p-6">
      <div className="w-full max-w-md space-y-6">
        <div className="text-center">
          <span className="text-4xl">📋</span>
          <h1 className="mt-3 text-2xl font-bold text-white">Month-End Test</h1>
          <p className="mt-1 text-sm text-surface-400">
            {eligibility.eligible
              ? 'You are eligible to take the level test'
              : 'Complete all days to unlock the test'}
          </p>
        </div>

        <Card variant="outlined" className="space-y-3">
          <div className="flex items-center justify-between">
            <span className="text-sm text-surface-400">Days completed</span>
            <span className="text-sm font-medium text-white">
              {eligibility.completed_days}/{eligibility.required_days}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-surface-400">Attempts used</span>
            <span className="text-sm font-medium text-white">
              {eligibility.attempts_used}/{eligibility.attempts_allowed}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <span className="text-sm text-surface-400">Status</span>
            <Badge variant={eligibility.eligible ? 'success' : 'warning'}>
              {eligibility.eligible ? 'Eligible' : 'Locked'}
            </Badge>
          </div>
        </Card>

        {!eligibility.eligible && eligibility.missing_days.length > 0 && (
          <Card variant="outlined">
            <p className="mb-2 text-sm font-medium text-surface-300">Missing days:</p>
            <div className="flex flex-wrap gap-1.5">
              {eligibility.missing_days.slice(0, 15).map((day) => (
                <span
                  key={day}
                  className="rounded bg-surface-800 px-2 py-0.5 text-xs text-surface-400"
                >
                  Day {day}
                </span>
              ))}
              {eligibility.missing_days.length > 15 && (
                <span className="text-xs text-surface-500">
                  +{eligibility.missing_days.length - 15} more
                </span>
              )}
            </div>
          </Card>
        )}

        {eligibility.eligible && (
          <div className="space-y-3">
            <Card variant="outlined" className="border-warning-500/30 bg-warning-500/5">
              <p className="text-xs text-warning-500">
                ⚠️ This test has 300 questions and is timed. Make sure you have a stable connection.
                Answers sync automatically but submission requires connectivity.
              </p>
            </Card>
            <Button
              fullWidth
              loading={startMutation.isPending}
              onClick={() => startMutation.mutate()}
            >
              Start Test
            </Button>
          </div>
        )}

        {!eligibility.eligible && (
          <Button variant="secondary" fullWidth onClick={() => navigate(-1)}>
            Go Back
          </Button>
        )}
      </div>
    </div>
  );
}

export const Component = FinalTestScreen;
export default FinalTestScreen;
