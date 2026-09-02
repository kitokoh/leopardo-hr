<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\EduManager\Domain\Models\EduFee;
use App\Modules\EduManager\Domain\Models\EduStudent;
use App\Modules\EduManager\Infrastructure\Services\EduFeeService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5832 (EDU-016) — frais scolaires et contrat Accounting.
 *
 * Verrouille : création idempotente (external_reference), montant > 0
 * (CHECK), règlement idempotent (terminal refusé), audit edu.fee.*,
 * AUCUNE écriture comptable créée (contrat Accounting), isolation tenant.
 */
class EduFeeServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private EduStudent $studentA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->create([
            'company_id' => $companyA->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->principalA = $principalA;

        /** @var EduStudent $studentA */
        $studentA = EduStudent::query()->create([
            'company_id' => $companyA->id,
            'student_number' => 'STU-A-1',
            'display_name' => 'Lina Benali',
            'status' => EduStudent::STATUS_ACTIVE,
        ]);
        $this->studentA = $studentA;
    }

    public function test_fees_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('edu_fees'));
    }

    public function test_create_is_idempotent_on_external_reference(): void
    {
        $service = app(EduFeeService::class);
        $payload = [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Frais d\'inscription',
            'amount' => 2500,
            'due_date' => '2026-09-15',
            'external_reference' => 'FEE-2026-001',
        ];

        $first = $service->create($this->principalA, $payload);
        $second = $service->create($this->principalA, $payload);

        $this->assertSame((int) $first->getAttribute('id'), (int) $second->getAttribute('id'));
        $this->assertSame(1, EduFee::query()->where('company_id', $this->companyA->id)->count());
    }

    public function test_create_rejects_non_positive_amount(): void
    {
        $this->expectException(QueryException::class);

        DB::transaction(function (): void {
            app(EduFeeService::class)->create($this->principalA, [
                'student_id' => (int) $this->studentA->getAttribute('id'),
                'label' => 'Frais nul',
                'amount' => 0,
                'due_date' => '2026-09-15',
            ]);
        });
    }

    public function test_mark_paid_is_idempotent_and_audited(): void
    {
        $service = app(EduFeeService::class);
        $fee = $service->create($this->principalA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Scolarité',
            'amount' => 5000,
            'due_date' => '2026-09-30',
        ]);

        $paid = $service->markPaid($this->principalA, $fee, ['payment_reference' => 'PAY-1']);
        $this->assertSame(EduFee::STATUS_PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);

        // Terminal → refus.
        try {
            $service->markPaid($this->principalA, $paid->refresh(), ['payment_reference' => 'PAY-2']);
            $this->fail('Un frais payé aurait dû refuser le re-règlement.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('EDU_FEE_TERMINAL', $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'edu.fee.paid',
            'module' => 'edu',
        ]);
    }

    public function test_fees_never_create_accounting_entries(): void
    {
        $service = app(EduFeeService::class);
        $fee = $service->create($this->principalA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Scolarité',
            'amount' => 5000,
            'due_date' => '2026-09-30',
        ]);
        $service->markPaid($this->principalA, $fee, ['payment_reference' => 'PAY-X']);

        // Aucune table comptable touchée (contrat Accounting : EduManager
        // expose le read model, Accounting consomme via son propre flux).
        $tables = ['accounting_journal_entries', 'accounting_entries', 'journal_entries'];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->assertSame(0, DB::table($table)->count(), "table {$table} non vide");
            }
        }
    }

    public function test_cross_tenant_fee_is_rejected(): void
    {
        $service = app(EduFeeService::class);
        $fee = $service->create($this->principalA, [
            'student_id' => (int) $this->studentA->getAttribute('id'),
            'label' => 'Scolarité',
            'amount' => 5000,
            'due_date' => '2026-09-30',
        ]);

        /** @var Employee $principalB */
        $principalB = Employee::factory()->create([
            'company_id' => $this->companyB->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        $this->expectException(NotFoundHttpException::class);

        $service->markPaid($principalB, $fee, ['payment_reference' => 'PAY-Z']);
    }
}
