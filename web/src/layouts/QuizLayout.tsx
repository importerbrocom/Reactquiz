import { Outlet } from 'react-router';
import { useNetworkStore } from '@/store/network.store';

/**
 * Quiz layout — distraction-free, no bottom nav.
 * Used for daily quiz and final test sessions.
 * Shows a minimal offline indicator when needed.
 */
function QuizLayout() {
  const { isOnline, pendingSyncCount } = useNetworkStore();

  return (
    <div className="flex min-h-dvh flex-col bg-surface-950">
      {/* Compact offline indicator */}
      {!isOnline && (
        <div
          className="flex items-center justify-center gap-2 bg-warning-600/90 px-3 py-1.5 text-xs font-medium text-surface-950"
          role="status"
          aria-live="polite"
        >
          <span className="h-2 w-2 animate-pulse rounded-full bg-surface-950" />
          Offline — answers saved locally
          {pendingSyncCount > 0 && ` (${pendingSyncCount})`}
        </div>
      )}

      {/* Quiz content fills the screen */}
      <main className="flex flex-1 flex-col">
        <Outlet />
      </main>
    </div>
  );
}

export const Component = QuizLayout;
export default QuizLayout;
