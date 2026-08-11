import http from 'k6/http';
import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';

/**
 * S2: Dashboard Load
 * Constant-arrival 200 rps for 5 min.
 * Proves: dashboard cache works; p95 < 300ms cached.
 */
export const options = {
  scenarios: {
    dashboard_load: {
      executor: 'constant-arrival-rate',
      rate: 200,
      timeUnit: '1s',
      duration: '5m',
      preAllocatedVUs: 300,
      maxVUs: 500,
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    'http_req_duration{name:dashboard}': ['p(95)<300'],
    checks: ['rate>0.995'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

export default function () {
  const token = tokens[Math.floor(Math.random() * tokens.length)];

  const res = http.get(`${BASE_URL}/student/dashboard`, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    tags: { name: 'dashboard' },
  });

  check(res, {
    'dashboard returns 200': (r) => r.status === 200,
    'response has next_action': (r) => JSON.parse(r.body).data?.next_action !== undefined,
  });
}
