<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Modules\Payroll\Domain\Models\PaymentBatch;
use App\Modules\Payroll\Domain\Models\PaymentConfirmation;
use App\Modules\Payroll\Domain\Models\PaymentItem;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaymentConsentSignatureService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class PaymentBatchControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_manager_creates_marks_paid_and_employee_confirms_payment_batch(): void
    {
        Queue::fake();

        [$company, $manager, $employee, $run, $slip] = $this->fixture();
        Sanctum::actingAs($manager);

        $create = $this->postJson('/api/v1/payment-batches', [
            'payroll_run_id' => $run->id,
            'currency' => 'DZD',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', PaymentBatch::STATUS_DRAFT)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.employee_id', $employee->id);

        $batchId = (int) $create->json('data.id');

        $paid = $this->postJson("/api/v1/payment-batches/{$batchId}/mark-paid");
        $paid->assertAccepted()
            ->assertJsonPath('data.status', PaymentBatch::STATUS_PAID)
            ->assertJsonPath('data.items.0.status', PaymentItem::STATUS_PAID);

        Queue::assertPushed(GeneratePaymentDocumentJob::class);

        $item = PaymentItem::query()->where('payment_batch_id', $batchId)->firstOrFail();
        Sanctum::actingAs($employee);

        $confirm = $this->postJson("/api/v1/payment-confirmations/{$item->id}/confirm", [
            'device_signature' => 'employee-mobile-v1',
            'document_version' => 'v1',
        ]);

        $confirm->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_item_id', $item->id);

        $documentHash = $confirm->json('data.document_hash');
        $this->assertNotEmpty($documentHash);
        $this->assertSame(64, strlen($documentHash));

        $this->assertDatabaseHas('payment_confirmations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'payment_item_id' => $item->id,
            'device_signature' => 'employee-mobile-v1',
            'document_hash' => $documentHash,
        ]);
        $this->assertSame(PaymentBatch::STATUS_CONFIRMED, PaymentBatch::query()->findOrFail($batchId)->status);
        $this->assertSame(PaymentItem::STATUS_CONFIRMED, $item->fresh()->status);

        // PA2-PAY-016 - The stored hash must match what an independent
        // recomputation from the confirmation facts would produce.
        $confirmation = PaymentConfirmation::query()->where('payment_item_id', $item->id)->firstOrFail();
        $signatureService = app(PaymentConsentSignatureService::class);
        $this->assertTrue($signatureService->verify(
            $item->fresh(),
            $confirmation->confirmed_at,
            $confirmation->document_version,
            $confirmation->document_hash,
        ));

        $again = $this->postJson("/api/v1/payment-confirmations/{$item->id}/confirm");
        $again->assertOk();
        $this->assertSame(1, PaymentConfirmation::query()->where('payment_item_id', $item->id)->count());
        $this->assertSame($slip->id, $item->pay_slip_id);

        // PA2-PAY-006 - The consent/signature model has its own audit trail:
        // confirming a payment writes an audit_logs row for the
        // PaymentConfirmation, independent of the tamper-evident hash itself.
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'action' => 'created',
            'auditable_type' => (new PaymentConfirmation)->getMorphClass(),
            'auditable_id' => $confirmation->id,
        ]);
    }

    public function test_employee_cannot_confirm_another_employee_payment_item(): void
    {
        [, , $employee, $run] = $this->fixture();
        $other = Employee::factory()->create([
            'company_id' => $employee->company_id,
            'email' => 'other@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        $batch = PaymentBatch::query()->create([
            'company_id' => $employee->company_id,
            'payroll_run_id' => $run->id,
            'status' => PaymentBatch::STATUS_PAID,
            'currency' => 'DZD',
            'items_count' => 1,
        ]);
        $item = PaymentItem::query()->create([
            'company_id' => $employee->company_id,
            'payment_batch_id' => $batch->id,
            'employee_id' => $other->id,
            'amount' => 1000,
            'currency' => 'DZD',
            'status' => PaymentItem::STATUS_PAID,
        ]);

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/payment-confirmations/{$item->id}/confirm")
            ->assertNotFound();
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee, 3: PayrollRun, 4: PaySlip}
     */
    private function fixture(): array
    {
        $company = Company::factory()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ]);

        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'period_start' => Carbon::parse('2026-05-01'),
            'period_end' => Carbon::parse('2026-05-31'),
            'country_code' => 'DZ',
            'status' => 'validated',
            'total_net' => 120000,
            'employee_count' => 1,
        ]);

        $slip = PaySlip::query()->create([
            'company_id' => $company->id,
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'period_start' => Carbon::parse('2026-05-01'),
            'period_end' => Carbon::parse('2026-05-31'),
            'gross_salary' => 150000,
            'total_deductions' => 30000,
            'net_salary' => 120000,
            'employer_contributions' => 0,
            'total_cost' => 150000,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        return [$company, $manager, $employee, $run, $slip];
    }
}
