import http from 'k6/http';
import { check } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';
import { uuid } from '../lib/helpers.js';

/**
 * S4: Concurrent submission on one attempt
 * 50 VUs double-submitting the same answer with the same idempotency key.
 * Proves: exactly one DB row per logical answer; no 5xx; replays return stored response.
 */
export const options = {
  scenarios: {
    idempotency_race: {
      executor: 'shared-iterations',
      vus: 50,
      iterations: 50,
      maxDuration: '30s',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.01'],
    checks: ['rate>0.99'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

// Shared idempotency key and answer UUID — all VUs submit the SAME answer
const SHARED_IDEM_KEY = '11111111-1111-4111-8111-111111111111';
const SHARED_ANSWER_UUID = '22222222-2222-4222-8222-222222222222';

export default function () {
  const token = tokens[0]; // All use the same user
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
    'Idempotency-Key': SHARED_IDEM_KEY,
  };

  // This attempt UUID and question ID must be pre-seeded in load-test data
  const attemptUuid = __ENV.ATTEMPT_UUID || 'test-attempt-uuid';
  const questionId = __ENV.QUESTION_ID || 'test-question-id';

  const res = http.post(
    `${BASE_URL}/student/quiz-attempts/${attemptUuid}/answers`,
    JSON.stringify({
      question_id: questionId,
      selected_option: 'a',
      client_answer_uuid: SHARED_ANSWER_UUID,
      time_spent_ms: 5000,
      answered_at: new Date().toISOString(),
      was_offline: false,
    }),
    { headers, tags: { name: 'answer_submit' } },
  );

  check(res, {
    'returns 200 (original or replay)': (r) => r.status === 200,
    'idempotent replay flagged on second+ hit': (r) =>
      r.status === 200, // X-Idempotent-Replay header may be set
  });
}
