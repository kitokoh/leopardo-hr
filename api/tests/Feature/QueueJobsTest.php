<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GeneratePaymentDocumentJob;
use App\Jobs\ProcessBulkPaymentJob;
use App\Jobs\ProcessPayrollBatchJob;
use App\Jobs\SendBulkNotificationsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_batch_job_dispatches_on_payroll_queue(): void
    {
        Queue::fake();

        ProcessPayrollBatchJob::dispatch(1, '11111111-1111-1111-1111-111111111111');

        Queue::assertPushed(ProcessPayrollBatchJob::class, function ($job) {
            return $job->queue === 'payroll';
        });
    }

    public function test_bulk_notifications_job_dispatches_on_notifications_queue(): void
    {
        Queue::fake();

        SendBulkNotificationsJob::dispatch(
            [1, 2, 3],
            Notification::class,
            [],
            '11111111-1111-1111-1111-111111111111'
        );

        Queue::assertPushed(SendBulkNotificationsJob::class, function ($job) {
            return $job->queue === 'notifications';
        });
    }

    public function test_payroll_batch_job_has_correct_tags(): void
    {
        $job = new ProcessPayrollBatchJob(42, '22222222-2222-2222-2222-222222222222');
        $tags = $job->tags();

        $this->assertContains('company:22222222-2222-2222-2222-222222222222', $tags);
        $this->assertContains('payroll_run:42', $tags);
    }

    public function test_bulk_notifications_job_has_correct_tags(): void
    {
        $job = new SendBulkNotificationsJob(
            [1, 2],
            Notification::class,
            [],
            '33333333-3333-3333-3333-333333333333'
        );
        $tags = $job->tags();

        $this->assertContains('company:33333333-3333-3333-3333-333333333333', $tags);
    }

    public function test_payment_document_job_runs_on_documents_queue(): void
    {
        Queue::fake();

        GeneratePaymentDocumentJob::dispatch(123);

        Queue::assertPushed(GeneratePaymentDocumentJob::class, function ($job) {
            return $job->queue === 'documents';
        });
    }

    public function test_bulk_payment_job_runs_on_payroll_queue(): void
    {
        Queue::fake();

        ProcessBulkPaymentJob::dispatch(42, 7);

        Queue::assertPushed(ProcessBulkPaymentJob::class, function ($job) {
            return $job->queue === 'payroll';
        });
    }

    public function test_process_payroll_batch_job_implements_tenant_scoped_job(): void
    {
        $job = new ProcessPayrollBatchJob(42, '22222222-2222-2222-2222-222222222222');

        $this->assertInstanceOf(\App\Contracts\Queue\TenantScopedJob::class, $job);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $job->tenantCompanyId());
        $this->assertNotEmpty($job->middleware());
        $this->assertInstanceOf(\App\Jobs\Middleware\EnsureTenantContext::class, $job->middleware()[0]);
    }

    public function test_send_bulk_notifications_job_implements_tenant_scoped_job(): void
    {
        $job = new SendBulkNotificationsJob([1, 2], Notification::class, [], '33333333-3333-3333-3333-333333333333');

        $this->assertInstanceOf(\App\Contracts\Queue\TenantScopedJob::class, $job);
        $this->assertSame('33333333-3333-3333-3333-333333333333', $job->tenantCompanyId());
    }

    public function test_generate_payment_document_job_implements_tenant_scoped_job(): void
    {
        $job = new GeneratePaymentDocumentJob(123);

        $this->assertInstanceOf(\App\Contracts\Queue\TenantScopedJob::class, $job);
    }

    public function test_process_bulk_payment_job_implements_tenant_scoped_job(): void
    {
        $job = new ProcessBulkPaymentJob(42, 7);

        $this->assertInstanceOf(\App\Contracts\Queue\TenantScopedJob::class, $job);
    }
}
