import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { motion, AnimatePresence } from 'framer-motion';
import { getNextLevelInfo, advanceLevel } from '@/api/endpoints/levels.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';

interface LevelAdvancementModalProps {
  open: boolean;
  onClose: () => void;
}

/**
 * Full-screen celebration overlay for level advancement.
 * Shows when a student passes the month-end test and can advance.
 * Uses framer-motion for confetti-like entrance animation.
 */
export function LevelAdvancementModal({ open, onClose }: LevelAdvancementModalProps) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const { data: nextLevel } = useQuery({
    queryKey: queryKeys.levels.next(),
    queryFn: getNextLevelInfo,
    enabled: open,
  });

  const advanceMutation = useMutation({
    mutationFn: advanceLevel,
    onSuccess: () => {
      // Invalidate everything that changes when level advances
      queryClient.invalidateQueries({ queryKey: queryKeys.dashboard.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.days.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.progress.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.levels.all });
      onClose();
      navigate(ROUTES.DASHBOARD, { replace: true });
    },
  });

  if (!open) return null;

  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-50 flex items-center justify-center bg-surface-950/95 backdrop-blur-sm"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.3 }}
        >
          <motion.div
            className="mx-4 w-full max-w-md"
            initial={{ scale: 0.8, y: 30, opacity: 0 }}
            animate={{ scale: 1, y: 0, opacity: 1 }}
            exit={{ scale: 0.9, y: 20, opacity: 0 }}
            transition={{ type: 'spring', damping: 20, stiffness: 300, delay: 0.1 }}
          >
            {/* Celebration header */}
            <div className="mb-6 text-center">
              <motion.div
                initial={{ scale: 0, rotate: -20 }}
                animate={{ scale: 1, rotate: 0 }}
                transition={{ type: 'spring', damping: 10, stiffness: 200, delay: 0.3 }}
              >
                <span className="text-7xl">🎉</span>
              </motion.div>

              <motion.h1
                className="mt-4 text-3xl font-bold text-white"
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.5 }}
              >
                Level Complete!
              </motion.h1>

              <motion.p
                className="mt-2 text-surface-400"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.6 }}
              >
                You&apos;ve mastered all 30 days and passed the test
              </motion.p>
            </div>

            {/* Level info */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.7 }}
            >
              <Card variant="elevated" className="space-y-4">
                {nextLevel?.can_advance && nextLevel.next_level && (
                  <>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-success-500/15">
                          <span className="text-xl">✅</span>
                        </div>
                        <div>
                          <p className="text-sm text-surface-400">Current level</p>
                          <p className="font-bold text-white">Completed</p>
                        </div>
                      </div>
                      <ArrowIcon />
                      <div className="flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-500/15">
                          <span className="text-xl font-bold text-primary-400">
                            {nextLevel.next_level}
                          </span>
                        </div>
                        <div>
                          <p className="text-sm text-surface-400">Next level</p>
                          <p className="font-bold text-white">
                            {nextLevel.next_cycle && nextLevel.next_cycle > 1
                              ? `Cycle ${nextLevel.next_cycle}`
                              : `Level ${nextLevel.next_level}`}
                          </p>
                        </div>
                      </div>
                    </div>

                    {/* What to expect */}
                    <div className="rounded-lg bg-surface-800/50 p-3">
                      <p className="text-xs text-surface-400">What&apos;s next:</p>
                      <ul className="mt-1.5 space-y-1 text-xs text-surface-300">
                        <li className="flex items-start gap-2">
                          <span className="text-primary-400">•</span>
                          30 new daily quizzes with fresh questions
                        </li>
                        <li className="flex items-start gap-2">
                          <span className="text-primary-400">•</span>
                          New question shuffle for a different learning experience
                        </li>
                        <li className="flex items-start gap-2">
                          <span className="text-primary-400">•</span>
                          Another month-end test to prove mastery
                        </li>
                      </ul>
                    </div>
                  </>
                )}

                {nextLevel && !nextLevel.can_advance && (
                  <div className="text-center">
                    <p className="text-sm text-surface-300">
                      {nextLevel.reason ?? 'You cannot advance yet.'}
                    </p>
                  </div>
                )}

                {/* Actions */}
                <div className="space-y-3 pt-2">
                  {nextLevel?.can_advance && (
                    <Button
                      fullWidth
                      loading={advanceMutation.isPending}
                      onClick={() => advanceMutation.mutate()}
                    >
                      🚀 Advance to Level {nextLevel.next_level}
                    </Button>
                  )}
                  <Button variant="ghost" fullWidth onClick={onClose}>
                    Stay &amp; Review
                  </Button>
                </div>
              </Card>
            </motion.div>

            {/* Floating particles (pure decoration) */}
            <FloatingParticles />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

function ArrowIcon() {
  return (
    <svg
      className="h-5 w-5 text-surface-500"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth={2}
    >
      <path strokeLinecap="round" strokeLinejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
    </svg>
  );
}

/**
 * Lightweight celebratory floating dots — no canvas, no physics.
 * Each particle animates up and fades out via CSS/framer-motion.
 */
function FloatingParticles() {
  const particles = Array.from({ length: 12 }, (_, i) => ({
    id: i,
    x: Math.random() * 100,
    delay: Math.random() * 0.8,
    duration: 2 + Math.random() * 2,
    size: 4 + Math.random() * 6,
    color: ['#6366f1', '#22c55e', '#f59e0b', '#818cf8'][i % 4],
  }));

  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
      {particles.map((p) => (
        <motion.div
          key={p.id}
          className="absolute rounded-full"
          style={{
            left: `${p.x}%`,
            bottom: 0,
            width: p.size,
            height: p.size,
            backgroundColor: p.color,
          }}
          initial={{ y: 0, opacity: 0.8, scale: 0 }}
          animate={{ y: -400, opacity: 0, scale: 1 }}
          transition={{
            duration: p.duration,
            delay: p.delay,
            ease: 'easeOut',
          }}
        />
      ))}
    </div>
  );
}
