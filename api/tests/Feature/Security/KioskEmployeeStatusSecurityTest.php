<?php

namespace Tests\Feature\Security;

use App\Models\AttendanceKiosk;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class KioskEmployeeStatusSecurityTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_kiosk_punch_rejects_non_active_employees(): void
    {
        $company = Company::query()->create([
            'id' => Str::uuid(),
            'name' => 'Security Test Corp',
            'slug' => 'security-test-corp',
            'sector' => 'Security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'security@test.corp',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        app()->instance('current_company', $company);

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Front Desk Kiosk',
            'device_code' => 'KIOSK001',
            'sync_token_hash' => Hash::make('secret-token'),
            'status' => 'active',
        ]);

        $activeEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Active',
            'last_name' => 'User',
            'email' => 'active@test.corp',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_face_enabled' => true,
        ]);

        $suspendedEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'email' => 'suspended@test.corp',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'suspended',
            'biometric_face_enabled' => true,
        ]);

        $archivedEmployee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Archived',
            'last_name' => 'User',
            'email' => 'archived@test.corp',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'archived',
            'biometric_face_enabled' => true,
        ]);

        // Active employee should work
        $this->withHeader('X-Kiosk-Token', 'secret-token')
            ->postJson('/api/v1/kiosks/KIOSK001/punch', [
                'identifier' => 'active@test.corp',
                'action' => 'check_in',
            ])
            ->assertStatus(201);

        // Suspended employee should be rejected (403)
        $this->withHeader('X-Kiosk-Token', 'secret-token')
            ->postJson('/api/v1/kiosks/KIOSK001/punch', [
                'identifier' => 'suspended@test.corp',
                'action' => 'check_in',
            ])
            ->assertStatus(403);

        // Archived employee should be rejected (403)
        $this->withHeader('X-Kiosk-Token', 'secret-token')
            ->postJson('/api/v1/kiosks/KIOSK001/punch', [
                'identifier' => 'archived@test.corp',
                'action' => 'check_in',
            ])
            ->assertStatus(403);
    }

    public function test_kiosk_sync_skips_non_active_employees(): void
    {
        $company = Company::query()->create([
            'id' => Str::uuid(),
            'name' => 'Security Test Corp',
            'slug' => 'security-test-corp',
            'sector' => 'Security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'security@test.corp',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        app()->instance('current_company', $company);

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Front Desk Kiosk',
            'device_code' => 'KIOSK001',
            'sync_token_hash' => Hash::make('secret-token'),
            'status' => 'active',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Active',
            'last_name' => 'User',
            'email' => 'active@test.corp',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_face_enabled' => true,
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'email' => 'suspended@test.corp',
            'password_hash' => Hash::make('password'),
            'role' => 'employee',
            'status' => 'suspended',
            'biometric_face_enabled' => true,
        ]);

        $response = $this->withHeader('X-Kiosk-Token', 'secret-token')
            ->postJson('/api/v1/kiosks/KIOSK001/sync', [
                'events' => [
                    [
                        'identifier' => 'active@test.corp',
                        'action' => 'check_in',
                        'occurred_at' => now()->toIso8601String(),
                        'external_event_id' => 'evt_1',
                    ],
                    [
                        'identifier' => 'suspended@test.corp',
                        'action' => 'check_in',
                        'occurred_at' => now()->toIso8601String(),
                        'external_event_id' => 'evt_2',
                    ],
                ],
            ])
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('data.processed_count'), 'Only the active employee should have been processed');
        $this->assertCount(1, AttendanceLog::all());
        $this->assertEquals('active@test.corp', AttendanceLog::first()->employee->email);
    }
}
