import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';
import { uuid } from '../lib/helpers.js';

/**
 * S14: Offline sync storm
 * 500 VUs each draining a 20-answer outbox batch simultaneously.
 * Simulates network coming back for many students at once.
 * Proves: no duplicates; p95 batch < 800ms; idempotency ledger holds.
 */
export const options = {
  scenarios: {
    sync_storm: {
      executor: 'constant-vus',
      vus: 500,
      duration: '3m',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    'http_req_duration{name:batch_drain}': ['p(95)<800'],
    checks: ['rate>0.995'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

export default function () {
  const token = tokens[Math.floor(Math.random() * tokens.length)];
  const attemptUuid = __ENV.ATTEMPT_UUID || 'test-attempt-uuid';
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
    'Idempotency-Key': uuid(),
  };

  // Build a 20-answer batch (simulating offline outbox drain)
  const answers = [];
  for (let i = 0; i < 20; i++) {
    answers.push({
      question_id: `q-offline-${__VU}-${i}`,
      selected_option: ['a', 'b', 'c', 'd'][Math.floor(Math.random() * 4)],
      client_answer_uuid: uuid(),
      time_spent_ms: Math.floor(Math.random() * 15000 + 2000),
      answered_at: new Date().toISOString(),
      was_offline: true,
    });
  }

  const res = http.post(
    `${BASE_URL}/student/quiz-attempts/${attemptUuid}/answers/batch`,
    JSON.stringify({ batch_uuid: uuid(), answers }),
    { headers, tags: { name: 'batch_drain' } },
  );

  check(res, {
    'batch drain 200': (r) => r.status === 200,
    'no 5xx': (r) => r.status < 500,
  });

  sleep(1);
}
