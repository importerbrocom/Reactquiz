/**
 * Shared configuration for all k6 load test scenarios.
 */
export const BASE_URL = __ENV.API_BASE_URL || 'http://localhost:8000/api/v1';
export const USERS_CSV = __ENV.USERS_CSV || './data/users.csv';

// Thresholds applied per scenario as relevant
export const thresholds = {
  'http_req_failed': ['rate<0.005'],
  'http_req_duration{name:answer_submit}': ['p(95)<500', 'p(99)<1000'],
  'http_req_duration{name:quiz_day_fetch}': ['p(95)<700'],
  'http_req_duration{name:dashboard}': ['p(95)<300'],
  'http_req_duration{name:final_batch_sync}': ['p(95)<700'],
  'http_req_duration{name:complete_day}': ['p(95)<800'],
  'http_req_duration{name:login}': ['p(95)<800'],
  'checks': ['rate>0.995'],
};
