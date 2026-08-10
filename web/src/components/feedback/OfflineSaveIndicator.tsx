import { useNetworkStore } from '@/store/network.store';

/**
 * Compact indicator for the quiz screen showing pending answer count.
 */
export function OfflineSaveIndicator() {
  const { isOnline, pendingSyncCount } = useNetworkStore();

  if (isOnline && pendingSyncCount === 0) return null;

  return (
    <div
      className="flex items-center gap-1.5 rounded-full bg-surface-800 px-3 py-1 text-xs text-surface-300"
      role="status"
      aria-live="polite"
    >
      <span
        className={`h-2 w-2 rounded-full ${isOnline ? 'bg-primary-500' : 'bg-warning-500 animate-pulse'}`}
      />
      {pendingSyncCount > 0
        ? `${pendingSyncCount} saved locally`
        : 'Offline'}
    </div>
  );
}
