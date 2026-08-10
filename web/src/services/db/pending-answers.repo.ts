import { getDB } from './indexed-db';
import type { PendingAnswer } from '@/types/models';

/**
 * Repository for the pending_answers IndexedDB store.
 * Manages the offline outbox for quiz answers.
 */
export class PendingAnswersRepo {
  constructor(private userHash: string) {}

  async add(answer: PendingAnswer): Promise<void> {
    const db = await getDB(this.userHash);
    await db.put('pending_answers', answer);
  }

  async getByStatus(status: PendingAnswer['status']): Promise<PendingAnswer[]> {
    const db = await getDB(this.userHash);
    return db.getAllFromIndex('pending_answers', 'by_status', status);
  }

  async getPending(limit: number = 20): Promise<PendingAnswer[]> {
    const db = await getDB(this.userHash);
    const all = await db.getAllFromIndex('pending_answers', 'by_status', 'pending');
    const now = Date.now();
    return all
      .filter((a) => a.next_retry_at <= now)
      .sort((a, b) => new Date(a.answered_at).getTime() - new Date(b.answered_at).getTime())
      .slice(0, limit);
  }

  async markInflight(uuids: string[]): Promise<void> {
    const db = await getDB(this.userHash);
    const tx = db.transaction('pending_answers', 'readwrite');
    for (const uuid of uuids) {
      const item = await tx.store.get(uuid);
      if (item) {
        item.status = 'inflight';
        await tx.store.put(item);
      }
    }
    await tx.done;
  }

  async markPendingWithBackoff(uuid: string, attempts: number, nextRetryAt: number): Promise<void> {
    const db = await getDB(this.userHash);
    const item = await db.get('pending_answers', uuid);
    if (item) {
      item.status = 'pending';
      item.attempts = attempts;
      item.next_retry_at = nextRetryAt;
      await db.put('pending_answers', item);
    }
  }

  async markFailed(uuid: string): Promise<void> {
    const db = await getDB(this.userHash);
    const item = await db.get('pending_answers', uuid);
    if (item) {
      item.status = 'failed';
      await db.put('pending_answers', item);
    }
  }

  async remove(uuid: string): Promise<void> {
    const db = await getDB(this.userHash);
    await db.delete('pending_answers', uuid);
  }

  async removeByAttempt(attemptUuid: string): Promise<void> {
    const db = await getDB(this.userHash);
    const items = await db.getAllFromIndex('pending_answers', 'by_attempt', attemptUuid);
    const tx = db.transaction('pending_answers', 'readwrite');
    for (const item of items) {
      await tx.store.delete(item.client_answer_uuid);
    }
    await tx.done;
  }

  async count(): Promise<number> {
    const db = await getDB(this.userHash);
    return db.count('pending_answers');
  }

  async addSyncedTombstone(uuid: string, isCorrect: boolean): Promise<void> {
    const db = await getDB(this.userHash);
    await db.put('synced_answers', {
      client_answer_uuid: uuid,
      synced_at: Date.now(),
      is_correct: isCorrect,
    });
  }
}
