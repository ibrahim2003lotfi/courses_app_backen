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
  // Test 1: API Health Check
  let healthResponse = http.get(`${BASE_URL}/test`);
  check(healthResponse, {
    'health check status is 200': (r) => r.status === 200,
    'health check response time < 200ms': (r) => r.timings.duration < 200,
  }) || errorRate.add(1);

  sleep(1);

  // Test 2: Public Courses List (most common endpoint)
  let coursesResponse = http.get(`${BASE_URL}/courses?per_page=10`);
  check(coursesResponse, {
    'courses list status is 200': (r) => r.status === 200,
    'courses list has data': (r) => r.json('data') !== undefined,
    'courses list response time < 500ms': (r) => r.timings.duration < 500,
  }) || errorRate.add(1);

  sleep(1);

  // Test 3: Home Page Data
  let homeResponse = http.get(`${BASE_URL}/v1/home`);
  check(homeResponse, {
    'home page status is 200': (r) => r.status === 200,
    'home has categories': (r) => r.json('categories') !== undefined,
    'home response time < 800ms': (r) => r.timings.duration < 800,
  }) || errorRate.add(1);

  sleep(1);

  // Test 4: Search (with query)
  let searchResponse = http.get(`${BASE_URL}/v1/search?q=test&type=course`);
  check(searchResponse, {
    'search status is 200': (r) => r.status === 200,
    'search has results': (r) => r.json('results') !== undefined,
    'search response time < 600ms': (r) => r.timings.duration < 600,
  }) || errorRate.add(1);

  sleep(2);
}

export function handleSummary(data) {
  return {
    'public-endpoints-results.json': JSON.stringify(data, null, 2),
  };
}