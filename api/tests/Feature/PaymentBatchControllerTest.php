<?php

namespace Tests\Feature;

use App\Jobs\GeneratePaymentDocumentJob;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentBatch;
use App\Models\PaymentConfirmation;
use App\Models\PaymentItem;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PaymentBatchControllerTest extends TestCase
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

        $this->assertDatabaseHas('payment_confirmations', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'payment_item_id' => $item->id,
            'device_signature' => 'employee-mobile-v1',
        ]);
        $this->assertSame(PaymentBatch::STATUS_CONFIRMED, PaymentBatch::query()->findOrFail($batchId)->status);
        $this->assertSame(PaymentItem::STATUS_CONFIRMED, $item->fresh()->status);

        $again = $this->postJson("/api/v1/payment-confirmations/{$item->id}/confirm");
        $again->assertOk();
        $this->assertSame(1, PaymentConfirmation::query()->where('payment_item_id', $item->id)->count());
        $this->assertSame($slip->id, $item->pay_slip_id);
    }

    public function test_employee_cannot_confirm_another_employee_payment_item(): void
    {
        [, , $employee, $run] = $this->fixture();
        $other = Employee::query()->create([
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
        $company = Company::query()->create([
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
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
