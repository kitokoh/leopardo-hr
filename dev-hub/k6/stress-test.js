import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 20 },  // Rampe d'accès à 20 VUs
        { duration: '1m', target: 20 },   // Maintien
        { duration: '30s', target: 0 },   // Descente
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% des requêtes sous 500ms
        http_req_failed: ['rate<0.01'],   // Moins de 1% d'erreur
    },
};

const BASE_URL = __ENV.API_URL || 'http://localhost:8000/api/v1';
const DEMO_EMAIL = __ENV.DEMO_EMAIL || 'admin@leopardo-rh.com';
const DEMO_PASS = __ENV.DEMO_PASS || 'password123';

export default function () {
    // 1. Auth: Login
    const loginRes = http.post(`${BASE_URL}/auth/login`, {
        email: DEMO_EMAIL,
        password: DEMO_PASS,
        device_name: 'k6-stress-test',
    });

    check(loginRes, {
        'login status is 200': (r) => r.status === 200,
        'has token': (r) => r.json('token') !== undefined,
    });

    const token = loginRes.json('token');

    if (!token) {
        console.error('Login failed');
        return;
    }

    const params = {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json',
        },
    };

    // 2. Fetch Dashboard summary
    const dashboardRes = http.get(`${BASE_URL}/dashboard/summary`, params);
    check(dashboardRes, {
        'dashboard is 200': (r) => r.status === 200,
    });

    sleep(1);

    // 3. Check employees list
    const employeesRes = http.get(`${BASE_URL}/employees?per_page=15`, params);
    check(employeesRes, {
        'employees is 200': (r) => r.status === 200,
    });

    sleep(1);
}
