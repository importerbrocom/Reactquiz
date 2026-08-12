import type { AxiosInstance, InternalAxiosRequestConfig } from 'axios';

/**
 * In-memory access token. Also persisted to localStorage for page reload recovery.
 * Exported setters allow the auth store and refresh interceptor to update it.
 */
let accessToken: string | null = typeof window !== 'undefined' ? localStorage.getItem('qp_token') : null;

export function setAccessToken(token: string | null): void {
  accessToken = token;
  if (typeof window !== 'undefined') {
    if (token) {
      localStorage.setItem('qp_token', token);
    } else {
      localStorage.removeItem('qp_token');
    }
  }
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
