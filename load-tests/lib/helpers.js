import http from 'k6/http';
import { check } from 'k6';

/**
 * Authenticate and return headers with bearer token.
 */
export function login(email, password) {
  const res = http.post(`${__ENV.API_BASE_URL || 'http://localhost:8000/api/v1'}/auth/login`, JSON.stringify({
    email,
    password,
    device_name: 'k6-load-test',
  }), {
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    tags: { name: 'login' },
  });

  check(res, { 'login successful': (r) => r.status === 200 });

  const body = JSON.parse(res.body);
  return {
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${body.data?.access_token || ''}`,
    },
  };
}

/**
 * Generate a UUID v4.
 */
export function uuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Random think time between min and max seconds.
 */
export function think(minS, maxS) {
  const ms = (minS + Math.random() * (maxS - minS)) * 1000;
  return new Promise((resolve) => setTimeout(resolve, ms));
}
