<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Modules\Accounting\Domain\Models\AccountingContact;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #9763 — la délégation de module (`employee_module_grants`, #7761) doit
 * ouvrir le module Comptabilité à un collaborateur NON-manager.
 *
 * Avant ce correctif, quatre contrôleurs refaisaient
 * `if (! $actor->isManager()) { abort(403); }` APRÈS que
 * `EnsureApiManagerMiddleware` ait accordé l'accès par grant : le grant était
 * donc inopérant sur tout le module (devis, factures, paiements, audit,
 * partages).
 *
 * Ce test verrouille les trois cas de la matrice :
 *  1. non-manager AVEC grant `accounting` → devis + facture autorisés ;
 *  2. non-manager SANS grant → 403 (aucune régression de sécurité) ;
 *  3. manager `comptable` (rôle historique) → toujours autorisé.
 */
class AccountingModuleGrantDelegationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    private AccountingContact $customer;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Employee $principal */
        $principal = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principal = $principal;

        /** @var AccountingContact $customer */
        $customer = AccountingContact::query()->create([
            'company_id' => $company->id,
            'type' => 'customer',
            'name' => 'Client Délégation',
            'email' => 'delegation@example.com',
            'source' => 'manual',
        ]);
        $this->customer = $customer;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    private function employee(string $role = 'employee', ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $employee;
    }

    private function grantAccounting(Employee $employee): void
    {
        $grant = new EmployeeModuleGrant([
            'employee_id' => $employee->id,
            'module_key' => 'accounting',
        ]);
        $grant->company_id = (string) $employee->company_id;
        $grant->granted_by_employee_id = $this->principal->id;
        $grant->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function quotePayload(): array
    {
        return [
            'type' => 'quote',
            'contact_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'DZD',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Prestation', 'quantity' => 2, 'unit_price' => 500],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(): array
    {
        return [
            'type' => 'invoice',
            'contact_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'DZD',
            'tva_rate' => 19,
            'lines' => [
                ['description' => 'Forfait', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ];
    }

    // ── 1. Non-manager AVEC grant : le module est ouvert ────────────────────

    public function test_non_manager_with_accounting_grant_can_create_quote(): void
    {
        $employee = $this->employee();
        $this->grantAccounting($employee);

        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/accounting/documents', $this->quotePayload())
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'quote')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_non_manager_with_accounting_grant_can_create_invoice(): void
    {
        $employee = $this->employee();
        $this->grantAccounting($employee);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/accounting/documents', $this->invoicePayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'invoice');

        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{4}$/', (string) $response->json('data.number'));
    }

    public function test_non_manager_with_accounting_grant_can_list_documents(): void
    {
        $employee = $this->employee();
        $this->grantAccounting($employee);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/documents')->assertOk();
    }

    public function test_non_manager_with_accounting_grant_can_read_audit_logs(): void
    {
        $employee = $this->employee();
        $this->grantAccounting($employee);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/audit-logs')->assertOk();
    }

    // ── 2. Non-manager SANS grant : refusé (pas de régression) ──────────────

    public function test_non_manager_without_grant_is_forbidden(): void
    {
        Sanctum::actingAs($this->employee());

        $this->getJson('/api/v1/accounting/documents')->assertStatus(403);
        $this->postJson('/api/v1/accounting/documents', $this->quotePayload())->assertStatus(403);
        $this->getJson('/api/v1/accounting/audit-logs')->assertStatus(403);
    }

    public function test_grant_of_another_module_does_not_open_accounting(): void
    {
        $employee = $this->employee();

        $grant = new EmployeeModuleGrant([
            'employee_id' => $employee->id,
            'module_key' => 'crm',
        ]);
        $grant->company_id = (string) $employee->company_id;
        $grant->granted_by_employee_id = $this->principal->id;
        $grant->save();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/accounting/documents')->assertStatus(403);
    }

    // ── 3. Rôles manager historiques : inchangés ────────────────────────────

    public function test_accountant_manager_role_still_allowed(): void
    {
        Sanctum::actingAs($this->employee('manager', 'comptable'));

        $this->postJson('/api/v1/accounting/documents', $this->invoicePayload())
            ->assertStatus(201);
    }

    public function test_principal_still_allowed(): void
    {
        Sanctum::actingAs($this->principal);

        $this->postJson('/api/v1/accounting/documents', $this->quotePayload())
            ->assertStatus(201);
    }
}
