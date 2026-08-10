/** Minimum touch target size in pixels (WCAG 2.5.8) */
export const TOUCH_TARGET_SIZE = 44;

/** Debounce delay for position saves (ms) */
export const POSITION_SAVE_DEBOUNCE_MS = 1500;

/** Batch size for offline answer sync */
export const ANSWER_BATCH_SIZE = 20;

/** Auto-advance delay after correct answer (ms) */
export const CORRECT_ANSWER_ADVANCE_MS = 450;

/** Ping interval during quiz for network status (ms) */
export const NETWORK_PING_INTERVAL_MS = 20_000;

/** Outbox drain interval when pending answers exist (ms) */
export const OUTBOX_DRAIN_INTERVAL_MS = 30_000;

/** Max retry attempts before marking answer as 'failed' */
export const MAX_RETRY_ATTEMPTS = 8;

/** Backoff schedule for answer sync retries (ms) */
export const RETRY_BACKOFF_MS = [2000, 8000, 30_000, 120_000, 600_000] as const;

/** Final test: debounce before batch sync (ms) */
export const FINAL_TEST_SYNC_DEBOUNCE_MS = 3000;

/** Final test: flush threshold (dirty answers) */
export const FINAL_TEST_FLUSH_THRESHOLD = 10;

/** Final test: periodic sync interval (ms) */
export const FINAL_TEST_SYNC_INTERVAL_MS = 30_000;

/** Final test: question window size */
export const FINAL_TEST_WINDOW_SIZE = 20;

/** Install prompt: minimum days between re-prompts */
export const INSTALL_PROMPT_COOLDOWN_DAYS = 14;

/** Stale quiz cache age before cleanup (ms) */
export const QUIZ_CACHE_TTL_MS = 24 * 60 * 60 * 1000; // 24h

/** Synced answer tombstone TTL (ms) */
export const SYNCED_ANSWER_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7d
