<?php

namespace Tests\Feature;

use App\Jobs\GeneratePaymentDocumentJob;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentDocument;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\SalaryAdvance;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PaymentDocumentControllerTest extends TestCase
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

    public function test_employee_lists_only_own_payment_documents(): void
    {
        [$company, , $employee] = $this->actors();
        $own = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
        ]);

        $other = Employee::factory()->create(['company_id' => $company->id]);
        PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $other->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_AVAILABLE,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/me/payment-documents')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_employee_downloads_only_available_own_document(): void
    {
        Storage::fake('local');

        [$company, , $employee] = $this->actors();
        $path = 'payment-documents/'.$company->id.'/receipt.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 receipt');

        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_AVAILABLE,
            'path' => $path,
            'filename' => 'receipt.pdf',
            'size_bytes' => 16,
            'generated_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->get("/api/v1/me/payment-documents/{$document->id}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pending_document_download_returns_conflict(): void
    {
        [$company, , $employee] = $this->actors();
        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
        ]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/me/payment-documents/{$document->id}/download")
            ->assertStatus(409)
            ->assertJsonPath('status', PaymentDocument::STATUS_PENDING);
    }

    public function test_manager_lists_documents_for_own_payroll_run_only(): void
    {
        [$company, $manager, $employee] = $this->actors();
        [$run, $slip] = $this->payrollSlip($company, $employee);
        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'payroll_run_id' => $run->id,
            'pay_slip_id' => $slip->id,
            'document_type' => PaymentDocument::TYPE_PAYMENT_SLIP,
            'status' => PaymentDocument::STATUS_AVAILABLE,
        ]);

        [$foreignCompany, , $foreignEmployee] = $this->actors();
        [$foreignRun] = $this->payrollSlip($foreignCompany, $foreignEmployee);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/payroll-runs/{$run->id}/payment-documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $document->id);

        $this->getJson("/api/v1/payroll-runs/{$foreignRun->id}/payment-documents")
            ->assertNotFound();
    }

    public function test_mark_paid_dispatches_advance_receipt_document_job(): void
    {
        Queue::fake();

        [$company, $manager, $employee] = $this->actors();
        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 15000,
            'reason' => 'Urgence familiale',
            'status' => 'approved',
            'validation_status' => 'manager_approved',
            'amount_remaining' => 15000,
        ]);

        Sanctum::actingAs($manager);

        $this->putJson("/api/v1/salary-advances/{$advance->id}/mark-paid", [
            'payment_reference' => 'VIR-2026-001',
        ])->assertOk();

        $document = PaymentDocument::query()->where('salary_advance_id', $advance->id)->first();

        $this->assertNotNull($document);
        $this->assertSame(PaymentDocument::TYPE_ADVANCE_RECEIPT, $document->document_type);
        Queue::assertPushed(GeneratePaymentDocumentJob::class, fn (GeneratePaymentDocumentJob $job): bool => $job->paymentDocumentId === $document->id);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function actors(): array
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $manager, $employee];
    }

    /**
     * @return array{0: PayrollRun, 1: PaySlip}
     */
    private function payrollSlip(Company $company, Employee $employee): array
    {
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'paid',
            'employee_count' => 1,
            'total_gross' => 120000,
            'total_deductions' => 22000,
            'total_net' => 98000,
        ]);

        $slip = PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => $run->period_start,
            'period_end' => $run->period_end,
            'gross_salary' => 120000,
            'total_deductions' => 22000,
            'net_salary' => 98000,
            'employer_contributions' => 31200,
            'total_cost' => 151200,
            'working_days' => 22,
            'actual_days_worked' => 22,
            'overtime_hours' => 0,
            'status' => 'validated',
        ]);

        return [$run, $slip];
    }
}
