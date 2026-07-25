<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-ADM-005 — Monitoring plateforme lisible.
 *
 * Covers GET /api/v1/platform/observability/notifications: cross-tenant
 * outbound notification failure rate (24h window), grouped by channel,
 * most recent failures, and the curated runbook links surfaced on the
 * super-admin "System" screen alongside the existing queue observability
 * endpoint (PA2-QA-006).
 */
class PlatformNotificationObservabilityApiTest extends TestCase
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

    public function test_requires_super_admin_authentication(): void
    {
        $response = $this->getJson('/api/v1/platform/observability/notifications');

        $response->assertUnauthorized();
    }

    public function test_super_admin_can_view_cross_tenant_notification_failures_and_runbooks(): void
    {
        $companyA = Company::factory()->create(['name' => 'Atlas Corp']);
        $companyB = Company::factory()->create(['name' => 'Nova Retail']);
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);

        CommunicationEvent::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'email',
            'status' => 'sent',
            'template_key' => 'payroll_ready',
            'occurred_at' => now(),
        ]);
        CommunicationEvent::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'sms',
            'status' => 'failed',
            'template_key' => 'absence_approved',
            'error_message' => 'Provider timeout after 30s',
            'occurred_at' => now(),
        ]);
        CommunicationEvent::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'whatsapp',
            'status' => 'failed',
            'template_key' => 'security_alert',
            'error_message' => 'Invalid phone number',
            'occurred_at' => now(),
        ]);
        // Outside the 24h lookback window: must not be counted.
        CommunicationEvent::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'email',
            'status' => 'failed',
            'template_key' => 'welcome',
            'occurred_at' => now()->subDays(3),
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/observability/notifications');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'window_hours',
                'companies_scanned',
                'totals' => ['events', 'failed', 'failure_rate'],
                'by_channel',
                'recent_failures',
                'runbooks',
                'alerts' => ['notification_failures'],
                'thresholds' => ['notification_failures'],
                'generated_at',
            ],
        ]);

        $response->assertJsonPath('data.totals.events', 3);
        $response->assertJsonPath('data.totals.failed', 2);

        $byChannel = collect($response->json('data.by_channel'));
        $this->assertSame(1, $byChannel->firstWhere('channel', 'sms')['failed']);
        $this->assertSame(1, $byChannel->firstWhere('channel', 'whatsapp')['failed']);

        $recentCompanies = collect($response->json('data.recent_failures'))->pluck('company_name');
        $this->assertTrue($recentCompanies->contains('Atlas Corp'));
        $this->assertTrue($recentCompanies->contains('Nova Retail'));

        $runbookKeys = collect($response->json('data.runbooks'))->pluck('key');
        $this->assertTrue($runbookKeys->contains('incident_p1'));
        $this->assertTrue($runbookKeys->contains('observability'));
    }

    public function test_failure_count_above_threshold_flips_alert(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        for ($i = 0; $i < 12; $i++) {
            CommunicationEvent::query()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'event_name' => 'communication_dispatched',
                'channel' => 'email',
                'status' => 'failed',
                'template_key' => 'payroll_ready',
                'occurred_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/observability/notifications');

        $response->assertOk();
        $response->assertJsonPath('data.alerts.notification_failures', true);
        // Dashboard summary is capped even when more failures exist.
        $this->assertCount(10, $response->json('data.recent_failures'));
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => Hash::make('password123'),
        ]);
    }
}
