import type { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';
import { apiClient } from '../client';
import { setAccessToken } from './auth.interceptor';

/**
 * Single-flight token refresh.
 * When a 401 arrives, we pause all concurrent requests, refresh once,
 * then replay them with the new token.
 */
let isRefreshing = false;
let failedQueue: Array<{
  resolve: (token: string) => void;
  reject: (error: unknown) => void;
}> = [];

function processQueue(error: unknown, token: string | null = null): void {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token!);
    }
  });
  failedQueue = [];
}

export function attachRefreshInterceptor(client: AxiosInstance): void {
  client.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

      // Only handle 401s, and not on the refresh endpoint itself
      if (
        error.response?.status !== 401 ||
        originalRequest._retry ||
        originalRequest.url?.includes('/auth/refresh') ||
        originalRequest.url?.includes('/auth/login')
      ) {
        return Promise.reject(error);
      }

      if (isRefreshing) {
        // Another refresh is in flight — queue this request
        return new Promise<string>((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        }).then((token) => {
          if (originalRequest.headers) {
            originalRequest.headers.Authorization = `Bearer ${token}`;
          }
          return apiClient(originalRequest);
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        // POST /auth/refresh uses the httpOnly refresh cookie
        const { data } = await apiClient.post('/auth/refresh');
        const newToken = data.data?.access_token as string;
        setAccessToken(newToken);
        processQueue(null, newToken);

        if (originalRequest.headers) {
          originalRequest.headers.Authorization = `Bearer ${newToken}`;
        }
        return apiClient(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        setAccessToken(null);
        // Redirect to login — the auth store listener handles this
        window.dispatchEvent(new CustomEvent('auth:session-expired'));
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    },
  );
}
