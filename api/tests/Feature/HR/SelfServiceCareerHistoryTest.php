<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\Evaluation;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Historique unifié du cycle de vie employé (issue #5328 — gap G5).
 *
 * Couvre : fusion ordonnée des sections (contrats + évaluations, +
 * career_events/départ dès que les tables existent), pagination, RBAC
 * lecture seule du parcours de l'employé authentifié, rétro-compatibilité
 * de la clé `timeline` (liste des contrats, consommée par le mobile).
 */
class SelfServiceCareerHistoryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_career_history_merges_contracts_and_evaluations_ordered(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $this->makeContract($company, $employee, '2024-01-01');
        $newContract = $this->makeContract($company, $employee, '2026-01-01');
        $this->makeEvaluation($company, $manager, $employee, '2026-Q1');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/career');

        $response->assertOk()
            // Rétro-compat : timeline = contrats, ordre inchangé.
            ->assertJsonPath('data.timeline.0.id', $newContract->id)
            ->assertJsonCount(3, 'data.career_history.data');

        $types = collect($response->json('data.career_history.data'))->pluck('type')->all();
        // Tri par date décroissante : évaluation (créée « maintenant ») puis contrats.
        $this->assertSame(['evaluation', 'contract', 'contract'], $types);

        // Sections non encore mergées (#5259/#5324) : absentes sans crash.
        $this->assertNotContains('career_event', $types);
        $this->assertNotContains('departure', $types);

        // Chaque item expose type/label/date/status/data.
        $first = $response->json('data.career_history.data.0');
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('date', $first);
        $this->assertSame('2026-Q1', $first['data']['period']);
    }

    public function test_career_history_is_paginated(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $this->makeContract($company, $employee, '2022-01-01');
        $this->makeContract($company, $employee, '2024-01-01');
        $this->makeContract($company, $employee, '2026-01-01');

        Sanctum::actingAs($employee);

        $page1 = $this->getJson('/api/v1/me/career?per_page=2&page=1');
        $page1->assertOk()
            ->assertJsonPath('data.career_history.per_page', 2)
            ->assertJsonPath('data.career_history.total', 3)
            ->assertJsonCount(2, 'data.career_history.data')
            ->assertJsonPath('data.career_history.data.0.id', $page1->json('data.timeline.0.id'));

        $page2 = $this->getJson('/api/v1/me/career?per_page=2&page=2');
        $page2->assertOk()->assertJsonCount(1, 'data.career_history.data');
    }

    public function test_career_history_reads_only_own_records(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        [$otherCompany, , $otherEmployee] = $this->createActors('MA');

        $ownContract = $this->makeContract($company, $employee, '2026-01-01');
        $this->makeEvaluation($company, $manager, $employee, '2026-Q1');
        // Contrat d'un manager du même tenant + employé d'un autre tenant.
        $this->makeContract($company, $manager, '2025-01-01');
        $this->makeContract($otherCompany, $otherEmployee, '2025-06-01');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/me/career');

        $response->assertOk()
            ->assertJsonPath('data.career_history.total', 2)
            ->assertJsonCount(2, 'data.career_history.data');

        $contractIds = collect($response->json('data.career_history.data'))
            ->where('type', 'contract')
            ->pluck('data.id')
            ->all();

        $this->assertSame([$ownContract->id], $contractIds);
    }

    public function test_career_history_supports_per_page_cap(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        for ($i = 0; $i < 5; $i++) {
            $this->makeContract($company, $employee, sprintf('202%d-01-01', $i));
        }

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/career?per_page=500')
            ->assertOk()
            ->assertJsonPath('data.career_history.per_page', 100);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function createActors(string $country = 'DZ'): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'schema_name' => 'shared_tenants',
            'country' => $country,
            'timezone' => 'UTC',
        ]);

        $manager = $this->createEmployee($company, 'manager.'.$country.'.career@a.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.'.$country.'.career@a.test', 'employee', null);

        return [$company, $manager, $employee];
    }

    private function createEmployee(
        Company $company,
        string $email,
        ?string $role,
        ?string $managerRole,
    ): Employee {
        $employee = new Employee(['email' => $email]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'first_name' => 'Test',
            'last_name' => strtoupper((string) strstr($email, '@', true)),
        ])->save();
        $employee->forceFill([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ])->save();

        /** @var Employee $employee */
        return $employee;
    }

    private function makeContract(Company $company, Employee $employee, string $startDate): Contract
    {
        /** @var Contract $contract */
        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => $startDate,
            'base_salary' => 60000,
            'status' => 'active',
        ]);

        return $contract;
    }

    private function makeEvaluation(Company $company, Employee $manager, Employee $employee, string $period): Evaluation
    {
        /** @var Evaluation $evaluation */
        $evaluation = Evaluation::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'evaluator_id' => $manager->id,
            'period' => $period,
            'score' => 3.5,
            'criteria' => ['productivity' => 4],
            'status' => 'draft',
        ]);

        return $evaluation;
    }
}
