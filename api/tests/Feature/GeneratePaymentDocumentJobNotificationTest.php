<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GeneratePaymentDocumentJob;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Payroll\Domain\Models\PaymentDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-010 — A payment document (receipt/payslip/bordereau) is generated
 * asynchronously; the employee must be told it is being prepared, then that
 * it is ready (or that generation failed), instead of the UI blocking or
 * polling blindly for it.
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

    public function test_employee_is_notified_when_document_starts_processing_and_becomes_ready(): void
    {
        Storage::fake('local');

        [$company, $employee] = $this->companyAndEmployee();

        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
        ]);

        app(GeneratePaymentDocumentJob::class, ['paymentDocumentId' => $document->id])->handle(
            app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class)
        );

        $document->refresh();
        $this->assertSame(PaymentDocument::STATUS_AVAILABLE, $document->status);

        // Two app notifications: "processing" then "ready".
        $notifications = Notification::query()
            ->where('employee_id', $employee->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $notifications);
        $this->assertSame('payroll', $notifications[0]->type);
        $this->assertSame('payroll', $notifications[1]->type);

        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_processing',
            'channel' => 'app',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_ready',
            'channel' => 'app',
            'status' => 'sent',
        ]);
    }

    public function test_employee_is_notified_when_document_generation_fails(): void
    {
        [$company, $employee] = $this->companyAndEmployee();

        $document = PaymentDocument::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'document_type' => PaymentDocument::TYPE_ADVANCE_RECEIPT,
            'status' => PaymentDocument::STATUS_PENDING,
        ]);

        // Force a storage failure so the job takes its failure branch
        // without depending on PDF rendering internals.
        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturnUsing(function () {
                $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
                $disk->shouldReceive('put')->andThrow(new \RuntimeException('Disk unavailable in test'));

                return $disk;
            });

        try {
            app(GeneratePaymentDocumentJob::class, ['paymentDocumentId' => $document->id])->handle(
                app(\App\Modules\Notification\Infrastructure\Services\CommunicationService::class)
            );
            $this->fail('Expected job to throw when storage is unavailable.');
        } catch (\Throwable) {
            // Expected: the job rethrows after marking the document failed.
        }

        $document->refresh();
        $this->assertSame(PaymentDocument::STATUS_FAILED, $document->status);

        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_processing',
            'channel' => 'app',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'template_key' => 'payment_document_failed',
            'channel' => 'app',
            'status' => 'sent',
        ]);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function companyAndEmployee(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        return [$company, $employee];
    }
}
