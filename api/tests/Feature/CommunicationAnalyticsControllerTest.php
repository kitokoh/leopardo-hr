<?php

namespace Tests\Feature;

use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CommunicationAnalyticsControllerTest extends TestCase
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

    public function test_hr_manager_can_view_tenant_scoped_communication_analytics(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
        ]);
        $otherCompany = Company::factory()->create();
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);

        CommunicationEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'email',
            'status' => 'queued',
            'provider' => 'mail',
            'template_key' => 'payroll_ready',
            'occurred_at' => now(),
        ]);
        CommunicationEvent::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'sms',
            'status' => 'failed',
            'provider' => 'audit',
            'template_key' => 'absence_approved',
            'occurred_at' => now(),
        ]);
        CommunicationEvent::query()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'whatsapp',
            'status' => 'queued',
            'provider' => 'audit',
            'template_key' => 'security_alert',
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/communication/analytics?days=30')
            ->assertOk()
            ->assertJsonPath('data.totals.events', 2)
            ->assertJsonPath('data.totals.sent_or_queued', 1)
            ->assertJsonPath('data.totals.failed', 1);

        $this->assertContains(['key' => 'email', 'count' => 1], $response->json('data.by_channel'));
        $this->assertContains(['key' => 'sms', 'count' => 1], $response->json('data.by_channel'));
    }

    public function test_employee_cannot_view_communication_analytics(): void
    {
        Sanctum::actingAs(Employee::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'role' => 'employee',
        ]));

        $this->getJson('/api/v1/communication/analytics')
            ->assertForbidden();
    }
}
