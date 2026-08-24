import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { getDashboard } from '@/api/endpoints/student-dashboard.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { Badge } from '@/components/ui/Badge';
import { PageSpinner } from '@/components/ui/Spinner';
import { LevelAdvancementModal } from '@/features/progress/components/LevelAdvancementModal';
import { StudyStreakCard } from '../components/StudyStreakCard';
import { NextAction } from '@/types/enums';
import type { Dashboard } from '@/types/models';

function DashboardScreen() {
  const [showAdvanceModal, setShowAdvanceModal] = useState(false);

  const { data: dashboard, isLoading } = useQuery({
    queryKey: queryKeys.dashboard.all,
    queryFn: getDashboard,
  });

  if (isLoading || !dashboard) return <PageSpinner />;

  return (
    <div className="space-y-6">
      {/* Greeting */}
      <div>
        <h1 className="text-2xl font-bold text-white">
          Hi, {dashboard.user.name.split(' ')[0]}!
        </h1>
        <p className="mt-0.5 text-sm text-surface-400">
          {dashboard.enrolment.course_name} &middot; Level {dashboard.enrolment.current_level}
        </p>
      </div>

      {/* Primary action card */}
      <PrimaryActionCard dashboard={dashboard} onAdvance={() => setShowAdvanceModal(true)} />

      {/* Streak */}
      <StudyStreakCard streak={dashboard.streak} />

      {/* Progress overview */}
      <Card variant="outlined">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="font-medium text-white">Level Progress</h3>
          <span className="text-sm text-surface-400">
            Day {dashboard.enrolment.current_day}/{dashboard.enrolment.days_per_level}
          </span>
        </div>
        <ProgressBar
          value={dashboard.enrolment.current_day}
          max={dashboard.enrolment.days_per_level}
          variant="primary"
          size="md"
        />
        {dashboard.final_test.eligible && (
          <p className="mt-2 text-xs text-success-500">
            Month-end test unlocked! All {dashboard.final_test.required_days} days completed.
          </p>
        )}
      </Card>

      {/* Recent scores */}
      {dashboard.recent_scores.length > 0 && (
        <Card variant="outlined">
          <h3 className="mb-3 font-medium text-white">Recent Days</h3>
          <div className="flex gap-2 overflow-x-auto pb-1">
            {dashboard.recent_scores.map((score) => (
              <div
                key={score.day_number}
                className="flex flex-shrink-0 flex-col items-center gap-1 rounded-lg bg-surface-800 px-3 py-2"
              >
                <span className="text-xs text-surface-400">Day {score.day_number}</span>
                <span className="text-sm font-bold text-success-500">{score.score}/10</span>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Days timeline link */}
      <Link
        to={ROUTES.COURSE_DAYS(dashboard.enrolment.course_id)}
        className="block text-center text-sm font-medium text-primary-400 hover:text-primary-300"
      >
        View all days →
      </Link>

      {/* Level advancement modal */}
      <LevelAdvancementModal
        open={showAdvanceModal}
        onClose={() => setShowAdvanceModal(false)}
      />
    </div>
  );
}

function PrimaryActionCard({ dashboard, onAdvance }: { dashboard: Dashboard; onAdvance: () => void }) {
  const { next_action, today, enrolment } = dashboard;
  const courseId = enrolment.course_id;

  const actions: Record<NextAction, { label: string; description: string; to: string; variant: 'primary' | 'success' }> = {
    [NextAction.StartDay]: {
      label: 'Start Day ' + today.day_number,
      description: '10 questions to master today',
      to: ROUTES.QUIZ_DAY(courseId, today.day_number),
      variant: 'primary',
    },
    [NextAction.ResumeDay]: {
      label: 'Resume Day ' + today.day_number,
      description: `${today.mastered_count}/${today.required_count} mastered — keep going!`,
      to: ROUTES.QUIZ_DAY(courseId, today.day_number),
      variant: 'primary',
    },
    [NextAction.Locked]: {
      label: 'Day Locked',
      description: 'Complete the previous day to unlock',
      to: ROUTES.COURSE_DAYS(courseId),
      variant: 'primary',
    },
    [NextAction.TakeLevelTest]: {
      label: 'Take Month-End Test',
      description: 'All 30 days complete — sit the level test',
      to: ROUTES.FINAL_TEST(courseId),
      variant: 'success',
    },
    [NextAction.RetakeLevelTest]: {
      label: 'Retake Level Test',
      description: 'Try the month-end test again',
      to: ROUTES.FINAL_TEST(courseId),
      variant: 'primary',
    },
    [NextAction.AdvanceLevel]: {
      label: 'Advance to Level ' + (enrolment.current_level + 1),
      description: 'You passed! Move to the next level',
      to: ROUTES.DASHBOARD,
      variant: 'success',
    },
    [NextAction.ProgrammeComplete]: {
      label: 'Programme Complete!',
      description: 'Congratulations — you finished all levels',
      to: ROUTES.PROGRESS,
      variant: 'success',
    },
  };

  const action = actions[next_action];

  return (
    <Card variant="elevated" className="space-y-3">
      <div className="flex items-center gap-2">
        <Badge variant={action.variant === 'success' ? 'success' : 'primary'}>
          {next_action === NextAction.Locked ? 'Locked' : 'Ready'}
        </Badge>
      </div>
      <p className="text-lg font-semibold text-white">{action.label}</p>
      <p className="text-sm text-surface-400">{action.description}</p>
      {next_action === NextAction.AdvanceLevel ? (
        <Button fullWidth variant="primary" onClick={onAdvance}>
          🚀 Advance Now
        </Button>
      ) : (
        <Link to={action.to}>
          <Button fullWidth variant={next_action === NextAction.Locked ? 'secondary' : 'primary'}>
            {next_action === NextAction.Locked ? 'View Days' : 'Go'}
          </Button>
        </Link>
      )}
    </Card>
  );
}

export const Component = DashboardScreen;
export default DashboardScreen;
