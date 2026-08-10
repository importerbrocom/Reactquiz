import { create } from 'zustand';
import { persist } from 'zustand/middleware';

interface PreferencesState {
  /** Last selected course ID for quick resume */
  lastCourseId: string | null;
  /** UI density preference */
  listDensity: 'compact' | 'comfortable';
  /** Install prompt last dismissed timestamp */
  installPromptDismissedAt: number | null;
  /** Whether notification permission was previously denied */
  notificationDenied: boolean;

  setLastCourseId: (id: string | null) => void;
  setListDensity: (density: 'compact' | 'comfortable') => void;
  setInstallPromptDismissed: () => void;
  setNotificationDenied: (denied: boolean) => void;
}

/**
 * Preferences store — tiny, non-sensitive user preferences.
 * Persisted to localStorage. Survives page reloads.
 */
export const usePreferencesStore = create<PreferencesState>()(
  persist(
    (set) => ({
      lastCourseId: null,
      listDensity: 'comfortable',
      installPromptDismissedAt: null,
      notificationDenied: false,

      setLastCourseId: (lastCourseId) => set({ lastCourseId }),
      setListDensity: (listDensity) => set({ listDensity }),
      setInstallPromptDismissed: () => set({ installPromptDismissedAt: Date.now() }),
      setNotificationDenied: (notificationDenied) => set({ notificationDenied }),
    }),
    {
      name: 'qp-preferences',
    },
  ),
);
