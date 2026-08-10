import { useEffect } from 'react';
import { useNetworkStore } from '@/store/network.store';

/**
 * Combines navigator.onLine, connection API, and online/offline events
 * to maintain accurate network state in the store.
 */
export function useNetworkStatus() {
  const { setOnline, setEffectiveType } = useNetworkStore();

  useEffect(() => {
    const handleOnline = () => setOnline(true);
    const handleOffline = () => setOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    // Network Information API (if available)
    const connection = (navigator as unknown as { connection?: { effectiveType: string; addEventListener: (e: string, fn: () => void) => void; removeEventListener: (e: string, fn: () => void) => void } }).connection;
    const handleConnectionChange = () => {
      if (connection) {
        setEffectiveType(connection.effectiveType as '4g' | '3g' | '2g' | 'slow-2g');
      }
    };

    if (connection) {
      handleConnectionChange();
      connection.addEventListener('change', handleConnectionChange);
    }

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      connection?.removeEventListener('change', handleConnectionChange);
    };
  }, [setOnline, setEffectiveType]);
}
