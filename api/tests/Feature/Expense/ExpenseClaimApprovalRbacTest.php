<?php

namespace Tests\Feature\Expense;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #8159 — ExpenseClaimPolicy::approve()/::reject() restreignent
 * l'approbation/rejet aux rôles company-wide `principal`, `comptable`, `rh`,
 * mais le chemin HTTP n'invoquait jamais la policy (`isManager()` seul) :
 * un manager `dept`/`superviseur`/`marketing` pouvait approuver des notes de
 * frais — flux à impact financier (ExpenseAccountingEntryObserver, #5235).
 *
 * Cette matrice rôles × approve/reject vérifie que la policy enregistrée
 * (AuthServiceProvider → Gate::policy) est désormais la règle effective.
 */
class ExpenseClaimApprovalRbacTest extends TestCase
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

    private function submittedClaim(Company $company): ExpenseClaim
    {
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return ExpenseClaim::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => 'Note de frais',
            'status' => 'submitted',
            'total_amount' => 100,
            'currency' => 'DZD',
            'submitted_at' => now(),
        ]);
    }

    private function managerWithRole(Company $company, string $managerRole): Employee
    {
        return Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function authorizedRolesProvider(): array
    {
        return [
            'principal' => ['principal'],
            'comptable' => ['comptable'],
            'rh' => ['rh'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenRolesProvider(): array
    {
        return [
            'dept' => ['dept'],
            'superviseur' => ['superviseur'],
            'marketing' => ['marketing'],
        ];
    }

    /**
     * @dataProvider authorizedRolesProvider
     */
    public function test_company_wide_role_can_approve_submitted_claim(string $managerRole): void
    {
        $company = Company::factory()->create();
        $claim = $this->submittedClaim($company);

        Sanctum::actingAs($this->managerWithRole($company, $managerRole));

        $this->postJson("/api/v1/expense-claims/{$claim->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    /**
     * @dataProvider forbiddenRolesProvider
     */
    public function test_team_scoped_role_cannot_approve_submitted_claim(string $managerRole): void
    {
        $company = Company::factory()->create();
        $claim = $this->submittedClaim($company);

        Sanctum::actingAs($this->managerWithRole($company, $managerRole));

        $this->postJson("/api/v1/expense-claims/{$claim->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('expense_claims', [
            'id' => $claim->id,
            'status' => 'submitted',
        ]);
    }

    /**
     * @dataProvider authorizedRolesProvider
     */
    public function test_company_wide_role_can_reject_submitted_claim(string $managerRole): void
    {
        $company = Company::factory()->create();
        $claim = $this->submittedClaim($company);

        Sanctum::actingAs($this->managerWithRole($company, $managerRole));

        $this->postJson("/api/v1/expense-claims/{$claim->id}/reject", ['reason' => 'Justificatif manquant'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    /**
     * @dataProvider forbiddenRolesProvider
     */
    public function test_team_scoped_role_cannot_reject_submitted_claim(string $managerRole): void
    {
        $company = Company::factory()->create();
        $claim = $this->submittedClaim($company);

        Sanctum::actingAs($this->managerWithRole($company, $managerRole));

        $this->postJson("/api/v1/expense-claims/{$claim->id}/reject", ['reason' => 'Justificatif manquant'])
            ->assertForbidden();

        $this->assertDatabaseHas('expense_claims', [
            'id' => $claim->id,
            'status' => 'submitted',
        ]);
    }
}
