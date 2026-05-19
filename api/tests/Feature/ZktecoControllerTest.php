<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ZktecoControllerTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $this->company = Company::factory()->create();

        $this->manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_devices_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/zkteco/devices')
            ->assertUnauthorized();
    }

    public function test_devices_list_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/zkteco/devices')
            ->assertForbidden();
    }

    public function test_register_device_validates_required_fields(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/zkteco/devices', [])
            ->assertUnprocessable();
    }

    public function test_heartbeat_accepts_valid_serial(): void
    {
        $this->postJson('/api/v1/zkteco/heartbeat/UNKNOWN-SERIAL')
            ->assertStatus(404);
    }

    public function test_sync_attendance_rejects_invalid_serial(): void
    {
        $this->postJson('/api/v1/zkteco/sync-attendance/NONEXISTENT', [
            'records' => [
                ['user_id' => '1', 'timestamp' => '2026-01-01 08:00:00'],
            ],
        ])->assertStatus(404);
    }
}
