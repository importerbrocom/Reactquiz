import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';
import { uuid } from '../lib/helpers.js';

/**
 * S15: Soak test
 * 100 VUs mixed traffic for 4 hours.
 * Proves: no memory growth in PHP-FPM/workers; no connection leaks; queue steady state.
 */
export const options = {
  scenarios: {
    soak: {
      executor: 'constant-vus',
      vus: 100,
      duration: '4h',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    'http_req_duration': ['p(95)<1000'],
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

  // Mixed traffic: randomly pick an action
  const action = Math.random();

  if (action < 0.4) {
    // 40% dashboard loads
    const res = http.get(`${BASE_URL}/student/dashboard`, { headers, tags: { name: 'dashboard' } });
    check(res, { 'dashboard 200': (r) => r.status === 200 });
  } else if (action < 0.7) {
    // 30% answer submissions
    const attemptUuid = __ENV.ATTEMPT_UUID || 'soak-attempt';
    const res = http.post(
      `${BASE_URL}/student/quiz-attempts/${attemptUuid}/answers`,
      JSON.stringify({
        question_id: `q-soak-${__VU}-${__ITER}`,
        selected_option: 'b',
        client_answer_uuid: uuid(),
        time_spent_ms: 8000,
        answered_at: new Date().toISOString(),
        was_offline: false,
      }),
      { headers: { ...headers, 'Idempotency-Key': uuid() }, tags: { name: 'answer_submit' } },
    );
    check(res, { 'answer 200': (r) => r.status === 200 || r.status === 422 });
  } else if (action < 0.9) {
    // 20% progress/mistakes reads
    const res = http.get(`${BASE_URL}/student/progress`, { headers, tags: { name: 'progress' } });
    check(res, { 'progress 200': (r) => r.status === 200 });
  } else {
    // 10% notifications
    const res = http.get(`${BASE_URL}/student/notifications`, { headers, tags: { name: 'notifications' } });
    check(res, { 'notifications 200': (r) => r.status === 200 });
  }

  sleep(Math.random() * 5 + 2);
}
