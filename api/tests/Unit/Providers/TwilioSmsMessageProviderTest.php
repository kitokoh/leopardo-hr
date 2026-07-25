<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Infrastructure\Services\Providers\TwilioSmsMessageProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-JOB-003 - Production SMS provider: verifies the actual Twilio REST
 * call, the missing-phone-number skip, the missing-credentials skip, and
 * that a transport/API failure propagates as an exception (so
 * `CommunicationService::dispatchWithRetry()` can retry it) instead of
 * being swallowed here.
 */
class TwilioSmsMessageProviderTest extends TestCase
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

    public function test_send_posts_to_twilio_messages_api_with_composed_body(): void
    {
        config()->set('services.twilio.account_sid', 'AC_test_sid');
        config()->set('services.twilio.auth_token', 'test_token');
        config()->set('services.twilio.from', '+15550001111');

        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201),
        ]);

        $employee = $this->employee(['phone' => '+15551234567']);
        $provider = new TwilioSmsMessageProvider;

        $status = $provider->send($employee, 'Payslip ready', 'Your payslip for July is ready.');

        $this->assertSame('queued', $status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC_test_sid/Messages.json'
                && $request['From'] === '+15550001111'
                && $request['To'] === '+15551234567'
                && $request['Body'] === 'Payslip ready: Your payslip for July is ready.';
        });
    }

    public function test_send_is_skipped_when_employee_has_no_phone_number(): void
    {
        config()->set('services.twilio.account_sid', 'AC_test_sid');
        config()->set('services.twilio.auth_token', 'test_token');
        config()->set('services.twilio.from', '+15550001111');

        Http::fake();

        $employee = $this->employee(['phone' => null, 'personal_phone' => null]);
        $provider = new TwilioSmsMessageProvider;

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('skipped', $status);
        Http::assertNothingSent();
    }

    public function test_send_is_skipped_when_twilio_credentials_are_not_configured(): void
    {
        config()->set('services.twilio.account_sid', null);
        config()->set('services.twilio.auth_token', null);
        config()->set('services.twilio.from', null);

        Http::fake();

        $employee = $this->employee(['phone' => '+15551234567']);
        $provider = new TwilioSmsMessageProvider;

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('skipped', $status);
        Http::assertNothingSent();
    }

    public function test_send_throws_for_the_caller_to_retry_on_twilio_error_response(): void
    {
        config()->set('services.twilio.account_sid', 'AC_test_sid');
        config()->set('services.twilio.auth_token', 'test_token');
        config()->set('services.twilio.from', '+15550001111');

        Http::fake([
            'api.twilio.com/*' => Http::response(['message' => 'Invalid number'], 400),
        ]);

        $employee = $this->employee(['phone' => '+15551234567']);
        $provider = new TwilioSmsMessageProvider;

        $this->expectException(RuntimeException::class);

        $provider->send($employee, 'Title', 'Body');
    }

    public function test_max_attempts_and_retry_delay_use_configured_values(): void
    {
        $provider = new TwilioSmsMessageProvider(4, 250);

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
