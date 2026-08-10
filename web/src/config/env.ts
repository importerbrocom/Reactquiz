import { z } from 'zod';

/**
 * Zod-validated environment variables.
 * Fails the build if misconfigured — no silent undefined access.
 */
const envSchema = z.object({
  VITE_API_BASE_URL: z.string().url(),
  VITE_API_CONTRACT_VERSION: z.coerce.number().int().positive(),
  VITE_VAPID_PUBLIC_KEY: z.string().default(''),
  VITE_SENTRY_DSN: z.string().default(''),
  VITE_APP_NAME: z.string().default('QuizPath'),
});

function parseEnv() {
  const result = envSchema.safeParse(import.meta.env);
  if (!result.success) {
    console.error('❌ Invalid environment variables:', result.error.flatten().fieldErrors);
    throw new Error('Invalid environment configuration. Check .env file.');
  }
  return result.data;
}

export const env = parseEnv();
