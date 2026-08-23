<?php

namespace Tests\Feature\HR;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Contrats par pays (issue #5260) — modèles légaux DZ/MA/TN/SN, seed des
 * clauses au store, signature explicite, historique amendements.
 */
class ContractByCountryTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_templates_endpoint_returns_country_bundle(): void
    {
        [$company, $manager] = $this->createActors();

        Sanctum::actingAs($manager);

        foreach (['DZ' => '90-11', 'MA' => '65-99', 'TN' => '96-62', 'SN' => '97-17'] as $country => $law) {
            $response = $this->getJson("/api/v1/contracts/templates?country={$country}");

            $response->assertOk()
                ->assertJsonPath('data.country', $country)
                ->assertJsonPath('data.contract_type', 'cdi');
            $this->assertStringContainsString($law, (string) $response->json('data.legal_references.code'));
            $this->assertNotEmpty($response->json('data.clauses'));
            $this->assertNotEmpty($response->json('data.probation'));
            $this->assertNotEmpty($response->json('data.notice_period'));
        }
    }

    public function test_templates_endpoint_supports_cdd_and_contract_type_parameter(): void
    {
        [$company, $manager] = $this->createActors();

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/contracts/templates?country=DZ&contract_type=cdd');

        $response->assertOk()
            ->assertJsonPath('data.contract_type', 'cdd');
        $this->assertNotEmpty($response->json('data.clauses'));
    }

    public function test_templates_endpoint_rejects_unsupported_country(): void
    {
        [$company, $manager] = $this->createActors();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/contracts/templates?country=XX')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CONTRACT_TEMPLATE_NOT_FOUND');

        $this->getJson('/api/v1/contracts/templates')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CONTRACT_TEMPLATE_NOT_FOUND');
    }

    public function test_templates_endpoint_requires_manager_role(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/contracts/templates?country=DZ')->assertForbidden();
    }

    public function test_store_seeds_legal_clauses_from_country_template(): void
    {
        [$company, $manager, $employee] = $this->createActors('DZ');

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/contracts', [
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'job_title' => 'Comptable',
            'base_salary' => 60000,
        ]);

        $response->assertStatus(201);
        $clauses = $response->json('data.clauses');
        $this->assertNotEmpty($clauses);
        $this->assertSame('Nature du contrat', $clauses[0]['title']);
        // Les clauses proviennent du bundle DZ (loi 90-11).
        $this->assertStringContainsString('90-11', $clauses[0]['body']);

        $this->assertDatabaseHas('contracts', [
            'id' => $response->json('data.id'),
            'status' => 'draft',
        ]);
    }

    public function test_store_keeps_explicit_clauses_untouched(): void
    {
        [$company, $manager, $employee] = $this->createActors('DZ');

        Sanctum::actingAs($manager);

        $explicit = [
            ['title' => 'Clause maison', 'body' => 'Rédaction propre à l\'entreprise.'],
        ];

        $response = $this->postJson('/api/v1/contracts', [
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'base_salary' => 60000,
            'clauses' => $explicit,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.clauses.0.title', 'Clause maison');
        $this->assertCount(1, $response->json('data.clauses'));
    }

    public function test_store_respects_apply_legal_template_false(): void
    {
        [$company, $manager, $employee] = $this->createActors('DZ');

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/contracts', [
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'base_salary' => 60000,
            'apply_legal_template' => false,
        ]);

        $response->assertStatus(201);
        $this->assertEmpty($response->json('data.clauses'));
    }

    public function test_sign_marks_contract_signed_and_is_idempotent(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'base_salary' => 60000,
            'status' => 'draft',
        ]);

        Sanctum::actingAs($manager);

        $first = $this->postJson("/api/v1/contracts/{$contract->id}/sign", [
            'signed_document_path' => 'contracts/signed.pdf',
        ]);

        $first->assertOk();
        $this->assertNotNull($first->json('data.signed_at'));

        $contract->refresh();
        $this->assertNotNull($contract->signed_at);
        $this->assertSame('contracts/signed.pdf', $contract->signed_document_path);

        // Idempotence : un second sign ne change pas signed_at.
        $signedAt = $contract->signed_at;
        $this->postJson("/api/v1/contracts/{$contract->id}/sign")->assertOk();
        $contract->refresh();
        $this->assertSame($signedAt->toIso8601String(), $contract->signed_at?->toIso8601String());
    }

    public function test_sign_requires_manager_role_and_tenant_scope(): void
    {
        [$company, $manager, $employee] = $this->createActors();
        [$otherCompany, $otherManager] = $this->createActors('MA');

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'base_salary' => 60000,
            'status' => 'draft',
        ]);

        // Employé → 403.
        Sanctum::actingAs($employee);
        $this->postJson("/api/v1/contracts/{$contract->id}/sign")->assertForbidden();

        // Manager d'une autre société → 404 (isolation tenant).
        Sanctum::actingAs($otherManager);
        $this->postJson("/api/v1/contracts/{$contract->id}/sign")->assertNotFound();
    }

    public function test_amendments_history_is_complete(): void
    {
        [$company, $manager, $employee] = $this->createActors();

        $contract = Contract::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'contract_type' => 'cdi',
            'start_date' => '2026-09-01',
            'base_salary' => 60000,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $this->postJson("/api/v1/contracts/{$contract->id}/amendments", [
            'amendment_type' => 'salary_change',
            'changes' => ['base_salary' => ['from' => 60000, 'to' => 70000]],
            'effective_date' => '2026-10-01',
            'reason' => 'Augmentation annuelle',
        ])->assertStatus(201);

        $this->postJson("/api/v1/contracts/{$contract->id}/amendments", [
            'amendment_type' => 'position_change',
            'changes' => ['job_title' => ['from' => 'Comptable', 'to' => 'Chef comptable']],
            'effective_date' => '2026-11-01',
        ])->assertStatus(201);

        $history = $this->getJson("/api/v1/contracts/{$contract->id}/amendments");

        $history->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.amendment_type', 'position_change')
            ->assertJsonPath('data.1.amendment_type', 'salary_change');
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

        $manager = $this->createEmployee($company, 'manager.'.$country.'@a.test', 'manager', 'principal');
        $employee = $this->createEmployee($company, 'employee.'.$country.'@a.test', 'employee', null);

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
}
