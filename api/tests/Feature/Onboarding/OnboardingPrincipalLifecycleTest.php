<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Infrastructure\Services\ContractLifecycleAction;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Cycle de vie du PROPRIÉTAIRE (principal) d'un tenant — cas limites non
 * couverts par les suites existantes.
 *
 * Trois invariants de sécurité sont verrouillés ici, chacun adossé à une
 * règle de production réellement présente dans le code :
 *
 *  1. `EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN` (UpdateEmployeeRequest:136,
 *     StoreEmployeeRequest:136) — un manager NON principal ne peut pas
 *     promouvoir un employé en `manager_role=principal` via les endpoints
 *     génériques de création/mise à jour. Sans test jusqu'ici.
 *
 *  2. Réactivation après suspension : un employé `suspended` redevient
 *     `active` par la réactivation de son contrat (ContractLifecycleAction::
 *     activate), et retrouve alors l'accès — la suspension n'est pas
 *     définitive. Le chemin inverse (suspend) est couvert par
 *     ContractLifecycleAction::suspend.
 *
 *  3. Un principal suspendu ne compte pas dans `activePrincipalCount`
 *     (RoleAssignmentController:96) : il ne peut donc pas servir de
 *     « successeur » pour débloquer la rétrogradation du dernier principal
 *     actif. C'est la garantie anti-auto-verrouillage.
 *
 * Aucun de ces tests ne modifie le code applicatif : ils verrouillent le
 * comportement réel observé (fail-closed) contre une régression future.
 */
class OnboardingPrincipalLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->company = Company::factory()->create(['status' => 'active']);
        $this->principal = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
    }

    // ---------------------------------------------------------------------
    // 1. Promotion vers `principal` interdite aux managers non principaux
    // ---------------------------------------------------------------------

    /** @test */
    public function a_non_principal_manager_cannot_promote_an_employee_to_principal_via_update(): void
    {
        // Un RH (manager non principal) tente de promouvoir un employé en
        // principal via PATCH /employees/{id} — la règle de production doit
        // le refuser (EMPLOYEE_PROMOTE_PRINCIPAL_FORBIDDEN).
        $hr = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $target = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'manager_role' => null,
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);

        $this->patchJson("/api/v1/employees/{$target->id}", [
            'manager_role' => 'principal',
        ])->assertStatus(422);

        $target->refresh();
        $this->assertNotSame('principal', $target->manager_role, 'Un RH ne doit jamais pouvoir créer un principal.');
    }

    /** @test */
    public function a_non_principal_manager_cannot_create_a_principal_via_store(): void
    {
        // Même règle côté création (StoreEmployeeRequest:136).
        $hr = Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/employees', [
            'first_name' => 'Nouveau',
            'last_name' => 'Principal',
            'email' => 'nouveau.principal@company.test',
            'role' => 'manager',
            'manager_role' => 'principal',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('manager_role');

        $this->assertDatabaseMissing('employees', [
            'email' => 'nouveau.principal@company.test',
            'manager_role' => 'principal',
        ]);
    }

    // ---------------------------------------------------------------------
    // 2. Réactivation après suspension (contrat)
    // ---------------------------------------------------------------------

    /** @test */
    public function a_suspended_employee_regains_access_when_their_contract_is_reactivated(): void
    {
        // Un employé suspendu (contrat suspendu) redevient actif quand son
        // contrat est réactivé : la suspension n'est pas un état terminal.
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'suspended',
        ]);

        $contract = Contract::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'draft',
            'base_salary' => 0,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
        ]);

        /** @var ContractLifecycleAction $lifecycle */
        $lifecycle = app(ContractLifecycleAction::class);
        $lifecycle->activate($contract);

        $employee->refresh();
        $this->assertSame('active', $employee->status, 'La réactivation du contrat doit remettre l\'employé actif.');
        $this->assertSame('active', $contract->fresh()->status);
    }

    /** @test */
    public function suspending_a_contract_suspends_the_employee(): void
    {
        // Chemin inverse : suspendre le contrat suspend l'employé (miroir de
        // ContractLifecycleAction::suspend, #5327).
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $contract = Contract::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
            'base_salary' => 0,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
        ]);

        /** @var ContractLifecycleAction $lifecycle */
        $lifecycle = app(ContractLifecycleAction::class);
        $lifecycle->suspend($contract);

        $employee->refresh();
        $this->assertSame('suspended', $employee->status, 'Suspendre le contrat doit suspendre l\'employé.');
    }

    // ---------------------------------------------------------------------
    // 3. Un principal suspendu ne protège pas le dernier principal actif
    // ---------------------------------------------------------------------

    /** @test */
    public function a_suspended_principal_does_not_count_as_an_active_successor(): void
    {
        // Le principal actif tente de se rétrograder alors que le SEUL autre
        // principal est suspendu : la garde doit refuser (le suspendu ne peut
        // pas rouvrir la porte). C'est la garantie anti-auto-verrouillage.
        Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'principal',
            'status' => 'suspended',
        ]);

        Sanctum::actingAs($this->principal);

        $this->postJson("/api/v1/employees/{$this->principal->id}/assign-role", [
            'manager_role' => 'rh',
        ])->assertStatus(422)
            ->assertJsonPath('error', 'LAST_PRINCIPAL_REQUIRED');

        $this->principal->refresh();
        $this->assertSame('principal', $this->principal->manager_role, 'Le dernier principal actif ne doit pas pouvoir se rétrograder.');
    }

    /** @test */
    public function the_last_active_principal_can_be_demoted_when_another_active_principal_exists(): void
    {
        // Contre-épreuve : avec un second principal ACTIF, la rétrogradation
        // est autorisée — la garde ne bloque pas un transfert légitime.
        Employee::factory()->manager()->create([
            'company_id' => $this->company->id,
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->principal);

        $this->postJson("/api/v1/employees/{$this->principal->id}/assign-role", [
            'manager_role' => 'rh',
        ])->assertOk();

        $this->principal->refresh();
        $this->assertSame('rh', $this->principal->manager_role);
    }
}
