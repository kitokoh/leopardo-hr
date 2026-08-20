<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\Payroll\Domain\Models\Payroll;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #3231 (QA 2026-08-15) — IDOR cross-tenant sur les index/balances manager.
 *
 * Les index manager de 6 contrôleurs ne scopaient pas explicitement leurs
 * requêtes par company_id : un manager du tenant B pouvait lister (absences,
 * paies, avances, évaluations, projets) ou lire les soldes de congés d'un
 * employé du tenant A. La convention du repo (ExpenseClaim, PayrollCycle,
 * Task) est un `where('company_id', $actor->company_id)` explicite en tête de
 * requête — ces tests verrouillent ce scope sur chaque surface.
 */
class CrossTenantIndexIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $managerA;

    private Employee $managerB;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $this->managerA = Employee::factory()->manager()->create(['company_id' => $this->companyA->id]);
        $this->managerB = Employee::factory()->manager()->create(['company_id' => $this->companyB->id]);
        $this->employeeA = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $this->employeeB = Employee::factory()->create(['company_id' => $this->companyB->id]);
    }

    /**
     * Seed one row of each resource type for the given company and return
     * the created models so tests can assert the foreign ids never leak.
     *
     * @return array{absence: Absence, payroll: Payroll, advance: SalaryAdvance, evaluation: Evaluation, project: Project, leaveBalance: LeaveBalance}
     */
    private function seedData(Company $company, Employee $manager, Employee $employee): array
    {
        $absenceType = AbsenceType::query()->create([
            'company_id' => $company->id,
            'name' => 'Congé payé',
            'code' => 'CP-'.substr($company->id, 0, 8),
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $absence = Absence::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-03',
            'days_count' => 3,
            'status' => 'pending',
        ]);

        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_month' => 6,
            'period_year' => 2026,
            'status' => 'draft',
        ]);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 10000,
        ]);
            $advance->status = 'pending';
            $advance->save();


        $evaluation = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => '2026-H1',
            'status' => 'draft',
        ]);

        $project = Project::query()->create([
            'company_id' => $company->id,
            'name' => 'Projet secret',
            'created_by' => $manager->id,
            'members' => [$employee->id],
            'status' => 'active',
        ]);

        $leaveBalance = LeaveBalance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'balance' => 20,
            'used' => 2,
            'pending' => 1,
            'year' => 2026,
        ]);

        return compact('absence', 'payroll', 'advance', 'evaluation', 'project', 'leaveBalance');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     */
    private function assertForeignIdMissing($ids, Model $model, string $label): void
    {
        $this->assertFalse(
            $ids->contains($model['id']),
            $label.' du tenant A ne doit pas apparaître dans la liste du tenant B.'
        );
    }

    public function test_absence_index_is_scoped_to_actor_company(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);
        $this->seedData($this->companyB, $this->managerB, $this->employeeB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/absences')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertForeignIdMissing($ids, $dataA['absence'], 'L\'absence');
    }

    public function test_payroll_index_is_scoped_to_actor_company(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);
        $this->seedData($this->companyB, $this->managerB, $this->employeeB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/payrolls')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertForeignIdMissing($ids, $dataA['payroll'], 'La fiche de paie');
    }

    public function test_salary_advance_index_is_scoped_to_actor_company(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);
        $this->seedData($this->companyB, $this->managerB, $this->employeeB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/salary-advances')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertForeignIdMissing($ids, $dataA['advance'], 'L\'avance sur salaire');
    }

    public function test_evaluation_index_is_scoped_to_actor_company(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);
        $this->seedData($this->companyB, $this->managerB, $this->employeeB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/evaluations')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertForeignIdMissing($ids, $dataA['evaluation'], 'L\'évaluation');
    }

    public function test_project_index_is_scoped_to_actor_company(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);
        $this->seedData($this->companyB, $this->managerB, $this->employeeB);

        Sanctum::actingAs($this->managerB);

        $response = $this->getJson('/api/v1/projects')->assertOk();
        $ids = collect(data_get($response->json('data'), '*.id'));
        $this->assertForeignIdMissing($ids, $dataA['project'], 'Le projet');
    }

    public function test_leave_balances_of_other_company_employee_are_not_exposed(): void
    {
        $dataA = $this->seedData($this->companyA, $this->managerA, $this->employeeA);

        Sanctum::actingAs($this->managerB);

        // L’ancienne route par employé est volontairement supprimée : elle
        // exposait historiquement des soldes sans garde de rôle ni scope société.
        $this->getJson("/api/v1/employees/{$this->employeeA->id}/leave-balances?year=2026")
            ->assertNotFound();
    }

    public function test_project_store_rejects_members_from_another_company(): void
    {
        Sanctum::actingAs($this->managerB);

        // L'employé du tenant A ne doit pas être un membre valide d'un projet
        // du tenant B : la validation company-scoped (Rule::exists) renvoie 422.
        $this->postJson('/api/v1/projects', [
            'name' => 'Projet B',
            'members' => [$this->employeeA->id],
        ])->assertStatus(422);
    }

    public function test_project_store_accepts_members_from_own_company(): void
    {
        Sanctum::actingAs($this->managerB);

        $this->postJson('/api/v1/projects', [
            'name' => 'Projet B',
            'members' => [$this->employeeB->id],
        ])->assertStatus(201)->assertJsonPath('data.name', 'Projet B');
    }
}
