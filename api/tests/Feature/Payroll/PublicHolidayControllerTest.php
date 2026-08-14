<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1811 — API jours fériés : RBAC super-admin (national) vs manager
 * principal (entreprise uniquement) + isolation tenant.
 */
class PublicHolidayControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $companyA;

    protected Company $companyB;

    protected SuperAdmin $superAdmin;

    protected Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        $this->companyB = $companyB;

        /** @var SuperAdmin $superAdmin */
        $superAdmin = SuperAdmin::query()->create([
            'name' => 'Super Admin Test',
            'email' => 'sa-holidays-test@leopardo-rh.com',
            'password_hash' => bcrypt('secret123'),
            'role' => 'super_admin',
        ]);
        $this->superAdmin = $superAdmin;

        /** @var Employee $principalA */
        $principalA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $this->principalA = $principalA;
    }

    public function test_platform_admin_can_manage_national_holidays(): void
    {
        Sanctum::actingAs($this->superAdmin, ['*'], 'super_admin_api');

        $response = $this->postJson('/api/v1/admin/public-holidays', [
            'country_code' => 'DZ',
            'name' => 'Jour de l\'an',
            'date' => '2026-01-01',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '01-01',
            'holiday_type' => 'fixed',
        ])->assertStatus(201);

        $id = $response->json('data.id');
        $this->assertNotNull($id);
        $this->assertNull($response->json('data.company_id'));

        $this->getJson('/api/v1/admin/public-holidays?country_code=DZ&year=2026')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/admin/public-holidays/{$id}", [
            'country_code' => 'DZ',
            'name' => 'Jour de l\'an (révisé)',
            'date' => '2026-01-01',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '01-01',
            'holiday_type' => 'fixed',
        ])->assertStatus(200);

        $this->deleteJson("/api/v1/admin/public-holidays/{$id}")->assertStatus(204);
        $this->getJson('/api/v1/admin/public-holidays?country_code=DZ&year=2026')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_principal_can_manage_own_company_holidays_only(): void
    {
        Sanctum::actingAs($this->principalA);

        $response = $this->postJson('/api/v1/public-holidays', [
            'country_code' => 'DZ',
            'name' => 'Pont entreprise A',
            'date' => '2026-07-15',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ])->assertStatus(201);

        $this->assertSame((int) $this->companyA->id, (int) $response->json('data.company_id'));
        $id = $response->json('data.id');

        // Un principal ne voit que ses fériés + les nationaux.
        PublicHoliday::create([
            'company_id' => $this->companyB->id,
            'country_code' => 'DZ',
            'name' => 'Pont entreprise B',
            'date' => '2026-07-16',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        $this->getJson('/api/v1/public-holidays?country_code=DZ&year=2026')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data'); // seulement le sien, pas celui de B

        // Suppression OK sur le sien.
        $this->deleteJson("/api/v1/public-holidays/{$id}")->assertStatus(204);
    }

    public function test_principal_cannot_modify_another_company_holiday(): void
    {
        /** @var PublicHoliday $otherHoliday */
        $otherHoliday = PublicHoliday::create([
            'company_id' => $this->companyB->id,
            'country_code' => 'DZ',
            'name' => 'Pont entreprise B',
            'date' => '2026-07-16',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        Sanctum::actingAs($this->principalA);

        $this->putJson("/api/v1/public-holidays/{$otherHoliday->id}", [
            'country_code' => 'DZ',
            'name' => 'Tentative',
            'date' => '2026-07-16',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ])->assertStatus(403);

        $this->deleteJson("/api/v1/public-holidays/{$otherHoliday->id}")->assertStatus(403);
    }

    public function test_principal_cannot_modify_national_holiday(): void
    {
        /** @var PublicHoliday $national */
        $national = PublicHoliday::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Fête nationale',
            'date' => '2026-07-05',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '07-05',
            'holiday_type' => 'fixed',
        ]);

        Sanctum::actingAs($this->principalA);

        $this->deleteJson("/api/v1/public-holidays/{$national->id}")->assertStatus(403);
    }

    public function test_holiday_crud_requires_authentication(): void
    {
        $this->getJson('/api/v1/public-holidays?country_code=DZ&year=2026')->assertStatus(401);
        $this->postJson('/api/v1/public-holidays', [])->assertStatus(401);
        $this->getJson('/api/v1/admin/public-holidays?country_code=DZ&year=2026')->assertStatus(401);
    }

    public function test_company_holiday_affects_working_days_for_that_company_only(): void
    {
        // Férié national 2026-11-01 (DZ).
        PublicHoliday::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Fête de la Révolution',
            'date' => '2026-11-01',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '11-01',
            'holiday_type' => 'fixed',
        ]);

        // Férié d'entreprise A le 2026-11-09 (lundi, jour ouvré).
        PublicHoliday::create([
            'company_id' => $this->companyA->id,
            'country_code' => 'DZ',
            'name' => 'Pont A',
            'date' => '2026-11-09',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        Cache::flush();

        $service = app(\App\Modules\Payroll\Infrastructure\Services\PublicHolidayService::class);

        $companyA = $service->workingDaysBetween(
            \Illuminate\Support\Carbon::parse('2026-11-01'),
            \Illuminate\Support\Carbon::parse('2026-11-30'),
            'DZ',
            companyId: (int) $this->companyA->id,
            restDays: [5, 6],
        );
        $companyB = $service->workingDaysBetween(
            \Illuminate\Support\Carbon::parse('2026-11-01'),
            \Illuminate\Support\Carbon::parse('2026-11-30'),
            'DZ',
            companyId: (int) $this->companyB->id,
            restDays: [5, 6],
        );

        $this->assertSame(20.0, $companyA); // 21 - pont du 09/11
        $this->assertSame(21.0, $companyB); // sans le pont
    }
}
