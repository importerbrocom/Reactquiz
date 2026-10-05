import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { getDashboard } from '@/api/endpoints/student-dashboard.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { ProgressBar } from '@/components/ui/ProgressBar';
import { PageSpinner } from '@/components/ui/Spinner';
import { NextAction } from '@/types/enums';
import type { Dashboard } from '@/types/models';

/**
 * Student dashboard — redesigned to match the ERO mockups:
 * gradient hero banner, quick-action tiles, stat row, and the primary
 * "today's quiz" call to action. Data wiring is unchanged from the API.
 */
function DashboardScreen() {
  const { data: dashboard, isLoading } = useQuery({
    queryKey: queryKeys.dashboard.all,
    queryFn: getDashboard,
  });

  if (isLoading || !dashboard) return <PageSpinner />;

  const { user, enrolment, today, streak, recent_scores } = dashboard;
  const dayPct = Math.round((enrolment.current_day / enrolment.days_per_level) * 100);
  const avgScore =
    recent_scores.length > 0
      ? Math.round(
          (recent_scores.reduce((a, s) => a + s.score, 0) / (recent_scores.length * 10)) * 100,
        )
      : 0;

  return (
    <div className="space-y-5">
      {/* ── Hero banner ───────────────────────────────────────────────── */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-700 via-primary-600 to-primary-900 p-5">
        <div className="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/10 blur-2xl" />
        <div className="relative">
          <p className="text-sm font-medium text-blue-100">Welcome back,</p>
          <h1 className="mt-0.5 text-2xl font-bold text-white">{user.name.split(' ')[0]}!</h1>
          <p className="mt-1 text-sm text-blue-100/90">
            Let&apos;s continue your {enrolment.course_name} preparation
          </p>
          <div className="mt-4">
            <div className="mb-1.5 flex items-center justify-between text-xs text-blue-100">
              <span>Your monthly goal</span>
              <span className="font-semibold">
                Day {enrolment.current_day}/{enrolment.days_per_level}
              </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-white/20">
              <div
                className="h-full rounded-full bg-white transition-all"
                style={{ width: `${dayPct}%` }}
              />
            </div>
          </div>
        </div>
      </div>

      {/* ── Quick actions ─────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 gap-3">
        <QuickAction
          to={ROUTES.QUIZ_DAY(enrolment.course_id, today.day_number)}
          icon="📝"
          title="Today's Quiz"
          subtitle={`${today.required_count} Questions`}
          color="from-primary-600 to-primary-700"
        />
        <QuickAction
          to={ROUTES.FINAL_TEST(enrolment.course_id)}
          icon="🏆"
          title="Monthly Test"
          subtitle="Full Length"
          color="from-accent-teal/80 to-accent-teal"
        />
        <QuickAction
          to={ROUTES.COURSE_DAYS(enrolment.course_id)}
          icon="📄"
          title="All Days"
          subtitle="Timeline"
          color="from-accent-purple/80 to-accent-purple"
        />
        <QuickAction
          to={ROUTES.PROGRESS}
          icon="📊"
          title="Progress"
          subtitle="Your stats"
          color="from-accent-amber/80 to-accent-amber"
        />
      </div>

      {/* ── Stat row ──────────────────────────────────────────────────── */}
      <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-4">
        <h3 className="mb-3 text-sm font-semibold text-white">Overall Progress</h3>
        <div className="grid grid-cols-4 gap-2">
          <Stat value={`${avgScore}%`} label="Accuracy" accent="text-primary-400" />
          <Stat value={streak.current} label="Streak" accent="text-accent-amber" />
          <Stat value={enrolment.current_day} label="Days Done" accent="text-accent-teal" />
          <Stat value={streak.longest} label="Best Streak" accent="text-accent-purple" />
        </div>
      </div>

      {/* ── Primary action ────────────────────────────────────────────── */}
      <PrimaryActionCard dashboard={dashboard} />

      {/* ── Level progress ────────────────────────────────────────────── */}
      <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-5">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="font-medium text-white">Level {enrolment.current_level} Progress</h3>
          <span className="text-sm text-surface-400">
            Day {enrolment.current_day}/{enrolment.days_per_level}
          </span>
        </div>
        <ProgressBar
          value={enrolment.current_day}
          max={enrolment.days_per_level}
          variant="primary"
          size="md"
        />
        {dashboard.final_test.eligible && (
          <p className="mt-2 text-xs text-success-500">
            Month-end test unlocked! All {dashboard.final_test.required_days} days completed.
          </p>
        )}
      </div>

      {/* ── Recent scores ─────────────────────────────────────────────── */}
      {recent_scores.length > 0 && (
        <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-5">
          <h3 className="mb-3 font-medium text-white">Recent Days</h3>
          <div className="flex gap-2 overflow-x-auto pb-1">
            {recent_scores.map((score) => (
              <div
                key={score.day_number}
                className="flex flex-shrink-0 flex-col items-center gap-1 rounded-lg bg-surface-800 px-3 py-2"
              >
                <span className="text-xs text-surface-400">Day {score.day_number}</span>
                <span className="text-sm font-bold text-success-500">{score.score}/10</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function QuickAction({
  to,
  icon,
  title,
  subtitle,
  color,
}: {
  to: string;
  icon: string;
  title: string;
  subtitle: string;
  color: string;
}) {
  return (
    <Link
      to={to}
      className={`relative flex flex-col justify-between overflow-hidden rounded-2xl bg-gradient-to-br ${color} p-4 transition-transform active:scale-[0.98]`}
    >
      <span className="text-2xl">{icon}</span>
      <div className="mt-3">
        <p className="font-semibold text-white">{title}</p>
        <p className="text-xs text-white/80">{subtitle}</p>
      </div>
    </Link>
  );
}

function Stat({
  value,
  label,
  accent,
}: {
  value: string | number;
  label: string;
  accent: string;
}) {
  return (
    <div className="flex flex-col items-center rounded-xl bg-surface-800 px-1 py-3 text-center">
      <span className={`text-xl font-bold ${accent}`}>{value}</span>
      <span className="mt-0.5 text-[11px] leading-tight text-surface-400">{label}</span>
    </div>
  );
}

function PrimaryActionCard({ dashboard }: { dashboard: Dashboard }) {
  const { next_action, today, enrolment } = dashboard;
  const courseId = enrolment.course_id;

  const actions: Record<
    NextAction,
    { label: string; description: string; to: string; locked?: boolean }
  > = {
    [NextAction.StartDay]: {
      label: 'Start Day ' + today.day_number,
      description: `${today.required_count} questions to master today`,
      to: ROUTES.QUIZ_DAY(courseId, today.day_number),
    },
    [NextAction.ResumeDay]: {
      label: 'Resume Day ' + today.day_number,
      description: `${today.mastered_count}/${today.required_count} mastered — keep going!`,
      to: ROUTES.QUIZ_DAY(courseId, today.day_number),
    },
    [NextAction.Locked]: {
      label: 'Day Locked',
      description: 'Complete the previous day to unlock',
      to: ROUTES.COURSE_DAYS(courseId),
      locked: true,
    },
    [NextAction.TakeLevelTest]: {
      label: 'Take Month-End Test',
      description: 'All 30 days complete — sit the level test',
      to: ROUTES.FINAL_TEST(courseId),
    },
    [NextAction.RetakeLevelTest]: {
      label: 'Retake Level Test',
      description: 'Try the month-end test again',
      to: ROUTES.FINAL_TEST(courseId),
    },
    [NextAction.AdvanceLevel]: {
      label: 'Advance to Level ' + (enrolment.current_level + 1),
      description: 'You passed! Move to the next level',
      to: ROUTES.DASHBOARD,
    },
    [NextAction.ProgrammeComplete]: {
      label: 'Programme Complete!',
      description: 'Congratulations — you finished all levels',
      to: ROUTES.PROGRESS,
    },
  };

  const action = actions[next_action];

  return (
    <Link
      to={action.to}
      className={`block overflow-hidden rounded-2xl p-5 transition-transform active:scale-[0.99] ${
        action.locked
          ? 'border border-surface-700 bg-surface-900/50'
          : 'bg-gradient-to-r from-primary-600 to-primary-700 shadow-lg shadow-primary-900/40'
      }`}
    >
      <p className={`text-xs font-medium ${action.locked ? 'text-surface-400' : 'text-blue-100'}`}>
        {action.locked ? 'Locked' : "Today's focus"}
      </p>
      <p className={`mt-1 text-lg font-bold ${action.locked ? 'text-surface-300' : 'text-white'}`}>
        {action.label}
      </p>
      <p className={`mt-0.5 text-sm ${action.locked ? 'text-surface-500' : 'text-blue-100/90'}`}>
        {action.description}
      </p>
      <div className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-white">
        {action.locked ? (
          <span className="text-surface-400">View Days →</span>
        ) : (
          <>
            Start now <span>→</span>
          </>
        )}
      </div>
    </Link>
  );
}

export const Component = DashboardScreen;
export default DashboardScreen;
