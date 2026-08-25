<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Planning\Infrastructure\Services\LegalLeaveCalendarService;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveRulesService;
use Database\Seeders\PublicHolidaySeeder;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5289 — calendrier des fériés LÉGAUX par pays (US3).
 *
 * Lecture seule de la table globale `public_holidays` (module Payroll
 * intouché) : fériés nationaux (`company_id = null`) + récurrents (#1936).
 */
class LegalLeaveCalendarServiceTest extends TestCase
{
    use RefreshTenantDatabase;

    private LegalLeaveCalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LegalLeaveCalendarService;
    }

    public function test_dz_legal_holidays_include_the_four_fixed_national_days(): void
    {
        (new PublicHolidaySeeder)->run();

        $holidays = $this->service->legalHolidays('DZ', 2026);

        $dates = array_column($holidays, 'date');

        foreach (['2026-01-01', '2026-05-01', '2026-07-05', '2026-11-01'] as $expectedDate) {
            $this->assertContains($expectedDate, $dates, "Férié légal DZ manquant : {$expectedDate}");
        }
    }

    public function test_recurring_holidays_apply_to_every_year(): void
    {
        (new PublicHolidaySeeder)->run();

        // Le seeder ne stocke que 2024→2027 : un férié récurrent (ex. 05-01)
        // doit ressortir pour 2030 sans ligne dédiée (règle #1936).
        $dates2030 = $this->service->legalHolidayDates('DZ', 2030);

        $this->assertContains('2030-05-01', $dates2030);
    }

    public function test_ma_tn_sn_legal_holidays_are_seeded(): void
    {
        (new PublicHolidaySeeder)->run();

        // Repères fixes officiels par pays (PublicHolidaySeeder).
        $checkpoints = [
            'MA' => '2026-07-30', // Fête du Trône
            'TN' => '2026-03-20', // Fête de l'Indépendance
            'SN' => '2026-04-04', // Fête de l'Indépendance
        ];

        foreach ($checkpoints as $country => $expectedDate) {
            $dates = $this->service->legalHolidayDates($country, 2026);
            $this->assertContains($expectedDate, $dates, "Férié légal {$country} manquant : {$expectedDate}");
        }
    }

    public function test_empty_country_returns_empty_array_without_error(): void
    {
        (new PublicHolidaySeeder)->run();

        // BF est volontairement non seedé (réforme légale 2026, issue #2255) :
        // un pays sans données doit retourner [] sans erreur.
        $this->assertSame([], $this->service->legalHolidays('BF', 2026));
    }

    public function test_is_legal_holiday_matches_known_date(): void
    {
        (new PublicHolidaySeeder)->run();

        $this->assertTrue($this->service->isLegalHoliday('DZ', Carbon::parse('2026-07-05')));
        $this->assertFalse($this->service->isLegalHoliday('DZ', Carbon::parse('2026-07-06')));
    }

    public function test_rules_service_resolves_all_four_registered_countries(): void
    {
        $service = $this->app->make(LegalLeaveRulesService::class);

        $this->assertSame(30.0, $service->resolveForCountry('DZ')->legalAnnualDays());
        $this->assertSame(24.0, $service->resolveForCountry('MA')->legalAnnualDays());
        $this->assertSame(30.0, $service->resolveForCountry('TN')->legalAnnualDays());
        $this->assertSame(26.0, $service->resolveForCountry('SN')->legalAnnualDays());
    }
}
