import { useCallback } from 'react';
import { motion } from 'framer-motion';
import { usePwaStore } from '@/store/pwa.store';
import { Button } from '@/components/ui/Button';

/**
 * Banner shown when a new service worker is waiting to activate.
 * Clicking "Update" tells the waiting SW to skipWaiting, which triggers
 * the controllerchange event in register-sw.ts and reloads the page.
 */
export function UpdateAvailableBanner() {
  const { swRegistration, contractMismatch } = usePwaStore();

  const handleUpdate = useCallback(() => {
    if (swRegistration?.waiting) {
      swRegistration.waiting.postMessage({ type: 'SKIP_WAITING' });
    } else {
      // Fallback: hard reload to get new assets
      window.location.reload();
    }
  }, [swRegistration]);

  return (
    <motion.div
      className="border-b border-success-500/20 bg-success-500/5 px-4 py-3"
      initial={{ height: 0, opacity: 0 }}
      animate={{ height: 'auto', opacity: 1 }}
      transition={{ duration: 0.3, ease: 'easeInOut' }}
      role="alert"
      aria-live="polite"
    >
      <div className="mx-auto flex max-w-3xl items-center gap-3">
        <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-success-500/15">
          <span className="text-lg">🔄</span>
        </div>

        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-white">
            {contractMismatch ? 'Important update available' : 'Update available'}
          </p>
          <p className="text-xs text-surface-400">
            {contractMismatch
              ? 'A new version is required for compatibility'
              : 'Refresh to get the latest improvements'}
          </p>
        </div>

        <Button size="sm" variant="secondary" onClick={handleUpdate}>
          Update
        </Button>
      </div>
    </motion.div>
  );
}
