<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Application\Actions\ContractLifecycleAction;
use App\Modules\HR\Domain\Exceptions\InvalidContractTransitionException;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #3891 — machine à états du cycle de vie d'un contrat : les transitions
 * illégales renvoient 422 (jamais 500) et les autorisations passent par
 * ContractPolicy (404 cross-tenant, 403 non-manager).
 */
class ContractLifecycleTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function ($t) {
                $t->increments('id');
                $t->uuid('company_id');
                $t->unsignedInteger('employee_id');
                $t->string('contract_type', 20);
                $t->string('reference', 50)->nullable();
                $t->date('start_date');
                $t->date('end_date')->nullable();
                $t->string('job_title', 150)->nullable();
                $t->unsignedInteger('department_id')->nullable();
                $t->unsignedInteger('position_id')->nullable();
                $t->decimal('base_salary', 12, 2)->default(0);
                $t->string('currency', 3)->default('DZD');
                $t->string('salary_frequency', 10)->default('monthly');
                $t->decimal('work_hours_per_week', 5, 2)->nullable();
                $t->date('probation_end_date')->nullable();
                $t->json('benefits')->nullable();
                $t->json('clauses')->nullable();
                $t->string('status', 20)->default('draft');
                $t->timestamp('signed_at')->nullable();
                $t->string('signed_document_path')->nullable();
                $t->text('termination_reason')->nullable();
                $t->timestamp('terminated_at')->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
            });
        }

        $company = Company::factory()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
        ]);
        $this->employee = Employee::factory()->create([
            'company_id' => $company->id,
        ]);
    }

    private function createContract(string $status = 'draft', ?string $companyId = null): Contract
    {
        return Contract::create([
            'company_id' => $companyId ?? $this->employee->company_id,
            'employee_id' => $this->employee->id,
            'contract_type' => 'cdi',
            'start_date' => now()->toDateString(),
            'base_salary' => 100000,
            'status' => $status,
        ]);
    }

    public function test_activate_draft_contract(): void
    {
        $contract = $this->createContract('draft');

        $activated = app(ContractLifecycleAction::class)->activate($contract);

        $this->assertSame('active', $activated->status);
        $this->assertNotNull($activated->signed_at);
    }

    public function test_activate_non_draft_is_rejected(): void
    {
        $contract = $this->createContract('active');

        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->activate($contract);
    }

    public function test_suspend_requires_active(): void
    {
        $draft = $this->createContract('draft');
        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->suspend($draft);

        $active = $this->createContract('active');
        $suspended = app(ContractLifecycleAction::class)->suspend($active);
        $this->assertSame('suspended', $suspended->status);
    }

    public function test_terminate_active_contract(): void
    {
        $contract = $this->createContract('active');

        $terminated = app(ContractLifecycleAction::class)->terminate($contract, 'Fin de mission');

        $this->assertSame('terminated', $terminated->status);
        $this->assertSame('Fin de mission', $terminated->termination_reason);
        $this->assertNotNull($terminated->terminated_at);
    }

    public function test_terminate_draft_is_rejected(): void
    {
        $contract = $this->createContract('draft');

        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->terminate($contract, 'Raison');
    }

    public function test_renew_creates_draft_and_expires_previous(): void
    {
        $contract = $this->createContract('active');
        $actor = new Employee(['id' => 99, 'company_id' => $contract->company_id]);
        $actor->id = 99;

        $newContract = app(ContractLifecycleAction::class)->renew($contract, $actor, [
            'start_date' => now()->addYear()->toDateString(),
            'end_date' => null,
            'base_salary' => 120000,
        ]);

        $this->assertSame('draft', $newContract->status);
        $this->assertSame(120000.0, (float) $newContract->base_salary);
        $this->assertSame('expired', $contract->fresh()->status);
    }

    public function test_contract_endpoint_uses_policy_for_cross_tenant(): void
    {
        $manager = Employee::factory()->manager()->create(['company_id' => '11111111-1111-1111-1111-111111111111']);
        Sanctum::actingAs($manager);
        Company::factory()->create([
            'id' => '99999999-9999-9999-9999-999999999999',
        ]);

        $foreignContract = $this->createContract('draft', '99999999-9999-9999-9999-999999999999');

        $response = $this->postJson("/api/v1/contracts/{$foreignContract->id}/activate");
        $response->assertStatus(404);
    }
}
