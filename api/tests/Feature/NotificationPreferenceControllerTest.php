<?php

namespace Tests\Feature;

use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
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

    private function employee(): Employee
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);

        return Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);
    }
}
