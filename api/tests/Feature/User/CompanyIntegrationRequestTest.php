<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5540 — Onboarding personnel multi-statuts : demandes d'intégration.
 *
 * Côté utilisateur :
 *   POST /user/company-integration-requests   (créer une demande)
 *   GET  /user/company-integration-requests   (lister ses demandes)
 *   PATCH /user/personal-statuses             (statuts cumulables)
 *   GET  /user/companies/search               (recherche d'entreprise)
 *
 * Côté manager :
 *   GET  /company-integration-requests        (demandes du tenant)
 *   POST /company-integration-requests/{id}/accept
 *   POST /company-integration-requests/{id}/reject
 */
class CompanyIntegrationRequestTest extends TestCase
{
    use RefreshTenantDatabase;

    // ── USER-SIDE ──────────────────────────────────────────────────────────

    public function test_user_can_submit_integration_request(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Global']);
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $response = $this->postJson('/api/v1/user/company-integration-requests', [
            'target_company_id' => (string) $company->id,
            'target_company_name' => 'Acme Global',
            'message' => 'Je souhaite rejoindre votre équipe.',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'integration')
            ->assertJsonPath('data.target_company_id', (string) $company->id)
            ->assertJsonPath('data.target_company_name', 'Acme Global')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'created_at']]);

        $this->assertDatabaseHas('company_requests', [
            'user_id' => $user->id,
            'type' => 'integration',
            'target_company_id' => (string) $company->id,
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_pending_request_is_rejected_with_409(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $payload = [
            'target_company_id' => (string) $company->id,
            'target_company_name' => 'Acme',
        ];

        $this->postJson('/api/v1/user/company-integration-requests', $payload)->assertStatus(201);

        $this->postJson('/api/v1/user/company-integration-requests', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error', 'INTEGRATION_REQUEST_ALREADY_PENDING');
    }

    public function test_user_can_list_own_integration_requests(): void
    {
        $company = Company::factory()->create(['name' => 'Beta Corp']);
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $this->postJson('/api/v1/user/company-integration-requests', [
            'target_company_id' => (string) $company->id,
            'target_company_name' => 'Beta Corp',
        ])->assertStatus(201);

        $this->getJson('/api/v1/user/company-integration-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target_company_name', 'Beta Corp')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_integration_request_requires_valid_uuid(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $this->postJson('/api/v1/user/company-integration-requests', [
            'target_company_id' => 'not-a-uuid',
            'target_company_name' => 'Acme',
        ])->assertStatus(422);
    }

    public function test_user_can_update_personal_statuses(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $this->patchJson('/api/v1/user/personal-statuses', [
            'statuses' => ['student', 'employee'],
        ])
            ->assertOk()
            ->assertJsonPath('data.personal_statuses', ['student', 'employee']);

        $this->assertSame(['student', 'employee'], $user->fresh()->personal_statuses);
    }

    public function test_user_can_reset_personal_statuses_to_empty(): void
    {
        $user = User::factory()->create(['personal_statuses' => ['entrepreneur']]);

        Sanctum::actingAs($user, [], 'user_api');

        $this->patchJson('/api/v1/user/personal-statuses', ['statuses' => []])
            ->assertOk()
            ->assertJsonPath('data.personal_statuses', []);
    }

    public function test_invalid_personal_status_is_rejected(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $this->patchJson('/api/v1/user/personal-statuses', [
            'statuses' => ['astronaut'],
        ])->assertStatus(422);
    }

    public function test_user_can_search_companies_by_name(): void
    {
        Company::factory()->create(['name' => 'Acme Global', 'city' => 'Paris']);
        Company::factory()->create(['name' => 'Globex', 'city' => 'Lyon']);
        $user = User::factory()->create();

        Sanctum::actingAs($user, [], 'user_api');

        $this->getJson('/api/v1/user/companies/search?q=acme')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Global')
            ->assertJsonPath('data.0.city', 'Paris');
    }

    // ── MANAGER-SIDE ───────────────────────────────────────────────────────

    public function test_non_manager_cannot_list_integration_requests(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        // Le middleware api.manager intercepte avant le contrôleur (famille #3150)
        $this->getJson('/api/v1/company-integration-requests')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_manager_lists_pending_integration_requests_for_own_tenant(): void
    {
        $company = Company::factory()->create(['name' => 'Acme']);
        $otherCompany = Company::factory()->create(['name' => 'Autre']);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['first_name' => 'Lina', 'last_name' => 'Kacem']);

        // Demande pour le tenant du manager
        $this->createIntegrationRequest($user, $company);
        // Demande pour un autre tenant (ne doit pas apparaître)
        $this->createIntegrationRequest(User::factory()->create(), $otherCompany);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/company-integration-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.email', $user->email)
            ->assertJsonPath('data.0.user.full_name', 'Lina Kacem')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_manager_accept_creates_user_employee_link(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee@acme.test',
        ]);
        $user = User::factory()->create(['email' => 'applicant@mail.test']);
        $request = $this->createIntegrationRequest($user, $company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/company-integration-requests/{$request->id}/accept", [
            'employee_id' => $employee->id,
            'admin_notes' => 'Bienvenue !',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.employee_id', $employee->id);

        $this->assertDatabaseHas('user_employee_links', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('company_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'approved_company_id' => $company->id,
        ]);
    }

    public function test_manager_cannot_accept_with_employee_of_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $foreignEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        $user = User::factory()->create();
        $request = $this->createIntegrationRequest($user, $company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/company-integration-requests/{$request->id}/accept", [
            'employee_id' => $foreignEmployee->id,
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'EMPLOYEE_NOT_FOUND');
    }

    public function test_accept_when_link_already_exists_is_idempotent(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create();
        $request = $this->createIntegrationRequest($user, $company);

        UserEmployeeLink::create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'company_id' => $company->id,
            'status' => 'active',
            'linked_at' => now(),
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/company-integration-requests/{$request->id}/accept", [
            'employee_id' => $employee->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'already_linked');

        $this->assertDatabaseHas('company_requests', [
            'id' => $request->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseCount('user_employee_links', 1);
    }

    public function test_manager_rejects_integration_request(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $user = User::factory()->create();
        $request = $this->createIntegrationRequest($user, $company);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/company-integration-requests/{$request->id}/reject", [
            'admin_notes' => 'Profil ne correspond pas.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('company_requests', [
            'id' => $request->id,
            'status' => 'rejected',
            'admin_notes' => 'Profil ne correspond pas.',
        ]);
    }

    public function test_accept_reviewed_request_returns_404(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $user = User::factory()->create();
        $request = $this->createIntegrationRequest($user, $company, 'rejected');

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/company-integration-requests/{$request->id}/accept", [
            'employee_id' => 1,
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'NOT_FOUND');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function createIntegrationRequest(User $user, Company $company, string $status = 'pending'): CompanyRequest
    {
        return $user->companyRequests()->create([
            'type' => 'integration',
            'target_company_id' => (string) $company->id,
            'company_name' => $company->name,
            'email' => $user->email,
            'status' => $status,
            // Colonnes NOT NULL historiques (voir store() du contrôleur)
            'country' => substr((string) ($company->country ?? 'NA'), 0, 2),
            'city' => (string) ($company->city ?? ''),
        ]);
    }
}
