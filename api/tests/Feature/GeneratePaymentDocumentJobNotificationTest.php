<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GeneratePaymentDocumentJob;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-PAY-004 — Bordereaux PDF async: the employee must be notified
 * in-app as soon as their payment document PDF is generated and
 * downloadable, instead of having to poll for it.
 */
class GeneratePaymentDocumentJobNotificationTest extends TestCase
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

    public function test_employee_is_notified_when_advance_receipt_becomes_available(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.$company->id.'@example.test',
        ]);

        $advance = SalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount' => 15000,
            'currency' => 'DZD',
            'reason' => 'Urgence familiale',
            'status' => 'approved',
            'validation_status' => 'payment_declared',
            'amount_remaining' => 15000,
        ]);

        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'salary_advance_id' => $advance->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
            'metadata' => [
                'amount' => $advance->amount,
                'currency' => $advance->currency,
            ],
        ]);

        (new GeneratePaymentDocumentJob($document->id))->handle(app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class));

        $document->refresh();

        $this->assertSame(PaymentDocument::STATUS_AVAILABLE, $document->status);
        $this->assertNotNull($document->path);

        $this->assertDatabaseHas('notifications', [
            'employee_id' => $employee->id,
            'type' => 'payroll',
        ]);

        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_ready',
            'channel' => 'app',
            'status' => 'sent',
        ]);
    }

    public function test_no_notification_is_recorded_when_document_generation_fails(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.$company->id.'@example.test',
        ]);

        // An unconfigured storage disk makes Storage::disk(...)->put()
        // throw, simulating a hard generation failure after rendering.
        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_PAYMENT_SLIP,
            'status' => PaymentDocument::STATUS_PENDING,
            'disk' => 'nonexistent-disk',
        ]);

        try {
            (new GeneratePaymentDocumentJob($document->id))->handle(app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class));
        } catch (\Throwable) {
            // Expected: rendering with no pay slip context throws.
        }

        $document->refresh();

        $this->assertSame(PaymentDocument::STATUS_FAILED, $document->status);
        $this->assertDatabaseMissing('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_ready',
        ]);
    }
}
