<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Domain\Exceptions\UnsupportedLeaveCountryException;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveEntitlementService;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveRulesService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Issue #5289 — droit légal de congés projeté depuis l'ancienneté.
 *
 * Golden tests par pays (US2) : prorata mois entiers, plafond annuel,
 * pays non supporté → exception explicite (aucun fallback silencieux).
 */
class LegalLeaveEntitlementServiceTest extends TestCase
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

    public function test_dz_ten_months_worked_yields_25_days(): void
    {
        // Embauché le 15/03/2026 → 10 mois entiers (mars → décembre inclus).
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(10, $this->service->monthsWorkedInYear($employee, 2026));
        $this->assertSame(25.0, $this->service->projectedEntitlement($employee, 2026, 'DZ'));
    }

    public function test_dz_hire_after_the_15th_starts_next_month(): void
    {
        // Embauché le 16/03/2026 → l'acquisition démarre en avril (9 mois).
        $employee = $this->employeeWithContractStart('2026-03-16');

        $this->assertSame(9, $this->service->monthsWorkedInYear($employee, 2026));
        $this->assertSame(22.5, $this->service->projectedEntitlement($employee, 2026, 'DZ'));
    }

    public function test_full_year_is_capped_at_annual_entitlement(): void
    {
        // Ancienneté antérieure à l'année cible → 12 mois, plafonné à 30 j (DZ).
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(12, $this->service->monthsWorkedInYear($employee, 2026));
        $this->assertSame(30.0, $this->service->projectedEntitlement($employee, 2026, 'DZ'));
    }

    public function test_hire_after_year_end_yields_zero(): void
    {
        $employee = $this->employeeWithContractStart('2027-01-05');

        $this->assertSame(0, $this->service->monthsWorkedInYear($employee, 2026));
        $this->assertSame(0.0, $this->service->projectedEntitlement($employee, 2026, 'DZ'));
    }

    public function test_missing_contract_start_defaults_to_year_start(): void
    {
        $employee = $this->employeeWithContractStart(null);

        $this->assertSame(12, $this->service->monthsWorkedInYear($employee, 2026));
    }

    public function test_morocco_ten_months_yields_20_days(): void
    {
        // MA : 24 j/an → 2 j/mois.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(20.0, $this->service->projectedEntitlement($employee, 2026, 'MA'));
    }

    public function test_tunisia_ten_months_yields_25_days(): void
    {
        // TN : 30 j/an → 2,5 j/mois.
        $employee = $this->employeeWithContractStart('2026-03-15');

        $this->assertSame(25.0, $this->service->projectedEntitlement($employee, 2026, 'TN'));
    }

    public function test_senegal_full_year_yields_26_days(): void
    {
        // SN : 26 j/an → ≈ 2,17 j/mois, plafonné à 26.
        $employee = $this->employeeWithContractStart('2025-01-10');

        $this->assertSame(26.0, $this->service->projectedEntitlement($employee, 2026, 'SN'));
    }

    public function test_unsupported_country_throws_explicit_exception(): void
    {
        $this->expectException(UnsupportedLeaveCountryException::class);

        $employee = $this->employeeWithContractStart('2025-01-10');
        $this->service->projectedEntitlement($employee, 2026, 'XX');
    }

    public function test_unsupported_country_throws_via_rules_service(): void
    {
        $this->expectException(UnsupportedLeaveCountryException::class);

        $service = $this->app->make(LegalLeaveRulesService::class);
        $service->resolveForCountry('XX');
    }
}
