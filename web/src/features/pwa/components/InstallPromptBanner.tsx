import { useCallback } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { usePwaStore } from '@/store/pwa.store';
import { usePreferencesStore } from '@/store/preferences.store';
import { INSTALL_PROMPT_COOLDOWN_DAYS } from '@/config/constants';
import { Button } from '@/components/ui/Button';

/**
 * A2HS install prompt banner.
 *
 * Shown when the browser fires `beforeinstallprompt` AND the user hasn't
 * dismissed it within the cooldown window. Renders at the top of the student
 * layout so it doesn't block quiz interactions.
 *
 * The `beforeinstallprompt` event is captured in `register-sw.ts` and stored
 * in the PWA store. This component reads that state and triggers the native
 * prompt when the user taps "Install".
 */
export function InstallPromptBanner() {
  const { isInstallable, installEvent, clearInstallEvent } = usePwaStore();
  const { installPromptDismissedAt, setInstallPromptDismissed } = usePreferencesStore();

  // Don't show if not installable or if dismissed within cooldown period
  const cooldownMs = INSTALL_PROMPT_COOLDOWN_DAYS * 24 * 60 * 60 * 1000;
  const isDismissedRecently =
    installPromptDismissedAt !== null &&
    Date.now() - installPromptDismissedAt < cooldownMs;

  const shouldShow = isInstallable && !isDismissedRecently;

  const handleInstall = useCallback(async () => {
    if (!installEvent) return;

    // The event is a BeforeInstallPromptEvent (non-standard, not typed in lib.dom)
    const promptEvent = installEvent as BeforeInstallPromptEvent;
    promptEvent.prompt();

    const result = await promptEvent.userChoice;
    if (result.outcome === 'accepted') {
      clearInstallEvent();
    } else {
      // User declined the native prompt — treat as a dismiss
      setInstallPromptDismissed();
      clearInstallEvent();
    }
  }, [installEvent, clearInstallEvent, setInstallPromptDismissed]);

  const handleDismiss = useCallback(() => {
    setInstallPromptDismissed();
    clearInstallEvent();
  }, [setInstallPromptDismissed, clearInstallEvent]);

  return (
    <AnimatePresence>
      {shouldShow && (
        <motion.div
          className="border-b border-primary-500/20 bg-primary-500/5 px-4 py-3"
          initial={{ height: 0, opacity: 0 }}
          animate={{ height: 'auto', opacity: 1 }}
          exit={{ height: 0, opacity: 0 }}
          transition={{ duration: 0.3, ease: 'easeInOut' }}
          role="banner"
          aria-label="Install app prompt"
        >
          <div className="mx-auto flex max-w-3xl items-center gap-3">
            {/* App icon */}
            <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary-500/15">
              <span className="text-lg">📱</span>
            </div>

            {/* Message */}
            <div className="min-w-0 flex-1">
              <p className="text-sm font-medium text-white">Install QuizPath</p>
              <p className="text-xs text-surface-400">
                Add to home screen for offline access &amp; faster loading
              </p>
            </div>

            {/* Actions */}
            <div className="flex flex-shrink-0 items-center gap-2">
              <button
                onClick={handleDismiss}
                className="min-h-[44px] min-w-[44px] rounded-lg p-2 text-surface-400 transition-colors hover:text-surface-200"
                aria-label="Dismiss install prompt"
              >
                <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
              <Button size="sm" onClick={handleInstall}>
                Install
              </Button>
            </div>
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}

/**
 * BeforeInstallPromptEvent — non-standard Chrome/Edge API.
 * Not in lib.dom.d.ts, so we declare the shape we use.
 */
interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}
