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
            'whatsapp_consent_given' => true,
            'whatsapp_consent_at' => now(),
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
     * PA2-COMM-008 — WhatsApp messaging requires an explicit, separate
     * opt-in from the plain channel toggle: enabling `whatsapp_enabled`
     * alone must not be enough to actually message the employee.
     */
    public function test_whatsapp_is_skipped_without_explicit_consent_even_when_channel_enabled(): void
    {
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'whatsapp_enabled' => true,
            'whatsapp_consent_given' => false,
            'categories' => ['payroll' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'payroll_ready', [], ['whatsapp']);

        $this->assertSame('skipped', $result['results']['whatsapp']);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'whatsapp',
            'status' => 'skipped',
            'error_message' => 'WhatsApp consent missing.',
        ]);
    }

    /**
     * PA2-COMM-008 — with the channel enabled and consent explicitly
     * given, WhatsApp dispatch proceeds through the configured provider
     * (audit-only by default, since no Meta Cloud API secret is set in
     * tests).
     */
    public function test_whatsapp_is_sent_once_consent_is_explicitly_given(): void
    {
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'whatsapp_enabled' => true,
            'whatsapp_consent_given' => true,
            'whatsapp_consent_at' => now(),
            'categories' => ['payroll' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'payroll_ready', [], ['whatsapp']);

        $this->assertSame('queued', $result['results']['whatsapp']);
        $this->assertDatabaseHas('communication_events', [
            'employee_id' => $employee->id,
            'channel' => 'whatsapp',
            'status' => 'queued',
        ]);
    }

    /**
     * PA2-COMM-008 — even when the `whatsapp_cloud` provider is selected,
     * dispatch must stay on the safe audit-only fallback (never fail hard)
     * whenever the Meta Cloud API secrets are not configured.
     */
    public function test_whatsapp_falls_back_to_audit_only_when_provider_secret_is_missing(): void
    {
        config()->set('communication.providers.whatsapp', 'whatsapp_cloud');
        config()->set('services.whatsapp.phone_number_id', null);
        config()->set('services.whatsapp.access_token', null);

        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'whatsapp_enabled' => true,
            'whatsapp_consent_given' => true,
            'whatsapp_consent_at' => now(),
            'categories' => ['payroll' => true],
        ]);

        $result = app(CommunicationService::class)->notifyEmployee($employee, 'payroll_ready', [], ['whatsapp']);

        $this->assertSame('queued', $result['results']['whatsapp']);
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
