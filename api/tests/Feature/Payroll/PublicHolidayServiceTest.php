<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PublicHoliday;
use App\Modules\Payroll\Infrastructure\Services\PublicHolidayService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1811 — PublicHolidayService : jours ouvrés dynamiques par pays.
 */
class PublicHolidayServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le service cache par pays/année (Redis en CI) : purger le cache
        // entre les tests pour éviter qu'un jeu de fériés d'un test ne
        // pollue les assertions du suivant (clés identiques DZ/2026).
        Cache::flush();
    }

    private function service(): PublicHolidayService
    {
        return new PublicHolidayService(Cache::store());
    }

    public function test_working_days_excludes_holidays_and_weekends(): void
    {
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

        // Nov 2026 DZ : week-end vendredi/samedi (8 jours) + 1er nov férié
        // (dimanche, jour ouvré en DZ) → 30 - 8 - 1 = 21 jours ouvrés.
        $days = $this->service()->workingDaysBetween(
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
            'DZ',
            restDays: [5, 6],
        );

        $this->assertSame(21.0, $days);
    }

    public function test_company_holiday_overrides_national(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        PublicHoliday::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Fête nationale',
            'date' => '2026-07-05',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '07-05',
            'holiday_type' => 'fixed',
        ]);

        // Férié d'entreprise supplémentaire le 2026-07-15 (jour ouvré).
        PublicHoliday::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Pont interne',
            'date' => '2026-07-15',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        $national = $this->service()->workingDaysBetween(
            Carbon::parse('2026-07-13'),
            Carbon::parse('2026-07-19'),
            'DZ',
            companyId: null,
            restDays: [5, 6],
        );

        $withCompany = $this->service()->workingDaysBetween(
            Carbon::parse('2026-07-13'),
            Carbon::parse('2026-07-19'),
            'DZ',
            companyId: (string) $company->id,
            restDays: [5, 6],
        );

        // Semaine du 13/07/2026 : lun 13, mar 14, mer 15, jeu 16, ven 17 (repos), sam 18 (repos), dim 19.
        $this->assertSame(4.0, $national);
        $this->assertSame(3.0, $withCompany); // le pont du 15 retire un jour ouvré
    }

    public function test_fallback_when_no_holidays_configured(): void
    {
        // Pays sans fériés en base → calendrier hors week-ends (≈ 22 mensuel).
        $days = $this->service()->workingDaysBetween(
            Carbon::parse('2026-11-01'),
            Carbon::parse('2026-11-30'),
            'MA',
            restDays: [6, 7],
        );

        $this->assertSame(21.0, $days);
    }

    public function test_get_holidays_returns_national_and_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        PublicHoliday::create([
            'company_id' => null,
            'country_code' => 'DZ',
            'name' => 'Jour de l\'an',
            'date' => '2026-01-01',
            'year' => 2026,
            'is_recurring' => true,
            'month_day' => '01-01',
            'holiday_type' => 'fixed',
        ]);
        PublicHoliday::create([
            'company_id' => $company->id,
            'country_code' => 'DZ',
            'name' => 'Pont entreprise',
            'date' => '2026-01-02',
            'year' => 2026,
            'is_recurring' => false,
            'holiday_type' => 'custom',
        ]);

        $national = $this->service()->getHolidays('DZ', 2026);
        $withCompany = $this->service()->getHolidays('DZ', 2026, (string) $company->id);

        $this->assertCount(1, $national);
        $this->assertCount(2, $withCompany);
    }
}
