<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Application\Actions\CreateLeaveAccrual;
use App\Modules\Planning\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveEntitlementService;
use Illuminate\Testing\PendingCommand;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-022 (issue #8146) — zone 2/5 : règles de solde de congés en GOLDEN
 * TESTS (références calculées à la main et documentées ici, convention
 * constitution).
 *
 * Formule canonique du disponible (snapshot leave_balances, #2418/#2666) :
 *
 *   disponible = balance − used − pending
 *
 * Références de calcul :
 *  - entitlement FR (Code du travail L3141-3) : 30 j/an = 2,5 j/mois,
 *    prorata mois complets (mois d'embauche compté si embauche ≤ 15) ;
 *  - carry-forward (#2416) : reporté = min(max(0, balance − used − pending),
 *    carry_forward_max).
 */
class LeaveBalanceGoldenTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private Employee $manager;

    private AbsenceType $type;

    private AbsenceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->manager = Employee::factory()->managerRh()->create(['company_id' => $this->company->id]);
        $this->type = AbsenceType::factory()->create([
            'company_id' => $this->company->id,
            'deducts_leave' => true,
            'is_paid' => true,
        ]);
        $this->service = app(AbsenceService::class);
    }

    private function creditBalance(float $days, int $year = 2026): LeaveBalance
    {
        return LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => $year,
            'balance' => $days,
            'used' => 0,
            'pending' => 0,
        ]);
    }

    public function test_golden_available_balance_formula_on_snapshot(): void
    {
        // Golden : 25 − 5 (used) − 3 (pending) = 17 disponibles.
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => 2026,
            'balance' => 25,
            'used' => 5,
            'pending' => 3,
        ]);

        $this->assertSame(
            17.0,
            $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026)
        );
    }

    public function test_golden_two_pending_requests_reserve_cumulatively(): void
    {
        $this->creditBalance(25.0);

        // Demande 1 : lun 06 → mer 08/04/2026 = 3 j ouvrés. Dispo : 25 − 3 = 22.
        $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);
        $this->assertSame(22.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));

        // Demande 2 : lun 13 → jeu 16/04/2026 = 4 j ouvrés. Dispo : 22 − 4 = 18
        // (la réservation pending s'additionne — garde anti sur-réservation #2418).
        $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-16',
        ]);
        $this->assertSame(18.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));

        $snapshot = LeaveBalance::query()
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->type->id)
            ->where('year', 2026)
            ->firstOrFail();
        $this->assertSame(7.0, (float) $snapshot->pending); // 3 + 4
        $this->assertSame(0.0, (float) $snapshot->used);
    }

    public function test_golden_cancel_pending_releases_reserved_days(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);
        $this->assertSame(22.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));

        // Annulation : pending 3 → 0, disponible rendu intégralement (25).
        $this->service->cancel($absence);
        $this->assertSame(25.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));
    }

    public function test_golden_insufficient_balance_throws_with_exact_amounts(): void
    {
        // Golden : solde 3, déjà 2 pending → disponible 1 ; demande de 2 j
        // (lun 06 → mar 07/04/2026) → exception portant les montants exacts.
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => 2026,
            'balance' => 3,
            'used' => 0,
            'pending' => 2,
        ]);

        try {
            $this->service->create($this->employee, [
                'absence_type_id' => $this->type->id,
                'start_date' => '2026-04-06',
                'end_date' => '2026-04-07',
            ]);
            $this->fail('InsufficientLeaveBalanceException attendue.');
        } catch (InsufficientLeaveBalanceException $e) {
            $this->assertSame('INSUFFICIENT_LEAVE_BALANCE', $e->errorCode());
            $this->assertSame(
                'Insufficient leave balance. Available: 1 days, requested: 2 days.',
                $e->getMessage()
            );
        }
    }

    public function test_golden_accrual_credits_balance_via_canonical_action(): void
    {
        $policy = LeavePolicy::query()->create([
            'company_id' => $this->company->id,
            'absence_type_id' => $this->type->id,
            'name' => 'Congés annuels',
            'accrual_type' => 'yearly',
            'accrual_amount' => 10,
            'carry_forward' => false,
            'active' => true,
        ]);

        $action = app(CreateLeaveAccrual::class);

        // Golden : crédit de 10 j au 15/01/2026 → snapshot de l'année 2026
        // créé à 10 (firstOrCreate), puis second crédit de 5 → 15.
        $action->execute($this->manager, [
            'employee_id' => $this->employee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 10,
            'type' => 'accrual',
            'description' => 'Acquisition annuelle',
            'effective_date' => '2026-01-15',
        ]);
        $action->execute($this->manager, [
            'employee_id' => $this->employee->id,
            'leave_policy_id' => $policy->id,
            'amount' => 5,
            'type' => 'manual_adjustment',
            'description' => 'Ajustement manuel',
            'effective_date' => '2026-03-01',
        ]);

        $snapshot = LeaveBalance::query()
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->type->id)
            ->where('year', 2026)
            ->firstOrFail();
        $this->assertSame(15.0, (float) $snapshot->balance);
        $this->assertSame(2, LeaveAccrual::query()->where('employee_id', $this->employee->id)->count());
    }

    public function test_golden_balance_is_tracked_independently_per_year(): void
    {
        // Golden : 10 j en 2025 et 25 j en 2026 — le disponible se lit par
        // (type, année) ; aucune fuite entre exercices.
        $this->creditBalance(10.0, 2025);
        $this->creditBalance(25.0, 2026);

        $this->assertSame(10.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2025));
        $this->assertSame(25.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));
    }

    public function test_golden_entitlement_prorata_hire_on_or_before_15th(): void
    {
        $service = app(LegalLeaveEntitlementService::class);

        // Golden FR : embauche le 10/03/2026 (≤ 15 → mars compte entier)
        // → 10 mois (mars…décembre) × 2,5 j = 25,0 j (plafond 30 non atteint).
        $employee = new Employee(['contract_start' => '2026-03-10']);
        $this->assertSame(25.0, $service->projectedEntitlement($employee, 2026, 'FR'));
    }

    public function test_golden_entitlement_prorata_hire_after_15th_and_full_year_cap(): void
    {
        $service = app(LegalLeaveEntitlementService::class);

        // Golden FR : embauche le 20/03/2026 (> 15 → acquisition dès avril)
        // → 9 mois × 2,5 j = 22,5 j.
        $lateHire = new Employee(['contract_start' => '2026-03-20']);
        $this->assertSame(22.5, $service->projectedEntitlement($lateHire, 2026, 'FR'));

        // Golden FR : ancienneté couvrant toute l'année → 12 × 2,5 = 30,
        // plafonné au droit annuel légal (30,0).
        $fullYear = new Employee(['contract_start' => '2024-06-01']);
        $this->assertSame(30.0, $service->projectedEntitlement($fullYear, 2026, 'FR'));
    }

    public function test_golden_carry_forward_caps_at_max_and_deducts_pending(): void
    {
        $fromYear = now()->year - 1;
        $toYear = now()->year;

        $policy = LeavePolicy::query()->create([
            'company_id' => $this->company->id,
            'absence_type_id' => $this->type->id,
            'name' => 'Congés annuels',
            'accrual_type' => 'yearly',
            'accrual_amount' => 10,
            'carry_forward' => true,
            'carry_forward_max' => 5,
            'active' => true,
        ]);

        /** @var Employee $other */
        $other = Employee::factory()->create(['company_id' => $this->company->id]);

        // Employé A : balance 10, used 2, pending 0 → non utilisé 8,
        // plafonné au max politique → reporté 5.
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->type->id,
            'year' => $fromYear,
            'balance' => 10,
            'used' => 2,
            'pending' => 0,
        ]);

        // Employé B : balance 10, used 0, pending 6 → reportable 10 − 0 − 6 = 4
        // (#2416 : les jours réservés ne sont pas reportés), sous le max.
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $other->id,
            'absence_type_id' => $this->type->id,
            'year' => $fromYear,
            'balance' => 10,
            'used' => 0,
            'pending' => 6,
        ]);

        $carry = $this->artisan('leave:carry-forward', ['--year' => $fromYear]);
        $this->assertInstanceOf(PendingCommand::class, $carry);
        // La commande ne s'exécute qu'au run() explicite (#5201).
        $carry->assertExitCode(0);
        $carry->run();

        $carriedA = (float) LeaveAccrual::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->where('leave_policy_id', $policy->id)
            ->where('type', 'carry_forward')
            ->sum('amount');
        $carriedB = (float) LeaveAccrual::withoutGlobalScopes()
            ->where('employee_id', $other->id)
            ->where('leave_policy_id', $policy->id)
            ->where('type', 'carry_forward')
            ->sum('amount');

        $this->assertSame(5.0, $carriedA);
        $this->assertSame(4.0, $carriedB);

        $newBalanceA = LeaveBalance::withoutGlobalScopes()
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->type->id)
            ->where('year', $toYear)
            ->firstOrFail();
        $this->assertSame(5.0, (float) $newBalanceA->balance);
    }
}
