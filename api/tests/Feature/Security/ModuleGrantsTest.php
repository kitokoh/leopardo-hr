<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #7761 (délégation d'accès, spec MISSION_ESPACE_CLIENT §3.1) — grants
 * de MODULES composables par collaborateur :
 *
 *  - GET/PUT /employees/{id}/module-grants réservés au principal du tenant ;
 *  - registre fermé `ModuleKey` (clé inconnue → 422, fail-closed) ;
 *  - enforcement middleware : manager_role autorisé OU grant explicite
 *    (`api.manager:...,module:<key>`), grant OU manager pour les tickets
 *    support (`api.module.grant:support`) ;
 *  - isolation multi-tenant : un grant ne voyage jamais entre sociétés ;
 *  - révocation effective en un geste (PUT remplace le jeu complet).
 */
class ModuleGrantsTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La table de cette tranche n'est pas dans le schéma MVP : on exécute
        // la migration réelle (convention du dépôt, cf. ResourceScopedRbacTest).
        $migration = require database_path('migrations/tenant/2026_09_19_000001_7761_create_employee_module_grants.php');
        $migration->up();

        $this->company = Company::factory()->create();
        $this->principal = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeEmployee(?string $companyId = null): Employee
    {
        return Employee::factory()->create(['company_id' => $companyId ?? $this->company->id]);
    }

    private function grant(Employee $employee, string $moduleKey): void
    {
        $grant = new EmployeeModuleGrant([
            'employee_id' => $employee->id,
            'module_key' => $moduleKey,
        ]);
        $grant->company_id = (string) $employee->company_id;
        $grant->granted_by_employee_id = $this->principal->id;
        $grant->save();
    }

    // ── 1. API GET/PUT — principal uniquement ────────────────────────────────

    public function test_principal_composes_module_grants_via_put(): void
    {
        $employee = $this->makeEmployee();

        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$employee->id}/module-grants", [
            'module_keys' => ['marketing', 'accounting', 'support'],
        ])
            ->assertOk()
            ->assertJsonPath('data.module_keys', ['accounting', 'marketing', 'support']);

        $this->getJson("/api/v1/employees/{$employee->id}/module-grants")
            ->assertOk()
            ->assertJsonPath('data.module_keys', ['accounting', 'marketing', 'support']);

        $this->assertDatabaseHas('employee_module_grants', [
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'module_key' => 'marketing',
            'granted_by_employee_id' => $this->principal->id,
        ]);
    }

    public function test_non_principal_roles_are_refused(): void
    {
        $employee = $this->makeEmployee();
        $rh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($rh);
        $this->getJson("/api/v1/employees/{$employee->id}/module-grants")->assertStatus(403);
        $this->putJson("/api/v1/employees/{$employee->id}/module-grants", ['module_keys' => []])->assertStatus(403);

        Sanctum::actingAs($this->makeEmployee());
        $this->getJson("/api/v1/employees/{$employee->id}/module-grants")->assertStatus(403);
    }

    public function test_unknown_module_key_is_rejected(): void
    {
        $employee = $this->makeEmployee();

        Sanctum::actingAs($this->principal);

        $this->putJson("/api/v1/employees/{$employee->id}/module-grants", [
            'module_keys' => ['warp_drive'],
        ])->assertStatus(422);
    }

    // ── 2. Enforcement — support-tickets : grant OU manager ─────────────────

    public function test_employee_without_support_grant_is_refused_on_support_tickets(): void
    {
        Sanctum::actingAs($this->makeEmployee());

        $this->getJson('/api/v1/support-tickets')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MODULE_ACCESS_REQUIRED');
    }

    public function test_employee_with_support_grant_uses_support_tickets(): void
    {
        $employee = $this->makeEmployee();
        $this->grant($employee, 'support');

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/support-tickets', [
            'subject' => 'Imprimante badgeuse en panne',
            'category' => 'technical',
            'message' => 'La badgeuse du site Almadies ne répond plus.',
        ])->assertCreated();

        $this->getJson('/api/v1/support-tickets')->assertOk();
    }

    public function test_managers_keep_historic_support_tickets_access_without_grant(): void
    {
        Sanctum::actingAs($this->principal);
        $this->getJson('/api/v1/support-tickets')->assertOk();

        $rh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($rh);
        $this->getJson('/api/v1/support-tickets')->assertOk();
    }

    // ── 3. Enforcement — api.manager:…,module:<key> : rôle OU grant ─────────

    public function test_employee_without_marketing_grant_is_refused_on_marketing_routes(): void
    {
        Sanctum::actingAs($this->makeEmployee());

        $this->getJson('/api/v1/marketing/social-account')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MANAGER_REQUIRED');
    }

    public function test_employee_with_marketing_grant_passes_marketing_middleware(): void
    {
        $employee = $this->makeEmployee();
        $this->grant($employee, 'marketing');

        Sanctum::actingAs($employee);

        // Le middleware passe (plus de 403 rôle) : sans compte social connecté,
        // le contrôleur répond 404 SOCIAL_ACCOUNT_NOT_FOUND — la preuve que
        // l'accès module est ouvert par le grant.
        $this->getJson('/api/v1/marketing/social-account')
            ->assertStatus(404)
            ->assertJsonPath('error', 'SOCIAL_ACCOUNT_NOT_FOUND');
    }

    public function test_wrong_manager_role_without_grant_stays_refused(): void
    {
        $rh = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);

        Sanctum::actingAs($rh);

        $this->getJson('/api/v1/marketing/social-account')
            ->assertStatus(403)
            ->assertJsonPath('error', 'INSUFFICIENT_ROLE');
    }

    // ── 4. Isolation multi-tenant ────────────────────────────────────────────

    public function test_module_grants_never_cross_tenants(): void
    {
        $otherCompany = Company::factory()->create();
        $otherPrincipal = Employee::factory()->manager()->create(['company_id' => $otherCompany->id]);

        $employee = $this->makeEmployee();
        $this->grant($employee, 'support');

        // Le principal d'une AUTRE société ne lit ni n'écrit la composition.
        Sanctum::actingAs($otherPrincipal);
        $this->getJson("/api/v1/employees/{$employee->id}/module-grants")->assertStatus(403);
        $this->putJson("/api/v1/employees/{$employee->id}/module-grants", ['module_keys' => []])->assertStatus(403);

        // Un grant posé dans la société A n'ouvre rien à un employé de B.
        $otherEmployee = $this->makeEmployee($otherCompany->id);
        Sanctum::actingAs($otherEmployee);
        $this->getJson('/api/v1/support-tickets')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MODULE_ACCESS_REQUIRED');
    }

    // ── 5. Révocation effective (PUT = remplacement) ─────────────────────────

    public function test_revoking_a_grant_takes_effect_immediately(): void
    {
        $employee = $this->makeEmployee();
        $this->grant($employee, 'support');

        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/support-tickets')->assertOk();

        Sanctum::actingAs($this->principal);
        $this->putJson("/api/v1/employees/{$employee->id}/module-grants", ['module_keys' => []])
            ->assertOk()
            ->assertJsonPath('data.module_keys', []);

        $this->assertDatabaseMissing('employee_module_grants', [
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
        ]);

        $employee->refresh();
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/support-tickets')
            ->assertStatus(403)
            ->assertJsonPath('error', 'MODULE_ACCESS_REQUIRED');
    }

    // ── 6. Contrat de session /auth/me ───────────────────────────────────────

    public function test_me_exposes_own_module_grants(): void
    {
        $employee = $this->makeEmployee();
        $this->grant($employee, 'support');
        $this->grant($employee, 'accounting');

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.module_grants', ['accounting', 'support']);
    }
}
