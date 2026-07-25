<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Infrastructure\Services\Providers\WhatsappCloudApiMessageProvider;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-008 - WhatsApp Business Cloud API provider: verifies the actual
 * HTTP request shape sent to Meta, the phone number normalization, and
 * that a missing recipient number or a provider-side error never throws
 * (the caller/`CommunicationService` relies on a plain status string).
 */
class WhatsappCloudApiMessageProviderTest extends TestCase
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

    public function test_send_posts_normalized_recipient_number_and_text_body(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $employee = $this->employee(['personal_phone' => '+213 6 12 34 56 78']);
        $provider = new WhatsappCloudApiMessageProvider('123456', 'test-token');

        $status = $provider->send($employee, 'Payslip ready', 'Your payslip for July is ready.');

        $this->assertSame('queued', $status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v19.0/123456/messages'
                && $request['to'] === '213612345678'
                && $request['messaging_product'] === 'whatsapp'
                && $request['text']['body'] === "Payslip ready\n\nYour payslip for July is ready.";
        });
    }

    public function test_send_prefers_personal_phone_over_work_phone(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);

        $employee = $this->employee([
            'personal_phone' => '0612345678',
            'phone' => '0699999999',
        ]);
        $provider = new WhatsappCloudApiMessageProvider('123456', 'test-token');

        $provider->send($employee, 'Title', 'Body');

        Http::assertSent(fn ($request) => $request['to'] === '0612345678');
    }

    public function test_send_is_skipped_when_employee_has_no_usable_phone_number(): void
    {
        Http::fake();

        $employee = $this->employee(['personal_phone' => null, 'phone' => null]);
        $provider = new WhatsappCloudApiMessageProvider('123456', 'test-token');

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('skipped', $status);
        Http::assertNothingSent();
    }

    public function test_send_returns_failed_status_on_provider_error_without_throwing(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid parameter']], 400),
        ]);

        $employee = $this->employee(['personal_phone' => '0612345678']);
        $provider = new WhatsappCloudApiMessageProvider('123456', 'test-token');

        $status = $provider->send($employee, 'Title', 'Body');

        $this->assertSame('failed', $status);
    }

    private function employee(array $attributes = []): Employee
    {
        $company = Company::factory()->create();

        return Employee::factory()->create(array_merge([
            'company_id' => $company->id,
        ], $attributes));
    }
}
