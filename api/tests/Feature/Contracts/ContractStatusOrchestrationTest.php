<?php

declare(strict_types=1);

namespace Tests\Feature\Contracts;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\EmployeeLastContractTerminated;
use App\Modules\HR\Application\Actions\ContractLifecycleAction;
use App\Modules\HR\Domain\Exceptions\InvalidContractTransitionException;
use App\Modules\HR\Domain\Models\Contract;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5327 (G4) — orchestration contrat ↔ statut employé : activate →
 * active, suspend → suspended, terminate du dernier contrat → événement
 * `EmployeeLastContractTerminated` (hook workflow de départ #5324).
 *
 * Invariant garanti : jamais de transition de contrat sur un employé
 * archivé (refus 422) — jamais de contrat actif/suspendu sur un employé
 * archivé.
 */
class ContractStatusOrchestrationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function ($t): void {
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

        /** @var Company $company */
        $company = Company::factory()->create([
            'id' => '11111111-1111-1111-1111-111111111111',
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
        ]);
        $this->employee = $employee;
    }

    private function createContract(string $status = 'draft', ?string $companyId = null): Contract
    {
        return Contract::create([
            'company_id' => $companyId ?? $this->employee->company_id,
            'employee_id' => $this->employee->id,
            'contract_type' => 'cdi',
            'start_date' => now()->toDateString(),
            'base_salary' => 100000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
            'status' => $status,
        ]);
    }

    public function test_activate_sets_employee_active(): void
    {
        $this->employee->forceFill(['status' => 'suspended'])->save();
        $contract = $this->createContract('draft');

        app(ContractLifecycleAction::class)->activate($contract);

        $this->assertSame('active', $contract->fresh()?->status);
        $this->assertSame('active', $this->employee->fresh()?->status);
    }

    public function test_suspend_sets_employee_suspended(): void
    {
        $this->employee->forceFill(['status' => 'active'])->save();
        $contract = $this->createContract('active');

        app(ContractLifecycleAction::class)->suspend($contract);

        $this->assertSame('suspended', $contract->fresh()?->status);
        $this->assertSame('suspended', $this->employee->fresh()?->status);
    }

    public function test_activate_on_archived_employee_is_rejected(): void
    {
        // Invariant G4 : jamais de contrat actif sur un employé archivé.
        $this->employee->forceFill(['status' => 'archived'])->save();
        $contract = $this->createContract('draft');

        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->activate($contract);
    }

    public function test_suspend_on_archived_employee_is_rejected(): void
    {
        $this->employee->forceFill(['status' => 'archived'])->save();
        $contract = $this->createContract('active');

        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->suspend($contract);
    }

    public function test_terminate_last_contract_dispatches_departure_hook(): void
    {
        Event::fake([EmployeeLastContractTerminated::class]);

        $contract = $this->createContract('active');

        app(ContractLifecycleAction::class)->terminate($contract, 'Fin de CDD');

        $this->assertSame('terminated', $contract->fresh()?->status);
        Event::assertDispatched(EmployeeLastContractTerminated::class, function ($event) use ($contract): bool {
            return $event->employee->id === $this->employee->id
                && $event->contract->id === $contract->id;
        });
    }

    public function test_terminate_keeps_employee_when_other_contract_active(): void
    {
        Event::fake([EmployeeLastContractTerminated::class]);

        $this->createContract('active');          // second contrat actif
        $contract = $this->createContract('active');

        app(ContractLifecycleAction::class)->terminate($contract, 'Fin');

        Event::assertNotDispatched(EmployeeLastContractTerminated::class);
        $this->assertSame('active', $this->employee->fresh()?->status);
        $this->assertTrue(
            Contract::query()->where('employee_id', $this->employee->id)->where('status', 'active')->exists()
        );
    }

    public function test_terminate_on_archived_employee_is_rejected(): void
    {
        $this->employee->forceFill(['status' => 'archived'])->save();
        $contract = $this->createContract('active');

        $this->expectException(InvalidContractTransitionException::class);
        app(ContractLifecycleAction::class)->terminate($contract, 'Raison');
    }
}
