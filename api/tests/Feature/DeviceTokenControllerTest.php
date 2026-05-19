<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
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
        $this->postJson('/api/v1/device-tokens/register', [
            'token' => 'test-fcm-token',
            'platform' => 'android',
        ])->assertUnauthorized();
    }

    public function test_register_validates_platform(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens/register', [
                'token' => 'test-token',
                'platform' => 'invalid',
            ])
            ->assertUnprocessable();
    }

    public function test_register_requires_token_and_platform(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens/register', [])
            ->assertUnprocessable();
    }

    public function test_unregister_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/device-tokens/unregister', [
            'token' => 'test-token',
        ])->assertUnauthorized();
    }

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/v1/device-tokens')
            ->assertUnauthorized();
    }

    public function test_send_test_requires_manager_role(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/device-tokens/send-test', [
                'title' => 'Test',
                'body' => 'Test notification',
            ])
            ->assertForbidden();
    }
}
