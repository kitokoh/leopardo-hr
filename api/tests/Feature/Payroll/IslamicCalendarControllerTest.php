<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\IslamicCalendar;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1812 — API calendrier islamique : RBAC super-admin uniquement.
 */
class IslamicCalendarControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected SuperAdmin $superAdmin;

    protected Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-islamic-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $principal */
        $principal = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->principal = $principal;

        IslamicCalendar::create([
            'holiday_key' => 'eid_al_fitr',
            'year' => 2026,
            'gregorian_date' => '2026-03-20',
            'duration_days' => 1,
            'source' => 'computed',
            'confirmed' => false,
        ]);
        IslamicCalendar::create([
            'holiday_key' => 'eid_al_adha',
            'year' => 2026,
            'gregorian_date' => '2026-05-27',
            'duration_days' => 2,
            'source' => 'computed',
            'confirmed' => false,
        ]);
    }

    public function test_platform_admin_can_list_and_confirm_date(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/admin/islamic-calendar?year=2026')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unconfirmed_count', 2);

        // Confirmer une date précise.
        $this->putJson('/api/v1/admin/islamic-calendar/eid_al_fitr/2026', [
            'gregorian_date' => '2026-03-20',
            'duration_days' => 1,
            'confirmed' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.confirmed', true)
            ->assertJsonPath('data.source', 'manual');

        /** @var IslamicCalendar $holiday */
        $holiday = IslamicCalendar::query()->where('holiday_key', 'eid_al_fitr')->where('year', 2026)->firstOrFail();
        $this->assertTrue($holiday->confirmed);
        $this->assertSame($this->superAdmin->id, $holiday->confirmed_by);
    }

    public function test_platform_admin_can_confirm_whole_year(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->postJson('/api/v1/admin/islamic-calendar/confirm-year/2026')
            ->assertStatus(200)
            ->assertJsonPath('data.confirmed_count', 2);

        $this->getJson('/api/v1/admin/islamic-calendar?year=2026')
            ->assertJsonPath('meta.unconfirmed_count', 0);
    }

    public function test_principal_cannot_modify_islamic_calendar(): void
    {
        Sanctum::actingAs($this->principal);

        $this->getJson('/api/v1/admin/islamic-calendar?year=2026')->assertStatus(401);

        $this->putJson('/api/v1/admin/islamic-calendar/eid_al_fitr/2026', [
            'gregorian_date' => '2026-03-21',
            'duration_days' => 1,
        ])->assertStatus(401);

        $this->postJson('/api/v1/admin/islamic-calendar/confirm-year/2026')->assertStatus(401);
    }

    public function test_invalid_holiday_key_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $this->putJson('/api/v1/admin/islamic-calendar/not_a_festival/2026', [
            'gregorian_date' => '2026-03-21',
            'duration_days' => 1,
        ])->assertStatus(404);
    }
}
