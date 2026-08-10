import type { AxiosError, AxiosInstance } from 'axios';
import type { ApiError, ApiEnvelope } from '@/types/api';

/**
 * Normalises all API errors into an ApiError shape.
 * The rest of the app catches `ApiError`, never raw AxiosErrors.
 */
export function attachErrorInterceptor(client: AxiosInstance): void {
  client.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiEnvelope>) => {
      const apiError: ApiError = {
        status: error.response?.status ?? 0,
        message: error.response?.data?.message ?? error.message ?? 'Network error',
        code: extractErrorCode(error.response?.data),
        errors: error.response?.data?.errors ?? null,
        meta: error.response?.data?.meta ?? null,
      };

      // Attach the normalised error to the rejected promise
      return Promise.reject(apiError);
    },
  );
}

function extractErrorCode(data: ApiEnvelope | undefined): string | null {
  if (!data?.errors) return null;
  // The API puts the machine-readable code in errors.code
  if ('code' in data.errors && Array.isArray(data.errors.code)) {
    return data.errors.code[0] ?? null;
  }
  // Fallback: check meta
  if (data.meta && typeof data.meta.error_code === 'string') {
    return data.meta.error_code;
  }
  return null;
}

/**
 * Type guard to check if an error is an ApiError from our interceptor.
 */
export function isApiError(error: unknown): error is ApiError {
  return (
    typeof error === 'object' &&
    error !== null &&
    'status' in error &&
    'message' in error &&
    'code' in error
  );
}
