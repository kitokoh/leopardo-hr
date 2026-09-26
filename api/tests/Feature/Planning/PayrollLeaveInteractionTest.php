<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollWorkInputAggregator;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-022 (issue #8146) — zone 5/5 : interaction Payroll — impact d'une
 * absence approuvée sur les intrants de paie (jours de congé payé / non
 * payé agrégés par run, #2672).
 *
 * Golden tests (calcul documenté) du clipping période :
 *
 *   jours comptés = days_count × (jours calendaires d'intersection
 *                                  avec la période / jours calendaires
 *                                  totaux de l'absence)
 *
 * Scénario de référence : absence approuvée lun 30/03 → ven 03/04/2026
 * (span = 5 jours calendaires, days_count = 5,0) :
 *   - run d'avril (01/04 → 30/04) : intersection 3 j → 5 × 3/5 = 3,0
 *   - run de mars  (01/03 → 31/03) : intersection 2 j → 5 × 2/5 = 2,0
 * L'absence n'est comptée ni deux fois ni en totalité sur un seul mois.
 */
class PayrollLeaveInteractionTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private AbsenceType $paidType;

    private AbsenceType $unpaidType;

    private PayrollWorkInputAggregator $aggregator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->paidType = AbsenceType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => true,
            'deducts_leave' => true,
        ]);
        $this->unpaidType = AbsenceType::factory()->create([
            'company_id' => $this->company->id,
            'is_paid' => false,
            'deducts_leave' => false,
        ]);
        $this->aggregator = app(PayrollWorkInputAggregator::class);
    }

    private function approvedAbsence(string $start, string $end, float $days, AbsenceType $type, ?Employee $employee = null): Absence
    {
        return Absence::factory()->approved()->create([
            'company_id' => $this->company->id,
            'employee_id' => ($employee ?? $this->employee)->id,
            'absence_type_id' => $type->id,
            'start_date' => $start,
            'end_date' => $end,
            'days_count' => $days,
        ]);
    }

    private function runFor(string $periodStart, string $periodEnd): PayrollRun
    {
        // Run non persisté : l'agrégateur ne lit que company_id / period_*.
        return new PayrollRun([
            'company_id' => $this->company->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }

    public function test_approved_paid_absence_fully_inside_period_counts_full_days(): void
    {
        // Golden : lun 06 → mer 08/04/2026, entièrement dans le run d'avril
        // → 3,0 jours de congé payé, 0 non payé, 0 h sup.
        $this->approvedAbsence('2026-04-06', '2026-04-08', 3.0, $this->paidType);

        $inputs = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-04-01', '2026-04-30'),
            $this->employee
        );

        $this->assertSame(3.0, $inputs['paid_leave_days']);
        $this->assertSame(0.0, $inputs['unpaid_leave_days']);
        $this->assertSame(0.0, $inputs['overtime_hours']);
    }

    public function test_absence_spanning_period_boundary_is_clipped_prorata(): void
    {
        // Golden documenté en en-tête : avril → 3,0 ; mars → 2,0 (jamais 5
        // des deux côtés — double déduction #2672).
        $this->approvedAbsence('2026-03-30', '2026-04-03', 5.0, $this->paidType);

        $april = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-04-01', '2026-04-30'),
            $this->employee
        );
        $march = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-03-01', '2026-03-31'),
            $this->employee
        );

        $this->assertSame(3.0, $april['paid_leave_days']);
        $this->assertSame(2.0, $march['paid_leave_days']);
        $this->assertSame(5.0, $april['paid_leave_days'] + $march['paid_leave_days']);
    }

    public function test_unpaid_absence_type_counts_as_unpaid_leave_days(): void
    {
        // Golden : type non payé → unpaid_leave_days, jamais paid_leave_days.
        $this->approvedAbsence('2026-04-06', '2026-04-09', 4.0, $this->unpaidType);

        $inputs = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-04-01', '2026-04-30'),
            $this->employee
        );

        $this->assertSame(0.0, $inputs['paid_leave_days']);
        $this->assertSame(4.0, $inputs['unpaid_leave_days']);
    }

    public function test_pending_and_rejected_absences_are_not_counted(): void
    {
        Absence::factory()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->paidType->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
            'days_count' => 3.0,
            'status' => 'pending',
        ]);
        Absence::factory()->rejected()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->paidType->id,
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-15',
            'days_count' => 3.0,
        ]);

        // Seul le statut `approved` alimente la paie.
        $inputs = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-04-01', '2026-04-30'),
            $this->employee
        );

        $this->assertSame(0.0, $inputs['paid_leave_days']);
        $this->assertSame(0.0, $inputs['unpaid_leave_days']);
    }

    public function test_cross_tenant_absence_is_not_counted(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        /** @var Employee $otherEmployee */
        $otherEmployee = Employee::factory()->create(['company_id' => $otherCompany->id]);
        /** @var AbsenceType $otherType */
        $otherType = AbsenceType::factory()->create([
            'company_id' => $otherCompany->id,
            'is_paid' => true,
        ]);

        Absence::factory()->approved()->create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'absence_type_id' => $otherType->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
            'days_count' => 5.0,
        ]);

        // Isolation tenant : l'absence d'un autre tenant ne fuite pas dans
        // le run de MA société (filtre company_id du run).
        $inputs = $this->aggregator->collectWorkInputs(
            $this->runFor('2026-04-01', '2026-04-30'),
            $this->employee
        );

        $this->assertSame(0.0, $inputs['paid_leave_days']);
    }
}
