import { create } from 'zustand';

interface PwaState {
  /** Whether the browser supports install and hasn't installed yet */
  isInstallable: boolean;
  /** The captured beforeinstallprompt event */
  installEvent: Event | null;
  /** Whether a new service worker is waiting to activate */
  updateAvailable: boolean;
  /** The waiting ServiceWorkerRegistration */
  swRegistration: ServiceWorkerRegistration | null;
  /** Whether the API contract has moved beyond this build */
  contractMismatch: boolean;

  setInstallable: (installable: boolean, event?: Event | null) => void;
  setUpdateAvailable: (available: boolean, registration?: ServiceWorkerRegistration | null) => void;
  setContractMismatch: (mismatch: boolean) => void;
  clearInstallEvent: () => void;
}

/**
 * PWA store — install prompt state, SW updates, and contract version.
 * Fed by register-sw.ts and the contract-version interceptor.
 */
export const usePwaStore = create<PwaState>((set) => ({
  isInstallable: false,
  installEvent: null,
  updateAvailable: false,
  swRegistration: null,
  contractMismatch: false,

  setInstallable: (isInstallable, event = null) => set({ isInstallable, installEvent: event }),
  setUpdateAvailable: (updateAvailable, swRegistration = null) =>
    set({ updateAvailable, swRegistration }),
  setContractMismatch: (contractMismatch) => set({ contractMismatch }),
  clearInstallEvent: () => set({ isInstallable: false, installEvent: null }),
}));
