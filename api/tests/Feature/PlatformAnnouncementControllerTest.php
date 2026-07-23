<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-005 — Super-admin broadcasts a platform-wide announcement to
 * every company, or to an explicit subset of companies.
 */
class PlatformAnnouncementControllerTest extends TestCase
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

    private function actingAsSuperAdmin(): SuperAdmin
    {
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@leopardo.test',
            'password_hash' => Hash::make('password123'),
        ]);

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        return $superAdmin;
    }

    public function test_super_admin_can_broadcast_to_every_company_and_notifications_fan_out(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $companyOne = Company::factory()->create();
        $companyTwo = Company::factory()->create();
        $suspendedCompany = Company::factory()->create(['status' => 'suspended']);

        $employeeOne = Employee::factory()->create(['company_id' => $companyOne->id, 'status' => 'active']);
        $employeeTwo = Employee::factory()->create(['company_id' => $companyTwo->id, 'status' => 'active']);
        $suspendedCompanyEmployee = Employee::factory()->create(['company_id' => $suspendedCompany->id, 'status' => 'active']);

        $response = $this->postJson('/api/v1/platform/announcements', [
            'title' => 'Scheduled maintenance',
            'body' => 'The platform will be down for maintenance Friday 22:00-23:00 UTC.',
            'category' => 'maintenance',
            'severity' => 'high',
            'audience_type' => 'all',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Scheduled maintenance')
            ->assertJsonPath('data.category', 'maintenance')
            ->assertJsonPath('data.audience_type', 'all')
            ->assertJsonPath('data.companies_count', 2);

        $this->assertDatabaseHas('platform_announcements', [
            'created_by' => $superAdmin->id,
            'title' => 'Scheduled maintenance',
            'audience_type' => 'all',
        ]);

        $this->assertSame(1, Notification::query()->where('employee_id', $employeeOne->id)->count());
        $this->assertSame(1, Notification::query()->where('employee_id', $employeeTwo->id)->count());
        // Suspended companies are excluded from an "all companies" broadcast.
        $this->assertSame(0, Notification::query()->where('employee_id', $suspendedCompanyEmployee->id)->count());
    }

    public function test_super_admin_can_target_a_specific_subset_of_companies(): void
    {
        $this->actingAsSuperAdmin();

        $targetCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();

        $targetEmployee = Employee::factory()->create(['company_id' => $targetCompany->id, 'status' => 'active']);
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id, 'status' => 'active']);

        $response = $this->postJson('/api/v1/platform/announcements', [
            'title' => 'New feature available',
            'body' => 'Check out the new payroll export in your dashboard.',
            'category' => 'feature',
            'audience_type' => 'companies',
            'company_ids' => [$targetCompany->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.audience_type', 'companies')
            ->assertJsonPath('data.companies_count', 1)
            ->assertJsonPath('data.company_ids.0', $targetCompany->id);

        $this->assertSame(1, Notification::query()->where('employee_id', $targetEmployee->id)->count());
        $this->assertSame(0, Notification::query()->where('employee_id', $otherEmployee->id)->count());
    }

    public function test_targeted_broadcast_requires_at_least_one_company_id(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/platform/announcements', [
            'title' => 'Incident update',
            'body' => 'We are investigating a partial outage.',
            'category' => 'incident',
            'audience_type' => 'companies',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['company_ids']);
    }

    public function test_non_super_admin_cannot_broadcast(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/platform/announcements', [
            'title' => 'Should be forbidden',
            'body' => 'Employees cannot broadcast platform-wide.',
        ])->assertUnauthorized();
    }

    public function test_index_lists_announcements_and_destroy_removes_one(): void
    {
        $this->actingAsSuperAdmin();

        Company::factory()->create();

        $create = $this->postJson('/api/v1/platform/announcements', [
            'title' => 'Action required',
            'body' => 'Please update your billing details before month end.',
            'category' => 'action_required',
        ])->assertCreated();

        $announcementId = $create->json('data.id');

        $this->getJson('/api/v1/platform/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcementId);

        $this->deleteJson("/api/v1/platform/announcements/{$announcementId}")
            ->assertOk();

        $this->assertDatabaseMissing('platform_announcements', ['id' => $announcementId]);
    }
}
