<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeviceToken;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
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

    public function test_register_requires_authentication(): void
    {
        $this->postJson('/api/v1/device-tokens', [
            'token' => 'test-fcm-token',
            'platform' => 'android',
        ])->assertUnauthorized();
    }

    public function test_register_validates_platform(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens', [
                'token' => 'test-token',
                'platform' => 'invalid',
            ])
            ->assertUnprocessable();
    }

    public function test_register_requires_token_and_platform(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens', [])
            ->assertUnprocessable();
    }

    public function test_register_upserts_and_lists_current_user_token(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens', [
                'token' => 'employee-fcm-token',
                'platform' => 'android',
                'device_name' => 'Pixel 8',
            ])
            ->assertCreated()
            ->assertJsonPath('data.token', 'employee-fcm-token')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.is_active', true);

        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens', [
                'token' => 'employee-fcm-token',
                'platform' => 'ios',
                'device_name' => 'iPhone',
            ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.device_name', 'iPhone');

        $this->assertSame(1, DeviceToken::query()->where('employee_id', $this->employee->id)->count());

        $this->actingAs($this->employee)
            ->getJson('/api/v1/device-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.token', 'employee-fcm-token');
    }

    public function test_unregister_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/device-tokens', [
            'token' => 'test-token',
        ])->assertUnauthorized();
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/device-tokens')
            ->assertUnauthorized();
    }

    public function test_unregister_removes_only_current_user_token(): void
    {
        DeviceToken::query()->create([
            'employee_id' => $this->employee->id,
            'company_id' => $this->company->id,
            'token' => 'employee-fcm-token',
            'platform' => 'android',
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        DeviceToken::query()->create([
            'employee_id' => $this->manager->id,
            'company_id' => $this->company->id,
            'token' => 'manager-fcm-token',
            'platform' => 'android',
            'is_active' => true,
            'last_used_at' => now(),
        ]);

        $this->actingAs($this->employee)
            ->deleteJson('/api/v1/device-tokens', [
                'token' => 'employee-fcm-token',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Device token removed.');

        $this->assertDatabaseMissing('device_tokens', [
            'employee_id' => $this->employee->id,
            'token' => 'employee-fcm-token',
        ]);
        $this->assertDatabaseHas('device_tokens', [
            'employee_id' => $this->manager->id,
            'token' => 'manager-fcm-token',
        ]);
    }

    public function test_send_test_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/push-notifications/send', [
                'employee_id' => $this->employee->id,
                'title' => 'Test',
                'body' => 'Test notification',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_send_test_notification_to_company_employee(): void
    {
        $this->actingAs($this->manager)
            ->postJson('/api/v1/push-notifications/send', [
                'employee_id' => $this->employee->id,
                'title' => 'Tache du jour',
                'body' => 'Nouvelle tache disponible.',
            ])
            ->assertOk()
            ->assertJsonPath('data.employee_id', $this->employee->id)
            ->assertJsonPath('data.results.app', 'sent');

        $this->assertDatabaseHas('notifications', [
            'employee_id' => $this->employee->id,
            'title' => 'Tache du jour',
            'body' => 'Nouvelle tache disponible.',
        ]);
    }
}
