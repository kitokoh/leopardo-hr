<?php

declare(strict_types=1);

namespace Tests\Feature\Leave;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Infrastructure\Services\CountryRules\LegalLeaveRulesRegistry;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveEntitlementService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #7930 — congés légaux CEMAC & CEDEAO (lot BC-06).
 *
 * Golden tests calculés à la main pour les 12 nouveaux pays : valeurs
 * annuelles/mensuelles du registre + acquisition projetée (prorata mois
 * entiers, plafond annuel). Miroir de `LegalLeaveRulesRegistryTest` (#5289).
 */
class LegalLeaveCemacCedeaoRulesTest extends TestCase
{
    private LegalLeaveEntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LegalLeaveEntitlementService;
    }

    private function employeeWithContractStart(?string $contractStart): Employee
    {
        $employee = new Employee(['first_name' => 'Test', 'last_name' => 'Employee']);
        $employee->contract_start = $contractStart !== null ? Carbon::parse($contractStart) : null;

        return $employee;
    }

    public function test_registry_resolves_cemac_and_cedeao_countries(): void
    {
        $expected = [
            // CEMAC
            'CM' => ['annual' => 18.0, 'monthly' => 1.5],     // art. 89 CT 1992 : 1,5 j ouvrable/mois
            'GA' => ['annual' => 24.0, 'monthly' => 2.0],     // art. 185 loi 022/2021 : 2 j ouvrables/mois
            'CG' => ['annual' => 26.0, 'monthly' => 2.1667],  // loi 45-75 : 26 j ouvrables/an
            'TD' => ['annual' => 24.0, 'monthly' => 2.0],     // art. 212 loi 038/PR/96 : 2 j ouvrables/mois
            'CF' => ['annual' => 24.0, 'monthly' => 2.0],     // art. 280 s. loi 09.004 : 2 j ouvrables/mois
            'GQ' => ['annual' => 30.0, 'monthly' => 2.5],     // art. 40 ley 4/2021 : 1 mois ≈ 30 j calendaires
            // CEDEAO
            'CI' => ['annual' => 26.4, 'monthly' => 2.2],     // art. 25.1 loi 2015-532 : 2,2 j ouvrables/mois
            'BF' => ['annual' => 30.0, 'monthly' => 2.5],     // art. 156 loi 028-2008 : 2,5 j calendaires/mois
            'ML' => ['annual' => 30.0, 'monthly' => 2.5],     // art. L.148 s. loi 92-020 : 2,5 j/mois
            'TG' => ['annual' => 30.0, 'monthly' => 2.5],     // loi 2021-012 : 2,5 j ouvrables/mois
            'BJ' => ['annual' => 24.0, 'monthly' => 2.0],     // art. 158 loi 98-004 : 2 j ouvrables/mois
            'NE' => ['annual' => 30.0, 'monthly' => 2.5],     // art. 116 s. loi 2012-45 : 2,5 j calendaires/mois
        ];

        foreach ($expected as $countryCode => $values) {
            $rule = LegalLeaveRulesRegistry::resolve($countryCode);

            $this->assertSame($countryCode, $rule->countryCode());
            $this->assertSame($values['annual'], $rule->legalAnnualDays(), "Droit annuel inattendu pour {$countryCode}");
            $this->assertSame($values['monthly'], $rule->accrualDaysPerMonth(), "Acquisition mensuelle inattendue pour {$countryCode}");
            $this->assertNotSame('', $rule->legalSource(), "Source légale manquante pour {$countryCode}");
            $this->assertSame('pilot', $rule->confidenceLevel());
            $this->assertTrue(LegalLeaveRulesRegistry::has($countryCode));
        }
    }

    public function test_cameroon_ten_months_yields_15_days(): void
    {
        // CM : 1,5 j/mois × 10 mois (embauche 15/03/2026 → mars-décembre) = 15 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(15.0, $this->service->projectedEntitlement($employee, 2026, 'CM'));
    }

    public function test_gabon_full_year_yields_24_days(): void
    {
        // GA : 2 j/mois × 12 = 24 j, plafonné à 24.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(24.0, $this->service->projectedEntitlement($employee, 2026, 'GA'));
    }

    public function test_congo_full_year_capped_at_26_days(): void
    {
        // CG : 2,1667 j/mois × 12 = 26,0004 → plafonné au droit annuel 26 j.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(26.0, $this->service->projectedEntitlement($employee, 2026, 'CG'));
    }

    public function test_chad_ten_months_yields_20_days(): void
    {
        // TD : 2 j/mois × 10 mois = 20 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(20.0, $this->service->projectedEntitlement($employee, 2026, 'TD'));
    }

    public function test_central_african_republic_six_months_yields_12_days(): void
    {
        // CF : 2 j/mois × 6 mois (embauche 01/07/2026 → juillet-décembre) = 12 j.
        $employee = $this->employeeWithContractStart('2026-07-01');

        $this->assertSame(12.0, $this->service->projectedEntitlement($employee, 2026, 'CF'));
    }

    public function test_equatorial_guinea_full_year_yields_30_days(): void
    {
        // GQ : 2,5 j/mois × 12 = 30 j.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'GQ'));
    }

    public function test_ivory_coast_ten_months_yields_22_days(): void
    {
        // CI : 2,2 j/mois × 10 mois = 22 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(22.0, $this->service->projectedEntitlement($employee, 2026, 'CI'));
    }

    public function test_ivory_coast_full_year_capped_at_26_4_days(): void
    {
        // CI : 2,2 × 12 = 26,4 j (droit annuel exact).
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(26.4, $this->service->projectedEntitlement($employee, 2026, 'CI'));
    }

    public function test_burkina_faso_ten_months_yields_25_days(): void
    {
        // BF : 2,5 j/mois × 10 mois = 25 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(25.0, $this->service->projectedEntitlement($employee, 2026, 'BF'));
    }

    public function test_mali_full_year_yields_30_days(): void
    {
        // ML : 2,5 j/mois × 12 = 30 j.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'ML'));
    }

    public function test_togo_full_year_yields_30_days(): void
    {
        // TG : 2,5 j/mois × 12 = 30 j.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'TG'));
    }

    public function test_benin_ten_months_yields_20_days(): void
    {
        // BJ : 2 j/mois × 10 mois = 20 j.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(20.0, $this->service->projectedEntitlement($employee, 2026, 'BJ'));
    }

    public function test_niger_nine_months_yields_22_5_days(): void
    {
        // NE : embauche 10/04/2026 → avril-décembre = 9 mois × 2,5 = 22,5 j.
        $employee = $this->employeeWithContractStart('2026-04-10');

        $this->assertSame(22.5, $this->service->projectedEntitlement($employee, 2026, 'NE'));
    }
}
