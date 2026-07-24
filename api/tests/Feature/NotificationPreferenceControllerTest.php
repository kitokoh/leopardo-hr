<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\CommunicationEvent;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class NotificationPreferenceControllerTest extends TestCase
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

    public function test_preferences_require_authentication(): void
    {
        $this->getJson('/api/v1/notification-preferences')
            ->assertUnauthorized();
    }

    public function test_show_creates_default_preferences_for_authenticated_employee(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.app_enabled', true)
            ->assertJsonPath('data.email_enabled', true)
            ->assertJsonPath('data.sms_enabled', false);

        $this->assertDatabaseHas('notification_preferences', [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_update_persists_preferences_and_records_communication_event(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($employee);

        $this->patchJson('/api/v1/notification-preferences', [
            'email_enabled' => false,
            'push_enabled' => true,
            'sms_enabled' => true,
            'locale' => 'fr',
            'categories' => [
                'absence' => true,
                'payroll' => false,
            ],
            'quiet_hours' => [
                'enabled' => true,
                'start' => '20:00',
                'end' => '07:00',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.email_enabled', false)
            ->assertJsonPath('data.sms_enabled', true)
            ->assertJsonPath('data.locale', 'fr');

        $this->assertSame(1, NotificationPreference::query()->where('employee_id', $employee->id)->count());
        $this->assertDatabaseHas('communication_events', [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'event_name' => 'notification_preferences_updated',
            'channel' => 'app',
        ]);
    }

    public function test_update_rejects_invalid_locale_and_quiet_hour_format(): void
    {
        Sanctum::actingAs($this->employee());

        $this->patchJson('/api/v1/notification-preferences', [
            'locale' => 'de',
            'quiet_hours' => [
                'enabled' => true,
                'start' => 'soir',
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, CommunicationEvent::query()->count());
    }

    /**
     * PA2-COMM-008 — giving WhatsApp consent through the API stamps a
     * server-side timestamp; the client cannot forge it.
     */
    public function test_update_stamps_whatsapp_consent_timestamp_when_consent_is_given(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($employee);

        $response = $this->patchJson('/api/v1/notification-preferences', [
            'whatsapp_enabled' => true,
            'whatsapp_consent_given' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_enabled', true)
            ->assertJsonPath('data.whatsapp_consent_given', true);

        $this->assertNotNull($response->json('data.whatsapp_consent_at'));

        $preference = NotificationPreference::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertTrue($preference->hasWhatsappConsent());
    }

    /**
     * PA2-COMM-008 — withdrawing consent clears the timestamp so a stale
     * value can never be mistaken for still-valid consent.
     */
    public function test_update_clears_whatsapp_consent_timestamp_when_consent_is_withdrawn(): void
    {
        $employee = $this->employee();
        NotificationPreference::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'whatsapp_enabled' => true,
            'whatsapp_consent_given' => true,
            'whatsapp_consent_at' => now(),
        ]);
        Sanctum::actingAs($employee);

        $this->patchJson('/api/v1/notification-preferences', [
            'whatsapp_consent_given' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.whatsapp_consent_given', false)
            ->assertJsonPath('data.whatsapp_consent_at', null);

        $preference = NotificationPreference::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertFalse($preference->hasWhatsappConsent());
    }

    private function employee(): Employee
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);

        return Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);
    }
}
