<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class BackfillNotificationPreferencesCommandTest extends TestCase
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

    public function test_command_backfills_missing_active_employee_preferences(): void
    {
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'preferred_language' => 'fr',
        ]);

        $exitCode = Artisan::call('notifications:backfill-preferences');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('notification_preferences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'locale' => 'fr',
            'timezone' => 'Africa/Algiers',
        ]);
    }

    public function test_command_repairs_existing_preference_company_scope(): void
    {
        $company = Company::factory()->create(['timezone' => 'Europe/Istanbul']);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        NotificationPreference::query()->create([
            'company_id' => '00000000-0000-0000-0000-000000000000',
            'employee_id' => $employee->id,
            'app_enabled' => true,
            'email_enabled' => false,
            'push_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'categories' => ['hr' => true],
        ]);

        $exitCode = Artisan::call('notifications:backfill-preferences');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('notification_preferences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'email_enabled' => false,
            'timezone' => 'Europe/Istanbul',
        ]);
        $this->assertSame(1, NotificationPreference::query()->where('employee_id', $employee->id)->count());
    }
}
