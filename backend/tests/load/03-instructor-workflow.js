import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import { BASE_URL, LOAD_PROFILES, THRESHOLDS } from './config.js';

export let options = {
  stages: LOAD_PROFILES.smoke, // Use lighter load for instructor operations
  thresholds: THRESHOLDS,
};

const errorRate = new Rate('errors');

export default function () {
  const userId = Math.floor(Math.random() * 100000);
  
  // Step 1: Register as instructor
  let registerResponse = http.post(
    `${BASE_URL}/register`,
    JSON.stringify({
      name: `Instructor${userId}`,
      email: `instructor${userId}@loadtest.com`,
      password: 'password123',
      password_confirmation: 'password123',
      role: 'instructor'
    }),
    { headers: { 'Content-Type': 'application/json' } }
  );

  let token = '';
  if (registerResponse.status === 201) {
    token = registerResponse.json('token');
  } else {
    errorRate.add(1);
    return;
  }

  let authHeaders = {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  };

  sleep(1);

  // Step 2: Create Course
  let courseResponse = http.post(
    `${BASE_URL}/instructor/courses`,
    JSON.stringify({
      title: `Load Test Course ${userId}`,
      description: 'A course created during load testing',
      price: 99.99,
      level: 'beginner'
    }),
    { headers: authHeaders }
  );

  let courseId = '';
  let courseCreated = check(courseResponse, {
    'course creation status is 201': (r) => r.status === 201,
    'course has ID': (r) => {
      if (r.status === 201) {
        courseId = r.json('course.id');
        return courseId !== undefined;
      }
      return false;
    },
    'course creation response time < 800ms': (r) => r.timings.duration < 800,
  });

  if (!courseCreated) {
    errorRate.add(1);
    return;
  }

  sleep(1);

  // Step 3: Get Instructor Courses
  let listResponse = http.get(`${BASE_URL}/instructor/courses`, { headers: authHeaders });
  check(listResponse, {
    'course list status is 200': (r) => r.status === 200,
    'course list has courses': (r) => r.json('courses') !== undefined,
    'course list response time < 400ms': (r) => r.timings.duration < 400,
  }) || errorRate.add(1);

  sleep(1);

  // Step 4: Create Section
  let sectionResponse = http.post(
    `${BASE_URL}/instructor/courses/${courseId}/sections`,
    JSON.stringify({
      title: 'Introduction Section',
      position: 1
    }),
    { headers: authHeaders }
  );

  check(sectionResponse, {
    'section creation status is 201': (r) => r.status === 201,
    'section creation response time < 500ms': (r) => r.timings.duration < 500,
  }) || errorRate.add(1);

  sleep(2);

  // Cleanup: Delete course
  if (courseId) {
    let deleteResponse = http.del(`${BASE_URL}/instructor/courses/${courseId}`, { headers: authHeaders });
    check(deleteResponse, {
      'course deletion status is 200': (r) => r.status === 200,
    });
  }

  sleep(1);
}

export function handleSummary(data) {
  return {
    'instructor-workflow-results.json': JSON.stringify(data, null, 2),
  };
}