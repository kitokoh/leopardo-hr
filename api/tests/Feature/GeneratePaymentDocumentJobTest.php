<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-PAY-018 — Finance anti-regression coverage for salary advance
 * payment receipts: the generated PDF binary, its stored balance/amount
 * figures, and the tenant/currency it is scoped to must never silently
 * drift across companies or currencies.
 *
 * These tests exercise `GeneratePaymentDocumentJob::handle()` directly
 * (previously only its *dispatch* was asserted, see QueueJobsTest and
 * PaymentDocumentControllerTest) so a regression in PDF rendering, in the
 * multi-tenant company resolution, or in the currency snapshotted on the
 * advance is caught immediately instead of failing silently in production.
 */
class GeneratePaymentDocumentJobTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_advance_receipt_job_generates_pdf_with_correct_amount_and_currency(): void
    {
        $company = Company::factory()->create(['currency' => 'EUR']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Amine',
            'last_name' => 'Belkacem',
        ]);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 25000,
            'currency' => 'EUR',
            'reason' => 'Frais medicaux',
            'status' => 'approved',
            'validation_status' => 'payment_declared',
            'amount_remaining' => 10000,
            'payment_reference' => 'VIR-2026-042',
            'payment_declared_at' => now(),
        ]);

        $document = GeneratePaymentDocumentJob::dispatchForSalaryAdvance($advance, $employee->id);

        app()->call([new GeneratePaymentDocumentJob($document->id), 'handle']);

        $document->refresh();

        $this->assertSame(PaymentDocument::STATUS_AVAILABLE, $document->status);
        $this->assertSame(PaymentDocument::TYPE_ADVANCE_RECEIPT, $document->document_type);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertNotNull($document->path);
        $this->assertNotNull($document->generated_at);
        $this->assertGreaterThan(0, $document->size_bytes);

        // Metadata is a currency/amount snapshot taken at dispatch time —
        // it must reflect the advance's own currency, not a hardcoded or
        // tenant-default fallback (PA2-PAY-002 regression surface).
        $this->assertSame('EUR', $document->metadata['currency']);
        $this->assertSame(25000.0, (float) $document->metadata['amount']);

        $disk = Storage::disk($document->disk ?: 'local');
        $this->assertTrue($disk->exists($document->path));

        $binary = $disk->get($document->path);
        $this->assertNotNull($binary);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    public function test_advance_receipt_pdf_is_scoped_to_its_own_tenant_and_currency_even_when_another_tenant_uses_a_different_currency(): void
    {
        $companyA = Company::factory()->create(['currency' => 'DZD']);
        $employeeA = Employee::factory()->create(['company_id' => $companyA->id]);
        $advanceA = SalaryAdvance::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => $employeeA->id,
            'amount' => 15000,
            'currency' => 'DZD',
            'status' => 'approved',
            'validation_status' => 'payment_declared',
            'amount_remaining' => 15000,
        ]);

        $companyB = Company::factory()->create(['currency' => 'XOF']);
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
        $advanceB = SalaryAdvance::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'amount' => 50000,
            'currency' => 'XOF',
            'status' => 'approved',
            'validation_status' => 'payment_declared',
            'amount_remaining' => 50000,
        ]);

        $documentA = GeneratePaymentDocumentJob::dispatchForSalaryAdvance($advanceA, $employeeA->id);
        $documentB = GeneratePaymentDocumentJob::dispatchForSalaryAdvance($advanceB, $employeeB->id);

        (new GeneratePaymentDocumentJob($documentA->id))->handle(app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class));
        (new GeneratePaymentDocumentJob($documentB->id))->handle(app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class));

        $documentA->refresh();
        $documentB->refresh();

        $this->assertSame($companyA->id, $documentA->company_id);
        $this->assertSame($companyB->id, $documentB->company_id);
        $this->assertNotSame($documentA->company_id, $documentB->company_id);

        $this->assertSame('DZD', $documentA->metadata['currency']);
        $this->assertSame('XOF', $documentB->metadata['currency']);

        $this->assertSame(PaymentDocument::STATUS_AVAILABLE, $documentA->status);
        $this->assertSame(PaymentDocument::STATUS_AVAILABLE, $documentB->status);

        // Each tenant's PDF must live under its own company-scoped path —
        // no cross-tenant path collision or overwrite is possible.
        $this->assertStringContainsString((string) $companyA->id, (string) $documentA->path);
        $this->assertStringContainsString((string) $companyB->id, (string) $documentB->path);
        $this->assertNotSame($documentA->path, $documentB->path);
    }

    public function test_advance_receipt_job_marks_document_failed_and_rethrows_when_storage_disk_is_invalid(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
            // A disk that is not registered in config/filesystems.php makes
            // Storage::disk() throw once the PDF has already been rendered,
            // exercising the job's failure branch: the document must be
            // flagged as failed (never left stuck as "generating") and the
            // exception must still propagate for the queue's retry policy.
            'disk' => 'nonexistent-disk',
        ]);

        $thrown = null;

        try {
            (new GeneratePaymentDocumentJob($document->id))->handle(app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class));
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Expected the job to rethrow the storage failure.');

        $document->refresh();

        $this->assertSame(PaymentDocument::STATUS_FAILED, $document->status);
        $this->assertNotNull($document->error_message);
    }
}
