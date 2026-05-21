<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ClientEventControllerTest extends TestCase
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

    public function test_authenticated_client_can_store_tenant_scoped_ux_event(): void
    {
        [$company, $manager] = $this->tenantFixture();
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/client-events', [
            'name' => 'dashboard_loaded',
            'surface' => 'web',
            'duration_ms' => 842,
            'properties' => [
                'role' => 'manager',
                'company_id' => $company->id,
                'active_modules' => 6,
                'locked_modules' => 2,
                'email' => 'should-not-be-stored@example.com',
                'unexpected_nested' => ['x' => 'drop'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'dashboard_loaded')
            ->assertJsonPath('data.stored', true);

        $event = DB::table('client_events')->where('event_name', 'dashboard_loaded')->first();

        $this->assertNotNull($event);
        $this->assertSame($company->id, $event->company_id);
        $this->assertSame($manager->id, (int) $event->employee_id);
        $this->assertSame(842, (int) $event->duration_ms);

        $properties = json_decode((string) $event->properties, true);
        $this->assertSame('manager', $properties['role']);
        $this->assertSame(6, $properties['active_modules']);
        $this->assertArrayNotHasKey('email', $properties);
        $this->assertArrayNotHasKey('unexpected_nested', $properties);
    }

    public function test_client_events_require_authentication(): void
    {
        $this->postJson('/api/v1/client-events', [
            'name' => 'dashboard_loaded',
        ])->assertUnauthorized();
    }

    public function test_client_event_name_is_allowlisted(): void
    {
        [, $manager] = $this->tenantFixture();
        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/client-events', [
            'name' => 'login_failed',
            'surface' => 'web',
        ])->assertUnprocessable();
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function tenantFixture(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return [$company, $manager];
    }
}
