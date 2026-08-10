/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string;
  readonly VITE_API_CONTRACT_VERSION: string;
  readonly VITE_VAPID_PUBLIC_KEY: string;
  readonly VITE_SENTRY_DSN: string;
  readonly VITE_APP_NAME: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
