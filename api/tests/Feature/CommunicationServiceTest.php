<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class CommunicationServiceTest extends TestCase
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

    public function test_it_creates_app_notification_and_audits_dispatch(): void
    {
        $employee = $this->employee();

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [
            'absence_id' => 42,
            'payroll_amount' => 990000,
        ], ['app']);

        $this->assertSame('sent', $result['results']['app']);
        $this->assertDatabaseHas('notifications', [
            'id' => $result['notification_id'],
            'employee_id' => $employee->id,
            'type' => 'hr',
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'notification_id' => $result['notification_id'],
            'event_name' => 'communication_dispatched',
            'channel' => 'app',
            'status' => 'sent',
            'template_key' => 'absence_approved',
        ]);

        $event = CommunicationEvent::query()->firstOrFail();
        $this->assertSame(['absence_id' => 42], $event->metadata);
    }

    public function test_it_respects_disabled_preferences(): void
    {
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => false,
            'email_enabled' => false,
            'push_enabled' => false,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'categories' => ['hr' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_rejected', [], ['app', 'push']);

        $this->assertSame('skipped', $result['results']['app']);
        $this->assertSame('skipped', $result['results']['push']);
        $this->assertSame(0, Notification::query()->count());
        $this->assertSame(2, CommunicationEvent::query()->where('status', 'skipped')->count());
    }

    public function test_email_sms_and_whatsapp_use_safe_audited_fallbacks(): void
    {
        Mail::fake();
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
            'categories' => ['payroll' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'payroll_ready', [
            'payroll_run_id' => 7,
            'net_salary' => 123456,
        ], ['email', 'sms', 'whatsapp']);

        $this->assertSame('queued', $result['results']['email']);
        $this->assertSame('queued', $result['results']['sms']);
        $this->assertSame('queued', $result['results']['whatsapp']);
        $this->assertSame(3, CommunicationEvent::query()->where('template_key', 'payroll_ready')->count());

        CommunicationEvent::query()
            ->whereIn('channel', ['email', 'sms', 'whatsapp'])
            ->get()
            ->each(function (CommunicationEvent $event): void {
                $this->assertSame(['payroll_run_id' => 7], $event->metadata);
            });
    }

    public function test_quiet_hours_skip_external_channels_but_keep_app_notifications(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 22:15:00', 'Africa/Algiers'));
        try {
            $employee = $this->employee();
            NotificationPreference::query()->create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'app_enabled' => true,
                'email_enabled' => true,
                'push_enabled' => true,
                'sms_enabled' => true,
                'whatsapp_enabled' => true,
                'timezone' => 'Africa/Algiers',
                'categories' => ['hr' => true],
                'quiet_hours' => [
                    'enabled' => true,
                    'start' => '20:00',
                    'end' => '07:00',
                ],
            ]);

            $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['app', 'email', 'sms']);

            $this->assertSame('sent', $result['results']['app']);
            $this->assertSame('skipped', $result['results']['email']);
            $this->assertSame('skipped', $result['results']['sms']);
            $this->assertSame(1, Notification::query()->count());
            $this->assertSame(2, CommunicationEvent::query()->where('error_message', 'Quiet hours active.')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sms_and_whatsapp_monthly_quota_blocks_extra_dispatch(): void
    {
        config()->set('communication.monthly_channel_quotas.sms', 1);
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'sms_enabled' => true,
            'categories' => ['hr' => true],
        ]);
        CommunicationEvent::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'event_name' => 'communication_dispatched',
            'channel' => 'sms',
            'status' => 'queued',
            'provider' => 'audit',
            'template_key' => 'absence_approved',
            'occurred_at' => now(),
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['sms']);

        $this->assertSame('skipped', $result['results']['sms']);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'sms',
            'status' => 'skipped',
            'error_message' => 'Monthly channel quota exceeded.',
        ]);
    }

    /**
     * PA2-JOB-003 — when an operator opts into the real Twilio-backed
     * providers (`COMMUNICATION_SMS_PROVIDER=twilio` /
     * `COMMUNICATION_WHATSAPP_PROVIDER=twilio` plus TWILIO_* credentials),
     * `CommunicationService` actually calls the Twilio REST API instead of
     * the audit-only fallback.
     */
    public function test_sms_and_whatsapp_dispatch_via_twilio_when_configured(): void
    {
        config()->set('communication.providers.sms', 'twilio');
        config()->set('communication.providers.whatsapp', 'twilio');
        config()->set('services.twilio.account_sid', 'AC_test_sid');
        config()->set('services.twilio.auth_token', 'test_token');
        config()->set('services.twilio.from', '+15550001111');
        config()->set('services.twilio.whatsapp_from', 'whatsapp:+14155238886');

        \Illuminate\Support\Facades\Http::fake([
            'api.twilio.com/*' => \Illuminate\Support\Facades\Http::response(['sid' => 'SM123'], 201),
        ]);

        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'sms_enabled' => true,
            'whatsapp_enabled' => true,
            'categories' => ['payroll' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'payroll_ready', [], ['sms', 'whatsapp']);

        $this->assertSame('queued', $result['results']['sms']);
        $this->assertSame('queued', $result['results']['whatsapp']);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'sms',
            'status' => 'queued',
            'provider' => 'twilio',
        ]);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'whatsapp',
            'status' => 'queued',
            'provider' => 'twilio',
        ]);

        \Illuminate\Support\Facades\Http::assertSentCount(2);
    }

    /**
     * PA2-COMM-007 — a transient email transport failure is retried up to
     * `communication.email_retry.max_attempts` before recording a final
     * `failed` audit event, instead of failing on the very first attempt.
     */
    public function test_email_dispatch_retries_on_transient_failure_then_succeeds(): void
    {
        config()->set('communication.email_retry.base_delay_ms', 0);
        $employee = $this->employee();

        $attempts = 0;
        Mail::shouldReceive('to')
            ->times(2)
            ->andReturnUsing(function () use (&$attempts) {
                $attempts++;
                $pending = \Mockery::mock();
                $pending->shouldReceive('send')->andReturnUsing(function () use ($attempts): void {
                    if ($attempts < 2) {
                        throw new RuntimeException('SMTP timeout');
                    }
                });

                return $pending;
            });

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['email']);

        $this->assertSame('queued', $result['results']['email']);
        $this->assertSame(2, $attempts);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);
    }

    /**
     * PA2-COMM-007 — once every retry attempt has failed, the dispatch is
     * recorded as a final `failed` audit event with the underlying error.
     */
    public function test_email_dispatch_records_failed_status_after_exhausting_retries(): void
    {
        config()->set('communication.email_retry.base_delay_ms', 0);
        config()->set('communication.email_retry.max_attempts', 2);
        $employee = $this->employee();

        Mail::shouldReceive('to')
            ->times(2)
            ->andReturnUsing(function () {
                $pending = \Mockery::mock();
                $pending->shouldReceive('send')->andThrow(new RuntimeException('SMTP connection refused'));

                return $pending;
            });

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['email']);

        $this->assertSame('failed', $result['results']['email']);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'email',
            'status' => 'failed',
            'error_message' => 'SMTP connection refused',
        ]);
    }

    /**
     * PA2-COMM-007 — an employee whose address previously bounced (stamped
     * by `EmailBounceWebhookController`) is skipped instead of retried.
     */
    public function test_email_dispatch_skips_previously_bounced_address(): void
    {
        Mail::fake();
        $employee = $this->employee();
        $employee->forceFill([
            'email_bounced_at' => now(),
            'email_bounce_reason' => 'hard_bounce',
        ])->save();

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['email']);

        $this->assertSame('skipped', $result['results']['email']);
        Mail::assertNothingSent();
    }

    /**
     * PA2-COMM-006 — title/body must be resolved from the localizable
     * `notifications.*` translation keys using the employee's own
     * preferred locale, not a single hardcoded French string.
     */
    public function test_notification_title_and_body_use_employee_preferred_locale(): void
    {
        $employee = $this->employee();
        $employee->forceFill(['preferred_language' => 'en'])->save();

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [], ['app']);

        $this->assertDatabaseHas('notifications', [
            'id' => $result['notification_id'],
            'title' => 'Leave request approved',
            'body' => 'Your leave request has been approved.',
        ]);
    }

    /**
     * When the employee has no explicit preferred_language, the recipient's
     * locale falls back to the tenant company's own default language.
     */
    public function test_notification_falls_back_to_company_language_when_employee_has_none(): void
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers', 'language' => 'en']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.$company->id.'@example.test',
            'preferred_language' => null,
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'security_alert', [], ['app']);

        $this->assertDatabaseHas('notifications', [
            'id' => $result['notification_id'],
            'title' => 'Security alert',
            'body' => 'A sensitive action was just detected on your account.',
        ]);
    }

    /**
     * Template variables declared in `config('communication.templates')`
     * (e.g. `task_comment_added`'s `:task`/`:author`) must be forwarded from
     * the caller context into the translated string.
     */
    public function test_template_variables_are_interpolated_into_translated_body(): void
    {
        $employee = $this->employee();
        $employee->forceFill(['preferred_language' => 'fr'])->save();

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'task_comment_added', [
            'task' => 'Verification materiel',
            'author' => 'Amine K.',
        ], ['app']);

        $this->assertDatabaseHas('notifications', [
            'id' => $result['notification_id'],
            'title' => 'Nouveau commentaire sur « Verification materiel »',
            'body' => 'Amine K. a ajouté un nouveau commentaire sur une tâche qui vous concerne.',
        ]);
    }

    /**
     * Callers may still pass an explicit `title`/`body` in the context to
     * fully override the localized template (used for manager-authored
     * free-text content like announcements).
     */
    public function test_explicit_title_and_body_override_the_localized_template(): void
    {
        $employee = $this->employee();

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'absence_approved', [
            'title' => 'Custom title',
            'body' => 'Custom body',
        ], ['app']);

        $this->assertDatabaseHas('notifications', [
            'id' => $result['notification_id'],
            'title' => 'Custom title',
            'body' => 'Custom body',
        ]);
    }

    private function employee(): Employee
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);

        return Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.$company->id.'@example.test',
        ]);
    }
}
