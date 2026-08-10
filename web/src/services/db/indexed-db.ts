import { openDB, type IDBPDatabase } from 'idb';
import type {
  PendingAnswer,
  SyncedAnswer,
  AttemptStateCache,
  FinalTestStateCache,
  QuizCacheEntry,
  SyncMeta,
} from '@/types/models';

const DB_VERSION = 1;

/**
 * IndexedDB schema — per-user isolation via hashed user UUID in the DB name.
 * Schema follows docs/phase-1/06-pwa-and-offline.md exactly.
 */

export interface QuizPathDB {
  pending_answers: {
    key: string; // client_answer_uuid
    value: PendingAnswer;
    indexes: {
      by_attempt: string;
      by_status: string;
    };
  };
  synced_answers: {
    key: string; // client_answer_uuid
    value: SyncedAnswer;
  };
  attempt_state: {
    key: string; // attempt_uuid
    value: AttemptStateCache;
  };
  final_test_state: {
    key: string; // attempt_uuid
    value: FinalTestStateCache;
  };
  quiz_cache: {
    key: string; // `${course_id}:${day_number}`
    value: QuizCacheEntry;
  };
  meta: {
    key: string;
    value: SyncMeta;
  };
}

let dbPromise: Promise<IDBPDatabase<QuizPathDB>> | null = null;

/**
 * Get or create the per-user IndexedDB instance.
 * DB name includes a user hash for per-user isolation.
 */
export function getDB(userHash: string): Promise<IDBPDatabase<QuizPathDB>> {
  const dbName = `qp-db-${userHash}`;

  if (!dbPromise) {
    dbPromise = openDB<QuizPathDB>(dbName, DB_VERSION, {
      upgrade(db) {
        // pending_answers store
        const pendingStore = db.createObjectStore('pending_answers', {
          keyPath: 'client_answer_uuid',
        });
        pendingStore.createIndex('by_attempt', 'attempt_uuid');
        pendingStore.createIndex('by_status', 'status');

        // synced_answers store (tombstones)
        db.createObjectStore('synced_answers', { keyPath: 'client_answer_uuid' });

        // attempt_state cache
        db.createObjectStore('attempt_state', { keyPath: 'attempt_uuid' });

        // final_test_state cache
        db.createObjectStore('final_test_state', { keyPath: 'attempt_uuid' });

        // quiz_cache for answer-free day payloads
        db.createObjectStore('quiz_cache', { keyPath: 'course_id' });

        // meta store
        db.createObjectStore('meta');
      },
    });
  }

  return dbPromise;
}

/**
 * Close and reset the DB reference (used on logout).
 */
export function closeDB(): void {
  dbPromise = null;
}

/**
 * Delete the entire database (logout / session revoked).
 */
export async function deleteUserDB(userHash: string): Promise<void> {
  closeDB();
  const dbName = `qp-db-${userHash}`;
  await new Promise<void>((resolve, reject) => {
    const req = indexedDB.deleteDatabase(dbName);
    req.onsuccess = () => resolve();
    req.onerror = () => reject(req.error);
  });
}

/**
 * Generate user hash for DB name (sha256 of user UUID, first 16 chars).
 */
export async function hashUserId(uuid: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(uuid);
  const hashBuffer = await crypto.subtle.digest('SHA-256', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const hashHex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
  return hashHex.slice(0, 16);
}
