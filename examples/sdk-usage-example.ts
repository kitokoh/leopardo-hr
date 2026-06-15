/**
 * Leopardo RH — TypeScript SDK Usage Example
 *
 * This example demonstrates how to authenticate and fetch employee data
 * using the Leopardo RH API.
 */

import { LeopardoClient } from '@leopardo-rh/sdk';

async function main() {
    // 1. Initialize the client
    const client = new LeopardoClient({
        baseUrl: 'https://api.leopardo-rh.com/api/v1',
        timeout: 5000,
    });

    try {
        // 2. Authenticate
        console.log('🔑 Authenticating...');
        const auth = await client.auth.login({
            email: 'admin@leopardo-rh.com',
            password: 'password123'
        });

        console.log(`✅ Logged in as: ${auth.user.name}`);
        client.setToken(auth.token);

        // 3. Fetch Employees
        console.log('👥 Fetching employees...');
        const employees = await client.hr.listEmployees({
            status: 'active',
            limit: 10
        });

        console.table(employees.data.map(emp => ({
            ID: emp.id,
            Name: emp.name,
            Department: emp.department?.name,
            Role: emp.job_title
        })));

        // 4. Record a mock Attendance Punch
        console.log('🕒 Recording attendance punch...');
        const punch = await client.attendance.punch({
            type: 'check_in',
            lat: 36.7538,
            lng: 3.0588, // Algiers coordinates
            device_id: 'sample-sdk-device'
        });

        console.log(`✅ Punch recorded at ${punch.timestamp}`);

    } catch (error) {
        console.error('❌ SDK Error:', error.message);
    }
}

main();
