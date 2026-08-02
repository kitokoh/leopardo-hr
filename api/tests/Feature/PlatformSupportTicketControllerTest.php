<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Domain\Models\PlatformSupportTicket;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-012 — Pilot client support center: a tenant manager/employee can
 * open a support ticket and reply on it; a super-admin can see every
 * tenant's tickets, reply, and triage (status/priority/assignment).
 */
class PlatformSupportTicketControllerTest extends TestCase
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

    public function test_employee_can_open_a_support_ticket_and_see_it_listed(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Payroll export fails',
            'category' => 'technical',
            'priority' => 'high',
            'message' => 'The payroll export button returns a 500 error since this morning.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', 'Payroll export fails')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.messages.0.from_platform', false);

        $this->assertDatabaseHas('platform_support_tickets', [
            'company_id' => $company->id,
            'created_by_employee_id' => $employee->id,
            'subject' => 'Payroll export fails',
        ]);

        $this->getJson('/api/v1/support-tickets')
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Payroll export fails')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_employee_cannot_see_another_companys_ticket(): void
    {
        $companyOne = Company::factory()->create();
        $companyTwo = Company::factory()->create();
        $employeeOne = Employee::factory()->manager()->create(['company_id' => $companyOne->id]);
        $employeeTwo = Employee::factory()->manager()->create(['company_id' => $companyTwo->id]);

        Sanctum::actingAs($employeeOne);
        $ticketId = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Private ticket',
            'category' => 'general',
            'message' => 'Only my company should see this.',
        ])->json('data.id');

        Sanctum::actingAs($employeeTwo);
        $this->getJson("/api/v1/support-tickets/{$ticketId}")->assertNotFound();
    }

    public function test_super_admin_can_see_and_triage_a_ticket_from_any_company(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
        Sanctum::actingAs($employee);

        $ticketId = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Billing question',
            'category' => 'billing',
            'message' => 'Why was I charged twice this month?',
        ])->json('data.id');

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/support-tickets')
            ->assertOk()
            ->assertJsonFragment(['subject' => 'Billing question'])
            ->assertJsonPath('meta.status_counts.open', 1);

        $reply = $this->postJson("/api/v1/platform/support-tickets/{$ticketId}/reply", [
            'message' => 'We are looking into the duplicate charge, refund incoming.',
        ]);
        $reply->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.messages.1.from_platform', true);

        $triage = $this->patchJson("/api/v1/platform/support-tickets/{$ticketId}/triage", [
            'status' => 'resolved',
            'priority' => 'low',
            'assign_to_me' => true,
        ]);
        $triage->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.priority', 'low')
            ->assertJsonPath('data.assigned_super_admin.id', $superAdmin->id);

        $this->assertDatabaseHas('platform_support_tickets', [
            'id' => $ticketId,
            'status' => PlatformSupportTicket::STATUS_RESOLVED,
            'priority' => PlatformSupportTicket::PRIORITY_LOW,
            'assigned_super_admin_id' => $superAdmin->id,
        ]);
    }

    public function test_super_admin_can_scope_ticket_list_to_one_company(): void
    {
        // PA2-ADM-003 — the company detail page's "Support" panel calls
        // GET /platform/support-tickets?company_id=... to show only that
        // client's tickets; make sure the filter actually scopes the list
        // and does not leak other companies' tickets.
        $superAdmin = $this->actingAsSuperAdmin();

        $companyA = Company::factory()->create();
        $employeeA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        Sanctum::actingAs($employeeA);
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Company A ticket',
            'category' => 'general',
            'message' => 'Issue reported by company A.',
        ])->assertCreated();

        $companyB = Company::factory()->create();
        $employeeB = Employee::factory()->manager()->create(['company_id' => $companyB->id]);
        Sanctum::actingAs($employeeB);
        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Company B ticket',
            'category' => 'general',
            'message' => 'Issue reported by company B.',
        ])->assertCreated();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $response = $this->getJson("/api/v1/platform/support-tickets?company_id={$companyA->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Company A ticket')
            ->assertJsonPath('data.0.company.id', $companyA->id);
    }

    public function test_employee_reply_reopens_a_pending_ticket(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);
        $ticketId = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Login issue',
            'category' => 'technical',
            'message' => 'Cannot log in on mobile.',
        ])->json('data.id');

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
        $this->postJson("/api/v1/platform/support-tickets/{$ticketId}/reply", [
            'message' => 'Can you confirm the app version?',
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/support-tickets/{$ticketId}/reply", [
            'message' => 'Version 2.4.1.',
        ])->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_employee_cannot_reply_on_a_closed_ticket(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();
        $company = Company::factory()->create();
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);
        $ticketId = $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Closed ticket test',
            'category' => 'other',
            'message' => 'Initial message.',
        ])->json('data.id');

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
        $this->patchJson("/api/v1/platform/support-tickets/{$ticketId}/triage", [
            'status' => 'closed',
        ])->assertOk();

        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/support-tickets/{$ticketId}/reply", [
            'message' => 'Are you still there?',
        ])->assertUnprocessable();
    }
}
