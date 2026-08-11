import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL, thresholds } from '../lib/config.js';

/**
 * S1: Login Storm
 * 100 → 500 → 1,000 VUs ramping over 3 min, one login each.
 * Proves: auth + bcrypt cost don't melt CPU; throttling behaves; p95 login < 800ms.
 */
export const options = {
  scenarios: {
    login_storm: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 100 },
        { duration: '1m', target: 500 },
        { duration: '1m', target: 1000 },
      ],
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.01'],
    'http_req_duration{name:login}': ['p(95)<800'],
    checks: ['rate>0.99'],
  },
};

const users = new SharedArray('users', function () {
  return JSON.parse(open('../data/users.json'));
});

export default function () {
  const user = users[Math.floor(Math.random() * users.length)];

  const res = http.post(`${BASE_URL}/auth/login`, JSON.stringify({
    email: user.email,
    password: user.password,
    device_name: 'k6-load-test',
  }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { name: 'login' },
  });

  check(res, {
    'login returns 200 or 429': (r) => r.status === 200 || r.status === 429,
    'response has access_token': (r) => r.status === 429 || JSON.parse(r.body).data?.access_token,
  });

  sleep(1);
}
