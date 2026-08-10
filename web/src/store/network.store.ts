import { create } from 'zustand';

type EffectiveType = '4g' | '3g' | '2g' | 'slow-2g' | 'unknown';

interface NetworkState {
  /** Browser's navigator.onLine + our ping-confirmed status */
  isOnline: boolean;
  /** Connection quality from Network Information API */
  effectiveType: EffectiveType;
  /** Number of pending answers in the offline outbox */
  pendingSyncCount: number;
  /** Whether we're currently draining the outbox */
  isSyncing: boolean;

  setOnline: (online: boolean) => void;
  setEffectiveType: (type: EffectiveType) => void;
  setPendingSyncCount: (count: number) => void;
  setSyncing: (syncing: boolean) => void;
  incrementPendingSync: () => void;
  decrementPendingSync: (by?: number) => void;
}

/**
 * Network store — tracks connectivity, quality, and sync state.
 * Fed by useNetworkStatus() hook which combines:
 * - navigator.onLine events
 * - /api/v1/ping heartbeat (during quiz only)
 * - navigator.connection.effectiveType
 */
export const useNetworkStore = create<NetworkState>((set) => ({
  isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
  effectiveType: 'unknown',
  pendingSyncCount: 0,
  isSyncing: false,

  setOnline: (isOnline) => set({ isOnline }),
  setEffectiveType: (effectiveType) => set({ effectiveType }),
  setPendingSyncCount: (pendingSyncCount) => set({ pendingSyncCount }),
  setSyncing: (isSyncing) => set({ isSyncing }),
  incrementPendingSync: () => set((s) => ({ pendingSyncCount: s.pendingSyncCount + 1 })),
  decrementPendingSync: (by = 1) =>
    set((s) => ({ pendingSyncCount: Math.max(0, s.pendingSyncCount - by) })),
}));
