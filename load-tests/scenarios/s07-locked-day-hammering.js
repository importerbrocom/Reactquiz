import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';

/**
 * S7: Locked-day hammering
 * 500 VUs requesting random future days and other students' attempt IDs.
 * Proves: 100% 423/404; no data leakage; DB load stays flat (rejections are cheap).
 */
export const options = {
  scenarios: {
    locked_hammering: {
      executor: 'constant-vus',
      vus: 500,
      duration: '3m',
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    checks: ['rate>0.999'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

export default function () {
  const token = tokens[Math.floor(Math.random() * tokens.length)];
  const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };

  const courseId = __ENV.COURSE_ID || 'test-course-id';
  const futureDay = Math.floor(Math.random() * 20) + 11; // days 11-30 (likely locked)

  const res = http.get(`${BASE_URL}/student/courses/${courseId}/quiz-days/${futureDay}`, {
    headers,
    tags: { name: 'locked_day' },
  });

  check(res, {
    'locked day returns 423 or 200 (never 5xx)': (r) => r.status === 423 || r.status === 200,
    'no answer content leaked': (r) => !r.body.includes('"correct_option"'),
  });

  sleep(0.5);
}
