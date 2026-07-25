<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Infrastructure\Services\EmployeeEmailLookupService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-COMM-007 - Inbound email provider bounce/complaint webhook.
 *
 * `EmployeeEmailLookupService` relies on Postgres-only catalog lookups
 * (`public.user_lookups`, cross-schema `search_path`), which the default
 * sqlite test connection cannot exercise end-to-end. These tests swap in a
 * lightweight stub for that one dependency so the controller's own
 * behaviour (shared-secret check, bounce stamping, audit trail) is
 * covered without needing a live Postgres connection.
 */
class EmailBounceWebhookControllerTest extends TestCase
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

    public function test_hard_bounce_stamps_employee_and_records_audit_event(): void
    {
        $employee = $this->employee();
        $this->bindLookupStub($employee);

        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => $employee->email,
            'event' => 'bounce',
            'reason' => 'mailbox does not exist',
        ]);

        $response->assertOk()->assertJsonPath('received', true);

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh->email_bounced_at);
        $this->assertSame('mailbox does not exist', $fresh->email_bounce_reason);

        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'email',
            'event_name' => 'email_provider_webhook',
            'status' => 'bounced',
            'error_message' => 'mailbox does not exist',
        ]);
    }

    public function test_delivered_event_is_recorded_without_stamping_a_bounce(): void
    {
        $employee = $this->employee();
        $this->bindLookupStub($employee);

        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => $employee->email,
            'event' => 'delivered',
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertNull($employee->fresh()->email_bounced_at);

        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'event_name' => 'email_provider_webhook',
            'status' => 'recorded',
        ]);
    }

    public function test_unknown_email_is_acknowledged_without_side_effects(): void
    {
        $this->bindLookupStub(null);

        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => 'unknown@example.test',
            'event' => 'bounce',
        ]);

        $response->assertOk()->assertJsonPath('received', true);
        $this->assertSame(0, CommunicationEvent::query()->count());
    }

    public function test_invalid_shared_secret_is_rejected(): void
    {
        config()->set('services.mail_bounce_webhook.secret', 'super-secret');
        $employee = $this->employee();
        $this->bindLookupStub($employee);

        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => $employee->email,
            'event' => 'bounce',
        ], ['X-Bounce-Webhook-Secret' => 'wrong-secret']);

        $response->assertStatus(400);
        $this->assertNull($employee->fresh()->email_bounced_at);
    }

    public function test_valid_shared_secret_is_accepted(): void
    {
        config()->set('services.mail_bounce_webhook.secret', 'super-secret');
        $employee = $this->employee();
        $this->bindLookupStub($employee);

        $response = $this->postJson('/api/v1/webhooks/email-bounce', [
            'email' => $employee->email,
            'event' => 'bounce',
        ], ['X-Bounce-Webhook-Secret' => 'super-secret']);

        $response->assertOk();
        $this->assertNotNull($employee->fresh()->email_bounced_at);
    }

    private function bindLookupStub(?Employee $employee): void
    {
        $this->app->bind(EmployeeEmailLookupService::class, fn () => new class($employee) extends EmployeeEmailLookupService
        {
            public function __construct(private readonly ?Employee $stubbed) {}

            public function resolve(string $email): ?Employee
            {
                return $this->stubbed;
            }
        });
    }

    private function employee(): Employee
    {
        $company = Company::factory()->create();

        return Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'bounce-target-'.$company->id.'@example.test',
        ]);
    }
}
