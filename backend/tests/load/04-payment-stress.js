import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';
import { BASE_URL, LOAD_PROFILES, THRESHOLDS } from './config.js';

export let options = {
  stages: LOAD_PROFILES.smoke,
  thresholds: THRESHOLDS,
};

const errorRate = new Rate('errors');

// Setup: Create a course to buy (run once)
export function setup() {
  // Register instructor
  let instructorResponse = http.post(
    `${BASE_URL}/register`,
    JSON.stringify({
      name: 'Load Test Instructor',
      email: 'loadtest.instructor@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      role: 'instructor'
    }),
    { headers: { 'Content-Type': 'application/json' } }
  );

  if (instructorResponse.status !== 201) return null;

  let instructorToken = instructorResponse.json('token');
  
  // Create course
  let courseResponse = http.post(
    `${BASE_URL}/instructor/courses`,
    JSON.stringify({
      title: 'Payment Load Test Course',
      description: 'Course for payment load testing',
      price: 49.99,
      level: 'beginner'
    }),
    { 
      headers: { 
        'Authorization': `Bearer ${instructorToken}`,
        'Content-Type': 'application/json' 
      } 
    }
  );

  if (courseResponse.status === 201) {
    return {
      courseId: courseResponse.json('course.id'),
      courseSlug: courseResponse.json('course.slug')
    };
  }
  
  return null;
}

export default function (data) {
  if (!data || !data.courseId) {
    errorRate.add(1);
    return;
  }

  const userId = Math.floor(Math.random() * 100000);
  
  // Step 1: Register student
  let studentResponse = http.post(
    `${BASE_URL}/register`,
    JSON.stringify({
      name: `Student${userId}`,
      email: `student${userId}@loadtest.com`,
      password: 'password123',
      password_confirmation: 'password123',
      role: 'student'
    }),
    { headers: { 'Content-Type': 'application/json' } }
  );

  if (studentResponse.status !== 201) {
    errorRate.add(1);
    return;
  }

  let studentToken = studentResponse.json('token');
  let authHeaders = {
    'Authorization': `Bearer ${studentToken}`,
    'Content-Type': 'application/json'
  };

  sleep(1);

  // Step 2: Initiate Payment
  let paymentResponse = http.post(
    `${BASE_URL}/courses/${data.courseId}/payment`,
    JSON.stringify({ payment_method: 'syrian_manual' }),
    { headers: authHeaders }
  );

  let orderId = '';
  let paymentInitiated = check(paymentResponse, {
    'payment initiation status is 200': (r) => r.status === 200,
    'payment has order_id': (r) => {
      if (r.status === 200) {
        orderId = r.json('order_id');
        return orderId !== undefined;
      }
      return false;
    },
    'payment initiation response time < 1000ms': (r) => r.timings.duration < 1000,
  });

  if (!paymentInitiated) {
    errorRate.add(1);
    return;
  }

  sleep(1);

  // Step 3: Confirm Payment
  let confirmResponse = http.post(
    `${BASE_URL}/payments/confirm`,
    JSON.stringify({
      order_id: orderId,
      confirmation_method: 'admin'
    }),
    { headers: authHeaders }
  );

  check(confirmResponse, {
    'payment confirmation status is 200': (r) => r.status === 200,
    'payment confirmation enrolled': (r) => r.json('enrolled') === true,
    'payment confirmation response time < 1500ms': (r) => r.timings.duration < 1500,
  }) || errorRate.add(1);

  sleep(1);

  // Step 4: Check Payment Status
  let statusResponse = http.get(
    `${BASE_URL}/payments/${orderId}/status`,
    { headers: authHeaders }
  );

  check(statusResponse, {
    'payment status check is 200': (r) => r.status === 200,
    'payment status response time < 300ms': (r) => r.timings.duration < 300,
  }) || errorRate.add(1);

  sleep(2);
}

export function handleSummary(data) {
  return {
    'payment-stress-results.json': JSON.stringify(data, null, 2),
  };
}