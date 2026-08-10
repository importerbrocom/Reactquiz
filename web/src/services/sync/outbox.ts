import { PendingAnswersRepo } from '../db/pending-answers.repo';
import { submitAnswerBatch } from '@/api/endpoints/quiz.api';
import { generateUUID } from '@/utils/uuid';
import { ANSWER_BATCH_SIZE, MAX_RETRY_ATTEMPTS, RETRY_BACKOFF_MS } from '@/config/constants';
import { useNetworkStore } from '@/store/network.store';
import type { PendingAnswer } from '@/types/models';

let draining = false;

/**
 * Drain the offline outbox — send pending answers to the server in batches.
 *
 * Triggered by: online event, visibilitychange→visible, SW sync event,
 * app start, and a 30s timer while pending rows exist.
 *
 * Uses navigator.locks for cross-tab safety (with fallback).
 */
export async function drainOutbox(userHash: string): Promise<void> {
  if (!navigator.onLine || draining) return;

  // Cross-tab lock (graceful fallback if locks API unavailable)
  if ('locks' in navigator) {
    await navigator.locks.request(
      'qp-outbox',
      { ifAvailable: true },
      async (lock) => {
        if (!lock) return; // Another tab holds the lock
        await performDrain(userHash);
      },
    );
  } else {
    await performDrain(userHash);
  }
}

async function performDrain(userHash: string): Promise<void> {
  draining = true;
  const { setSyncing, decrementPendingSync } = useNetworkStore.getState();
  setSyncing(true);

  try {
    const repo = new PendingAnswersRepo(userHash);
    const pendingItems = await repo.getPending(ANSWER_BATCH_SIZE);

    if (pendingItems.length === 0) {
      setSyncing(false);
      draining = false;
      return;
    }

    // Group by attempt_uuid
    const groups = groupByAttempt(pendingItems);

    for (const [attemptUuid, items] of Object.entries(groups)) {
      const uuids = items.map((i) => i.client_answer_uuid);
      await repo.markInflight(uuids);

      try {
        const result = await submitAnswerBatch(attemptUuid, {
          batch_uuid: generateUUID(),
          answers: items.map((item) => ({
            question_id: item.question_id,
            selected_option: item.selected_option,
            client_answer_uuid: item.client_answer_uuid,
            time_spent_ms: item.time_spent_ms,
            answered_at: item.answered_at,
            was_offline: item.was_offline,
          })),
        });

        // Per-item success: remove from pending, add tombstone
        for (const itemResult of result.results) {
          await repo.remove(itemResult.client_answer_uuid);
          await repo.addSyncedTombstone(itemResult.client_answer_uuid, itemResult.is_correct);
        }
        decrementPendingSync(items.length);
      } catch (error: unknown) {
        // Network or 5xx: put back as pending with backoff
        for (const item of items) {
          const newAttempts = item.attempts + 1;
          if (newAttempts >= MAX_RETRY_ATTEMPTS) {
            await repo.markFailed(item.client_answer_uuid);
          } else {
            const backoffIndex = Math.min(newAttempts - 1, RETRY_BACKOFF_MS.length - 1);
            const backoff = RETRY_BACKOFF_MS[backoffIndex]!;
            await repo.markPendingWithBackoff(
              item.client_answer_uuid,
              newAttempts,
              Date.now() + backoff,
            );
          }
        }
      }
    }
  } finally {
    setSyncing(false);
    draining = false;
  }
}

function groupByAttempt(items: PendingAnswer[]): Record<string, PendingAnswer[]> {
  const groups: Record<string, PendingAnswer[]> = {};
  for (const item of items) {
    if (!groups[item.attempt_uuid]) {
      groups[item.attempt_uuid] = [];
    }
    groups[item.attempt_uuid]!.push(item);
  }
  return groups;
}
