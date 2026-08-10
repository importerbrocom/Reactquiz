import type { AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import { generateUUID } from '@/utils/uuid';

/**
 * Custom axios config extension for flagging idempotent mutations.
 * Usage: apiClient.post('/endpoint', body, { idempotent: true })
 */
declare module 'axios' {
  interface AxiosRequestConfig {
    idempotent?: boolean;
    idempotencyKey?: string;
  }
  interface InternalAxiosRequestConfig {
    idempotent?: boolean;
    idempotencyKey?: string;
  }
}

/**
 * Attaches an Idempotency-Key header on mutations flagged with `idempotent: true`.
 * If a specific key is provided via `idempotencyKey`, uses that; otherwise generates one.
 *
 * The server treats this as an optional faster short-circuit — the real guarantee
 * is the client_answer_uuid unique constraint, not this header.
 */
export function attachIdempotencyInterceptor(client: AxiosInstance): void {
  client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    if (config.idempotent && config.headers) {
      const key = config.idempotencyKey ?? generateUUID();
      config.headers['Idempotency-Key'] = key;
    }
    return config;
  });
}
