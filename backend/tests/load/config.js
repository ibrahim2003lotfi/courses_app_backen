export const BASE_URL = 'http://localhost:8000/api';

export const LOAD_PROFILES = {
  smoke: {
    stages: [
      { duration: '30s', target: 2 },  // Ramp up to 2 users
      { duration: '1m', target: 2 },   // Stay at 2 users
      { duration: '30s', target: 0 },  // Ramp down
    ]
  },
  load: {
    stages: [
      { duration: '2m', target: 10 },  // Ramp up to 10 users
      { duration: '5m', target: 10 },  // Stay at 10 users
      { duration: '2m', target: 20 },  // Ramp up to 20 users
      { duration: '5m', target: 20 },  // Stay at 20 users
      { duration: '2m', target: 0 },   // Ramp down
    ]
  },
  stress: {
    stages: [
      { duration: '2m', target: 20 },  // Ramp up to 20 users
      { duration: '5m', target: 20 },  // Stay at 20 users
      { duration: '2m', target: 50 },  // Ramp up to 50 users
      { duration: '5m', target: 50 },  // Stay at 50 users
      { duration: '2m', target: 100 }, // Spike to 100 users
      { duration: '1m', target: 100 }, // Stay at 100 users
      { duration: '3m', target: 0 },   // Ramp down
    ]
  }
};

export const THRESHOLDS = {
  http_req_duration: ['p(95)<500'], // 95% of requests under 500ms
  http_req_failed: ['rate<0.1'],    // Error rate under 10%
  http_reqs: ['rate>10'],           // At least 10 RPS
};