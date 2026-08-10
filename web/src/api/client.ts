import axios from 'axios';
import { env } from '@/config/env';
import { attachAuthInterceptor } from './interceptors/auth.interceptor';
import { attachRefreshInterceptor } from './interceptors/refresh.interceptor';
import { attachIdempotencyInterceptor } from './interceptors/idempotency.interceptor';
import { attachContractVersionInterceptor } from './interceptors/contract-version.interceptor';
import { attachErrorInterceptor } from './interceptors/error.interceptor';

/**
 * Axios instance configured for the QuizPath API.
 * All student endpoints go through this client.
 */
export const apiClient = axios.create({
  baseURL: env.VITE_API_BASE_URL,
  timeout: 15_000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: true, // for refresh token cookie
});

// Order matters: request interceptors run first-attached-last,
// response interceptors run first-attached-first.
attachAuthInterceptor(apiClient);
attachIdempotencyInterceptor(apiClient);
attachContractVersionInterceptor(apiClient);
attachRefreshInterceptor(apiClient);
attachErrorInterceptor(apiClient);
