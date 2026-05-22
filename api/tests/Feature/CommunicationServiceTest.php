<?php

namespace Tests\Feature;

use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Services\Communication\CommunicationService;
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

    private function employee(): Employee
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);

        return Employee::factory()->create([
            'company_id' => $company->id,
            'email' => 'employee-'.$company->id.'@example.test',
        ]);
    }
}
