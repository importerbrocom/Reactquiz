import type { AxiosInstance, AxiosResponse } from 'axios';
import { env } from '@/config/env';

/**
 * Reads `X-Api-Contract` from every API response.
 * If the server's contract version exceeds what this build was designed for,
 * fires an event so the PWA update prompt can force a reload.
 */
export function attachContractVersionInterceptor(client: AxiosInstance): void {
  client.interceptors.response.use((response: AxiosResponse) => {
    const serverContract = response.headers['x-api-contract'];
    if (serverContract) {
      const serverVersion = parseInt(serverContract, 10);
      if (!isNaN(serverVersion) && serverVersion > env.VITE_API_CONTRACT_VERSION) {
        window.dispatchEvent(
          new CustomEvent('pwa:contract-mismatch', {
            detail: { server: serverVersion, client: env.VITE_API_CONTRACT_VERSION },
          }),
        );
      }
    }
    return response;
  });
}
