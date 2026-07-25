<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Mail\CommunicationMail;
use App\Modules\Notification\Infrastructure\Services\Providers\MailMessageProvider;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-007 - Production-ready email provider: verifies the actual
 * mailable dispatch, the bounced-address opt-out, the missing-email skip,
 * and that a transport failure propagates as an exception (so
 * `CommunicationService::dispatchWithRetry()` can retry it) instead of
 * being swallowed here.
 */
class MailMessageProviderTest extends TestCase
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

    public function test_send_queues_the_communication_mailable_to_the_employee_email(): void
    {
        Mail::fake();

        $employee = $this->employee(['email' => 'jane@example.test']);
        $provider = new MailMessageProvider;

        $status = $provider->send($employee, 'Payslip ready', 'Your payslip for July is ready.');

        $this->assertSame('queued', $status);
        Mail::assertSent(CommunicationMail::class, function (CommunicationMail $mail) {
            return $mail->hasTo('jane@example.test')
                && $mail->subjectLine === 'Payslip ready'
                && $mail->bodyText === 'Your payslip for July is ready.';
        });
    }

    public function test_send_is_skipped_when_employee_has_no_email(): void
    {
        Mail::fake();

        $employee = $this->employee(['email' => '']);
        $provider = new MailMessageProvider;

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('skipped', $status);
        Mail::assertNothingSent();
    }

    public function test_send_is_skipped_when_employee_email_previously_bounced(): void
    {
        Mail::fake();

        $employee = $this->employee([
            'email_bounced_at' => now(),
            'email_bounce_reason' => 'hard_bounce',
        ]);
        $provider = new MailMessageProvider;

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('skipped', $status);
        Mail::assertNothingSent();
    }

    public function test_send_rethrows_transport_failures_for_the_caller_to_retry(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP connection refused'));

        $employee = $this->employee();
        $provider = new MailMessageProvider;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMTP connection refused');

        $provider->send($employee, 'Title', 'Body');
    }

    public function test_max_attempts_and_retry_delay_use_configured_values(): void
    {
        $provider = new MailMessageProvider(4, 250);

        $this->assertSame(4, $provider->maxAttempts());
        $this->assertSame(250, $provider->retryDelayMs(1));
        $this->assertSame(500, $provider->retryDelayMs(2));
        $this->assertSame(1000, $provider->retryDelayMs(3));
    }

    private function employee(array $attributes = []): Employee
    {
        $company = Company::factory()->create();

        return Employee::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $attributes));
    }
}
