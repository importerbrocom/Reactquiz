import type { AxiosInstance, InternalAxiosRequestConfig } from 'axios';

/**
 * In-memory access token. Never persisted to disk — tab lifetime only.
 * Exported setters allow the auth store and refresh interceptor to update it.
 */
let accessToken: string | null = null;

export function setAccessToken(token: string | null): void {
  accessToken = token;
}

export function getAccessToken(): string | null {
  return accessToken;
}

/**
 * Attaches the Bearer token to every outgoing request (if available).
 */
export function attachAuthInterceptor(client: AxiosInstance): void {
  client.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    if (accessToken && config.headers) {
      config.headers.Authorization = `Bearer ${accessToken}`;
    }
    return config;
  });
}
