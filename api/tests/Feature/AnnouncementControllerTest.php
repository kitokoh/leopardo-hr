<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AnnouncementControllerTest extends TestCase
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

    public function test_principal_can_broadcast_to_whole_company_and_notifications_fan_out(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employeeOne = Employee::factory()->create(['company_id' => $company->id]);
        $employeeTwo = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $response = $this->postJson('/api/v1/announcements', [
            'title' => 'Company picnic',
            'body' => 'Join us Friday at noon.',
            'priority' => 'normal',
            'audience_type' => 'company',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Company picnic')
            ->assertJsonPath('data.audience_type', 'company')
            ->assertJsonPath('data.recipients_count', 2);

        $this->assertDatabaseHas('company_announcements', [
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'audience_type' => 'company',
        ]);

        $this->assertSame(1, Notification::query()->where('employee_id', $employeeOne->id)->count());
        $this->assertSame(1, Notification::query()->where('employee_id', $employeeTwo->id)->count());
        $this->assertSame(0, Notification::query()->where('employee_id', $principal->id)->count());
    }

    public function test_employee_cannot_broadcast_to_whole_company(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Not allowed',
            'body' => 'Should be rejected.',
            'audience_type' => 'company',
        ])->assertForbidden();
    }

    public function test_dept_manager_can_only_broadcast_to_own_department(): void
    {
        $company = Company::factory()->create();
        $deptManager = Employee::factory()->managerDept()->create(['company_id' => $company->id, 'department_id' => 1]);
        $sameDeptEmployee = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 1]);
        $otherDeptEmployee = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 2]);

        Sanctum::actingAs($deptManager);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Dept meeting',
            'body' => 'Team sync at 3pm.',
            'audience_type' => 'department',
            'audience_department_id' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.recipients_count', 1);

        $this->assertSame(1, Notification::query()->where('employee_id', $sameDeptEmployee->id)->count());
        $this->assertSame(0, Notification::query()->where('employee_id', $otherDeptEmployee->id)->count());

        $this->postJson('/api/v1/announcements', [
            'title' => 'Wrong dept',
            'body' => 'Should fail.',
            'audience_type' => 'department',
            'audience_department_id' => 2,
        ])->assertUnprocessable();
    }

    public function test_manager_can_target_a_single_employee_on_their_team(): void
    {
        $company = Company::factory()->create();
        $supervisor = Employee::factory()->create(['company_id' => $company->id, 'role' => 'manager', 'manager_role' => 'superviseur']);
        $directReport = Employee::factory()->create(['company_id' => $company->id, 'manager_id' => $supervisor->id]);
        $unrelatedEmployee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Direct note',
            'body' => 'Great job this week.',
            'audience_type' => 'employee',
            'audience_employee_id' => $directReport->id,
        ])->assertCreated()
            ->assertJsonPath('data.recipients_count', 1);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Not my report',
            'body' => 'Should fail.',
            'audience_type' => 'employee',
            'audience_employee_id' => $unrelatedEmployee->id,
        ])->assertUnprocessable();
    }

    public function test_company_wide_manager_role_cannot_target_arbitrary_employee_outside_their_team(): void
    {
        $company = Company::factory()->create();
        // comptable/marketing managers are company-wide roles but are NOT
        // team-scoped (isDept()/isSuperviseur() are both false), so they
        // must not be able to single out an arbitrary employee via the
        // default managesTeamMemberOf() === true fallback.
        $comptable = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'comptable',
        ]);
        $unrelatedEmployee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($comptable);

        $this->postJson('/api/v1/announcements', [
            'title' => 'Not allowed',
            'body' => 'Comptable is not team-scoped.',
            'audience_type' => 'employee',
            'audience_employee_id' => $unrelatedEmployee->id,
        ])->assertUnprocessable();
    }

    public function test_index_scopes_announcements_by_audience_and_authorship(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 1]);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 2]);

        $companyWide = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Company wide',
            'body' => 'Visible to everyone.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'published_at' => now(),
        ]);

        CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Other department',
            'body' => 'Not for this employee.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_DEPARTMENT,
            'audience_department_id' => $otherEmployee->department_id,
            'published_at' => now(),
        ]);

        $ownDept = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'My department',
            'body' => 'For this employee dept.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_DEPARTMENT,
            'audience_department_id' => $employee->department_id,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/announcements')->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains($companyWide->title, $titles);
        $this->assertContains($ownDept->title, $titles);
        $this->assertNotContains('Other department', $titles);
    }

    public function test_author_and_hr_manager_can_delete_announcement_but_others_cannot(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $hrManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'To delete',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($employee);
        $this->deleteJson("/api/v1/announcements/{$announcement->id}")->assertForbidden();

        Sanctum::actingAs($hrManager);
        $this->deleteJson("/api/v1/announcements/{$announcement->id}")->assertOk();

        $this->assertDatabaseMissing('company_announcements', ['id' => $announcement->id]);
    }

    public function test_cross_tenant_announcement_is_not_deletable(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $principalA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $principalB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $companyB->id,
            'created_by' => $principalB->id,
            'title' => 'Other tenant',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($principalA);

        $this->deleteJson("/api/v1/announcements/{$announcement->id}")->assertNotFound();
    }

    public function test_manager_can_save_a_draft_announcement_without_fan_out(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $response = $this->postJson('/api/v1/announcements', [
            'title' => 'Draft picnic',
            'body' => 'Still being written.',
            'audience_type' => 'company',
            'status' => 'draft',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.recipients_count', 0);

        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement_draft',
            'auditable_type' => CompanyAnnouncement::class,
        ]);
    }

    public function test_manager_can_schedule_an_announcement_and_it_publishes_when_due(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($principal);

        $scheduledAt = now()->addHour();

        $response = $this->postJson('/api/v1/announcements', [
            'title' => 'Scheduled picnic',
            'body' => 'Will publish in an hour.',
            'audience_type' => 'company',
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.recipients_count', 0);

        $announcementId = $response->json('data.id');
        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());

        // Not due yet: the command must not publish it.
        $this->artisan('announcements:publish-scheduled')->assertSuccessful();
        $this->assertDatabaseHas('company_announcements', ['id' => $announcementId, 'status' => 'scheduled']);
        $this->assertSame(0, Notification::query()->where('employee_id', $employee->id)->count());

        // Move past the due time and run the command again.
        Carbon::setTestNow($scheduledAt->clone()->addMinute());
        $this->artisan('announcements:publish-scheduled')->assertSuccessful();
        Carbon::setTestNow();

        $this->assertDatabaseHas('company_announcements', ['id' => $announcementId, 'status' => 'published']);
        $this->assertSame(1, Notification::query()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'announcement_published',
            'auditable_id' => $announcementId,
            'auditable_type' => CompanyAnnouncement::class,
        ]);
    }

    public function test_author_can_publish_a_draft_announcement_now(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Draft to publish',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'status' => CompanyAnnouncement::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($principal);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.recipients_count', 1);

        $this->assertSame(1, Notification::query()->where('employee_id', $employee->id)->count());
    }

    public function test_author_can_cancel_a_scheduled_announcement_before_it_publishes(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Employee::factory()->create(['company_id' => $company->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Scheduled to cancel',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'status' => CompanyAnnouncement::STATUS_SCHEDULED,
            'scheduled_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($principal);

        $this->postJson("/api/v1/announcements/{$announcement->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by', $principal->id);

        $this->assertDatabaseHas('company_announcements', [
            'id' => $announcement->id,
            'status' => 'cancelled',
        ]);

        // The publish-scheduled command must skip cancelled rows.
        Carbon::setTestNow(now()->addHours(2));
        $this->artisan('announcements:publish-scheduled')->assertSuccessful();
        Carbon::setTestNow();

        $this->assertDatabaseHas('company_announcements', ['id' => $announcement->id, 'status' => 'cancelled']);
    }

    public function test_cannot_cancel_an_already_published_announcement(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Already live',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'status' => CompanyAnnouncement::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($principal);

        $this->postJson("/api/v1/announcements/{$announcement->id}/cancel")->assertUnprocessable();
    }

    public function test_employee_other_than_author_cannot_moderate_someone_elses_draft(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);

        $announcement = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Not yours',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'status' => CompanyAnnouncement::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($otherEmployee);

        $this->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertForbidden();
        $this->postJson("/api/v1/announcements/{$announcement->id}/cancel")->assertForbidden();
    }

    public function test_index_hides_other_authors_drafts_from_regular_employees_but_shows_them_to_hr(): void
    {
        $company = Company::factory()->create();
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $hrManager = Employee::factory()->managerRh()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $draft = CompanyAnnouncement::create([
            'company_id' => $company->id,
            'created_by' => $principal->id,
            'title' => 'Hidden draft',
            'body' => 'Body text.',
            'audience_type' => CompanyAnnouncement::AUDIENCE_COMPANY,
            'status' => CompanyAnnouncement::STATUS_DRAFT,
        ]);

        Sanctum::actingAs($employee);
        $titles = collect($this->getJson('/api/v1/announcements')->json('data'))->pluck('title')->all();
        $this->assertNotContains($draft->title, $titles);

        Sanctum::actingAs($hrManager);
        $titles = collect($this->getJson('/api/v1/announcements')->json('data'))->pluck('title')->all();
        $this->assertContains($draft->title, $titles);

        Sanctum::actingAs($principal);
        $titles = collect($this->getJson('/api/v1/announcements')->json('data'))->pluck('title')->all();
        $this->assertContains($draft->title, $titles);
    }
}
