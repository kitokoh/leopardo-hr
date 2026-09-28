<?php

declare(strict_types=1);

namespace Tests\Unit\Payroll;

use App\Modules\Payroll\Infrastructure\Services\PublicHolidayCalendarAdapter;
use App\Shared\Contracts\Payroll\PublicHolidayCalendar;
use Illuminate\Support\Carbon;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-023 (#8211) — contrat partagé « jours fériés » : le container résout
 * `PublicHolidayCalendar` vers l'adapter Payroll, ce qui permet aux modules
 * consommateurs (Planning, congés…) de ne plus importer `Modules/Payroll`
 * (règle d'isolation #5584).
 */
class PublicHolidayCalendarContractTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_contract_resolves_to_payroll_adapter(): void
    {
        $this->assertInstanceOf(
            PublicHolidayCalendarAdapter::class,
            $this->app->make(PublicHolidayCalendar::class),
        );
    }

    public function test_working_days_between_preserves_canonical_fallback(): void
    {
        // Pays sans aucun férié configuré → fallback historique : calendrier
        // moins les jours de repos hebdomadaire (samedi+dimanche par défaut).
        $days = $this->app
            ->make(PublicHolidayCalendar::class)
            ->workingDaysBetween(Carbon::parse('2026-09-28'), Carbon::parse('2026-10-02'), 'XX');

        // lundi 2026-09-28 → vendredi 2026-10-02 = 5 jours ouvrés
        $this->assertSame(5.0, $days);
    }
}
