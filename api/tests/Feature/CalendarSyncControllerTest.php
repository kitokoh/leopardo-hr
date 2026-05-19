<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CalendarSyncControllerTest extends TestCase
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

    public function test_connections_requires_authentication(): void
    {
        $this->getJson('/api/v1/calendar/connections')
            ->assertUnauthorized();
    }

    public function test_connections_returns_empty_for_new_user(): void
    {
        $this->actingAs($this->employee)
            ->getJson('/api/v1/calendar/connections')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_connect_requires_provider(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/calendar/connect', [])
            ->assertUnprocessable();
    }

    public function test_connect_validates_provider_enum(): void
    {
        $this->actingAs($this->employee)
            ->postJson('/api/v1/calendar/connect', [
                'provider' => 'invalid',
                'access_token' => 'token123',
            ])
            ->assertUnprocessable();
    }

    public function test_disconnect_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/calendar/disconnect/999')
            ->assertUnauthorized();
    }

    public function test_events_requires_authentication(): void
    {
        $this->getJson('/api/v1/calendar/events')
            ->assertUnauthorized();
    }
}
