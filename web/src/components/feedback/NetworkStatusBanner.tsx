import { useNetworkStore } from '@/store/network.store';

/**
 * Network status banner — shown at the top when offline or syncing.
 * Uses aria-live="polite" for screen reader announcements.
 */
export function NetworkStatusBanner() {
  const { isOnline, isSyncing, pendingSyncCount } = useNetworkStore();

  if (isOnline && !isSyncing) return null;

  return (
    <div
      className="flex items-center justify-center gap-2 px-4 py-2 text-center text-sm font-medium"
      role="status"
      aria-live="polite"
      style={{
        backgroundColor: isOnline ? 'rgba(79, 70, 229, 0.9)' : 'rgba(217, 119, 6, 0.9)',
        color: '#ffffff',
      }}
    >
      {!isOnline && (
        <>
          <span className="h-2 w-2 animate-pulse rounded-full bg-white" />
          Offline — your answers are being saved
          {pendingSyncCount > 0 && ` (${pendingSyncCount})`}
        </>
      )}
      {isOnline && isSyncing && (
        <>
          <span className="h-2 w-2 animate-spin rounded-full border border-white border-t-transparent" />
          Back online — syncing ({pendingSyncCount})...
        </>
      )}
    </div>
  );
}
