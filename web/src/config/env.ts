/**
 * Environment variables — simplified for production.
 * No Zod validation to avoid runtime crashes on misconfigured envs.
 */
export const env = {
  VITE_API_BASE_URL: import.meta.env.VITE_API_BASE_URL || 'https://mediprep.nokkoo.in/api/v1',
  VITE_API_CONTRACT_VERSION: Number(import.meta.env.VITE_API_CONTRACT_VERSION || '1'),
  VITE_VAPID_PUBLIC_KEY: import.meta.env.VITE_VAPID_PUBLIC_KEY || '',
  VITE_SENTRY_DSN: import.meta.env.VITE_SENTRY_DSN || '',
  VITE_APP_NAME: import.meta.env.VITE_APP_NAME || 'QuizPath',
};
