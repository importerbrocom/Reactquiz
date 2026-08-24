import { motion } from 'framer-motion';
import { Card } from '@/components/ui/Card';
import type { StreakInfo } from '@/types/models';

interface StudyStreakCardProps {
  streak: StreakInfo;
}

/**
 * Animated streak display for the dashboard.
 * Shows current streak with a pulsing fire emoji, motivational message
 * based on streak length, and the longest streak for comparison.
 */
export function StudyStreakCard({ streak }: StudyStreakCardProps) {
  const { current, longest } = streak;
  const message = getMotivationalMessage(current);
  const tier = getStreakTier(current);

  return (
    <Card variant="outlined" className="relative overflow-hidden">
      {/* Background glow for high streaks */}
      {current >= 7 && (
        <div
          className="pointer-events-none absolute inset-0 opacity-20"
          style={{
            background: `radial-gradient(ellipse at 30% 50%, ${tier.glowColor} 0%, transparent 60%)`,
          }}
          aria-hidden
        />
      )}

      <div className="relative flex items-center gap-4">
        {/* Animated fire */}
        <div className="flex flex-col items-center">
          <motion.div
            className="text-4xl"
            animate={
              current > 0
                ? {
                    scale: [1, 1.15, 1],
                    rotate: [0, -3, 3, 0],
                  }
                : {}
            }
            transition={{
              duration: 2,
              repeat: Infinity,
              repeatType: 'loop',
              ease: 'easeInOut',
            }}
          >
            {tier.emoji}
          </motion.div>

          {/* Streak number with entrance animation */}
          <motion.span
            className="mt-1 text-2xl font-bold text-white"
            initial={{ scale: 0.8, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ type: 'spring', damping: 12, stiffness: 200 }}
            key={current} // Re-animates when streak changes
          >
            {current}
          </motion.span>
          <span className="text-xs text-surface-400">
            {current === 1 ? 'day' : 'days'}
          </span>
        </div>

        {/* Message and stats */}
        <div className="flex-1">
          <motion.p
            className="text-sm font-medium text-white"
            initial={{ opacity: 0, x: 10 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: 0.2 }}
          >
            {message}
          </motion.p>

          {/* Streak milestones / longest */}
          <div className="mt-2 flex items-center gap-3">
            <div className="flex items-center gap-1.5">
              <span className="text-xs text-surface-500">Best:</span>
              <span className="text-xs font-medium text-surface-300">
                {longest} {longest === 1 ? 'day' : 'days'}
              </span>
            </div>
            {current > 0 && current === longest && (
              <motion.span
                className="rounded-full bg-warning-500/15 px-2 py-0.5 text-[10px] font-medium text-warning-500"
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                transition={{ type: 'spring', delay: 0.4 }}
              >
                Personal best! 🏅
              </motion.span>
            )}
          </div>

          {/* Next milestone indicator */}
          {current > 0 && (
            <StreakMilestoneHint current={current} />
          )}
        </div>
      </div>
    </Card>
  );
}

/**
 * Shows a progress hint toward the next milestone (7, 14, 30, 60, 100 days).
 */
function StreakMilestoneHint({ current }: { current: number }) {
  const milestones = [7, 14, 30, 60, 100];
  const next = milestones.find((m) => m > current);

  if (!next) return null;

  const progress = (current / next) * 100;

  return (
    <div className="mt-2">
      <div className="flex items-center justify-between text-[10px] text-surface-500">
        <span>Next: {next}-day streak</span>
        <span>{next - current} to go</span>
      </div>
      <div className="mt-0.5 h-1 w-full overflow-hidden rounded-full bg-surface-800">
        <motion.div
          className="h-full rounded-full bg-gradient-to-r from-warning-500/60 to-warning-500"
          initial={{ width: 0 }}
          animate={{ width: `${progress}%` }}
          transition={{ duration: 0.8, ease: 'easeOut', delay: 0.3 }}
        />
      </div>
    </div>
  );
}

// ─── Helpers ────────────────────────────────────────────────────────────────

interface StreakTier {
  emoji: string;
  glowColor: string;
}

function getStreakTier(days: number): StreakTier {
  if (days >= 100) return { emoji: '💎', glowColor: '#818cf8' };
  if (days >= 60) return { emoji: '⚡', glowColor: '#f59e0b' };
  if (days >= 30) return { emoji: '🔥', glowColor: '#ef4444' };
  if (days >= 14) return { emoji: '🔥', glowColor: '#f97316' };
  if (days >= 7) return { emoji: '🔥', glowColor: '#f59e0b' };
  if (days >= 1) return { emoji: '🔥', glowColor: 'transparent' };
  return { emoji: '❄️', glowColor: 'transparent' };
}

function getMotivationalMessage(days: number): string {
  if (days === 0) return "Start today to build your streak!";
  if (days === 1) return "Great start! Keep it going tomorrow.";
  if (days === 2) return "Two days strong! Momentum is building.";
  if (days === 3) return "Three in a row — a habit is forming!";
  if (days < 7) return `${days} days! You're building consistency.`;
  if (days === 7) return "One full week! You're committed. 💪";
  if (days < 14) return "Over a week! This is becoming routine.";
  if (days === 14) return "Two weeks straight — impressive discipline!";
  if (days < 30) return "Keep pushing — 30-day milestone is close!";
  if (days === 30) return "30 DAYS! A full month of daily practice! 🏆";
  if (days < 60) return "Over a month! You're in the elite now.";
  if (days === 60) return "60 days — two months of pure dedication!";
  if (days < 100) return "Incredible consistency. The 100-day club awaits!";
  if (days === 100) return "💯 ONE HUNDRED DAYS! Absolute legend!";
  return `${days} days — you're unstoppable!`;
}
