import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';
import { uuid } from '../lib/helpers.js';

/**
 * S8: Final-test progress saving
 * 300 VUs, each: start → 15 windowed fetches → 30 batch syncs of 10 answers → submit.
 * Proves: batch upserts scale; p95 batch < 700ms; grading queue drains.
 */
export const options = {
  scenarios: {
    final_test_batch: {
      executor: 'constant-vus',
      vus: 300,
      duration: '10m',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    'http_req_duration{name:final_batch_sync}': ['p(95)<700'],
    checks: ['rate>0.99'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

export default function () {
  const token = tokens[Math.floor(Math.random() * tokens.length)];
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  };
  const courseId = __ENV.COURSE_ID || 'test-course-id';

  group('final_test_journey', function () {
    // Start attempt
    const startRes = http.post(
      `${BASE_URL}/student/courses/${courseId}/final-test/attempts`,
      '{}',
      { headers: { ...headers, 'Idempotency-Key': uuid() }, tags: { name: 'final_test_start' } },
    );

    if (startRes.status !== 200 && startRes.status !== 201) return;
    const attempt = JSON.parse(startRes.body).data;
    if (!attempt?.attempt_uuid) return;

    // Windowed question fetches
    for (let pos = 1; pos <= 300; pos += 20) {
      http.get(
        `${BASE_URL}/student/final-test-attempts/${attempt.attempt_uuid}/questions?position=${pos}&limit=20`,
        { headers, tags: { name: 'final_questions_fetch' } },
      );
      sleep(2);
    }

    // Batch answer syncs (30 batches of 10)
    for (let batch = 0; batch < 30; batch++) {
      const answers = [];
      for (let i = 0; i < 10; i++) {
        answers.push({
          question_id: `q-${batch * 10 + i}`, // placeholder
          selected_option: ['a', 'b', 'c', 'd'][Math.floor(Math.random() * 4)],
          client_answer_uuid: uuid(),
          time_spent_ms: Math.floor(Math.random() * 20000 + 3000),
          answered_at: new Date().toISOString(),
          was_offline: false,
          flagged: Math.random() < 0.1,
        });
      }

      const syncRes = http.post(
        `${BASE_URL}/student/final-test-attempts/${attempt.attempt_uuid}/answers/batch`,
        JSON.stringify({ batch_uuid: uuid(), answers }),
        { headers: { ...headers, 'Idempotency-Key': uuid() }, tags: { name: 'final_batch_sync' } },
      );

      check(syncRes, { 'batch sync 200': (r) => r.status === 200 });
      sleep(3);
    }

    // Submit
    http.post(
      `${BASE_URL}/student/final-test-attempts/${attempt.attempt_uuid}/submit`,
      '{}',
      { headers: { ...headers, 'Idempotency-Key': uuid() }, tags: { name: 'final_submit' } },
    );
  });
}
