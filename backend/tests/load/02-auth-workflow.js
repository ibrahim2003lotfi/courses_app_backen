import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import { BASE_URL, LOAD_PROFILES, THRESHOLDS } from './config.js';

export let options = {
  stages: LOAD_PROFILES.load,
  thresholds: THRESHOLDS,
};

const errorRate = new Rate('errors');

export default function () {
  const userId = Math.floor(Math.random() * 100000);
  const userData = {
    name: `TestUser${userId}`,
    email: `user${userId}@loadtest.com`,
    password: 'password123',
    password_confirmation: 'password123',
    role: 'student'
  };

  // Test 1: User Registration
  let registerResponse = http.post(
    `${BASE_URL}/register`,
    JSON.stringify(userData),
    { headers: { 'Content-Type': 'application/json' } }
  );

  let token = '';
  let registrationSuccess = check(registerResponse, {
    'registration status is 201': (r) => r.status === 201,
    'registration has token': (r) => {
      if (r.status === 201) {
        token = r.json('token');
        return token !== undefined && token !== '';
      }
      return false;
    },
    'registration response time < 1000ms': (r) => r.timings.duration < 1000,
  });

  if (!registrationSuccess) {
    errorRate.add(1);
    return;
  }

  sleep(1);

  // Test 2: Authenticated Request
  let authHeaders = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  };

  let debugResponse = http.get(`${BASE_URL}/debug-user`, { headers: authHeaders });
  check(debugResponse, {
    'debug user status is 200': (r) => r.status === 200,
    'debug shows user info': (r) => r.json('user_id') !== undefined,
    'debug response time < 300ms': (r) => r.timings.duration < 300,
  }) || errorRate.add(1);

  sleep(1);

  // Test 3: Logout
  let logoutResponse = http.post(`${BASE_URL}/logout`, null, { headers: authHeaders });
  check(logoutResponse, {
    'logout status is 200': (r) => r.status === 200,
    'logout response time < 200ms': (r) => r.timings.duration < 200,
  }) || errorRate.add(1);

  sleep(2);
}

export function handleSummary(data) {
  return {
    'auth-workflow-results.json': JSON.stringify(data, null, 2),
  };
}