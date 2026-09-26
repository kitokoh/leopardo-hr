<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Events\AbsenceRequested;
use App\Modules\Planning\Domain\Exceptions\AbsenceDateConflictException;
use App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Infrastructure\Services\AbsenceService;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-022 (issue #8146) — zone 1/5 : cycle de vie absence porté par le
 * module Planning (propriétaire canonique, façade Absence = HTTP thin).
 *
 * Couvre : demande → approbation → refus → annulation, recalcul des jours
 * ouvrés à la modification (#2671/#4933), exclusion des conflits de dates.
 *
 * Référence de calcul « jours ouvrés » (golden) : la table public_holidays
 * est vide en tests (peuplée par seeder, pas par migration) → le fallback
 * « week-ends exclus seuls » s'applique (PublicHolidayService::workingDaysBetween).
 * Calendrier 2026 : 06/04 = lundi, 10/04 = vendredi, 13/04 = lundi.
 */
class AbsenceLifecycleTest extends TestCase
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
        $this->manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->type = AbsenceType::factory()->create([
            'company_id' => $this->company->id,
            'deducts_leave' => true,
            'is_paid' => true,
        ]);
        $this->service = app(AbsenceService::class);
    }

    /** Solde initial posé par le chemin canonique (snapshot leave_balances). */
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

    private function snapshot(int $year = 2026): LeaveBalance
    {
        return LeaveBalance::query()
            ->where('company_id', $this->company->id)
            ->where('employee_id', $this->employee->id)
            ->where('absence_type_id', $this->type->id)
            ->where('year', $year)
            ->firstOrFail();
    }

    public function test_create_persists_pending_absence_with_working_days_count(): void
    {
        $this->creditBalance(25.0);

        // Golden : vendredi 10/04 → lundi 13/04/2026 = 2 jours ouvrés
        // (samedi 11 + dimanche 12 exclus), et non 4 jours calendaires (#2671).
        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-13',
            'reason' => 'Week-end prolongé familial',
        ]);

        $this->assertSame('pending', $absence->status);
        $this->assertSame(2.0, (float) $absence->days_count);
        $this->assertSame($this->company->id, $absence->company_id);
        $this->assertSame($this->employee->id, $absence->employee_id);
    }

    public function test_create_dispatches_absence_requested_event(): void
    {
        $this->creditBalance(25.0);
        Event::fake([AbsenceRequested::class]);

        $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);

        Event::assertDispatched(AbsenceRequested::class, fn (AbsenceRequested $e): bool => $e->absence->employee_id === $this->employee->id);
    }

    public function test_approve_sets_status_approver_and_dispatches_event(): void
    {
        $this->creditBalance(25.0);
        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);

        Event::fake([AbsenceApproved::class]);

        $approved = $this->service->approve($absence, $this->manager);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($this->manager->id, $approved->approved_by);
        Event::assertDispatched(AbsenceApproved::class);
    }

    public function test_reject_pending_stores_reason_and_dispatches_event(): void
    {
        $this->creditBalance(25.0);
        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);

        Event::fake([AbsenceRejected::class]);

        $rejected = $this->service->reject($absence, 'Période de clôture — effectif réduit');

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Période de clôture — effectif réduit', $rejected->rejected_reason);
        Event::assertDispatched(AbsenceRejected::class);
    }

    /**
     * Golden chain (calcul documenté à la main, convention constitution) :
     * solde initial 25 j → demande 3 j (lun 06 → mer 08/04/2026)
     *   create   : pending 0→3, used 0,  disponible 25−0−3 = 22
     *   approve  : pending 3→0, used 0→3, disponible 25−3−0 = 22
     *   reject   : used 3→0 (restauration #2666), disponible 25−0−0 = 25
     */
    public function test_full_lifecycle_balance_chain_create_approve_reject(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);
        $snap = $this->snapshot();
        $this->assertSame(3.0, (float) $snap->pending);
        $this->assertSame(0.0, (float) $snap->used);
        $this->assertSame(22.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));

        $this->service->approve($absence, $this->manager);
        $snap = $this->snapshot();
        $this->assertSame(0.0, (float) $snap->pending);
        $this->assertSame(3.0, (float) $snap->used);
        $this->assertSame(22.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));

        $absence->refresh();
        $this->service->reject($absence, 'Annulation exceptionnelle');
        $snap = $this->snapshot();
        $this->assertSame(0.0, (float) $snap->pending);
        $this->assertSame(0.0, (float) $snap->used);
        $this->assertSame(25.0, $this->service->currentAvailableBalance($this->employee, (int) $this->type->id, 2026));
    }

    public function test_cancel_then_recreate_same_period_is_allowed(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);

        Sanctum::actingAs($this->employee);
        $this->deleteJson("/api/v1/absences/{$absence->id}")->assertOk();
        $absence->refresh();
        $this->assertSame('cancelled', $absence->status);

        // Une absence annulée est exclue du contrôle de chevauchement
        // (whereNotIn cancelled/rejected) : la même période est redemandable.
        $recreated = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);

        $this->assertSame('pending', $recreated->status);
    }

    public function test_update_pending_absence_recalculates_working_days(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-10', // vendredi
            'end_date' => '2026-04-13',   // lundi → 2 jours ouvrés
        ]);
        $this->assertSame(2.0, (float) $absence->days_count);

        // Golden : déplacé à mer 08/04 → ven 10/04 = 3 jours ouvrés (#4933 :
        // recalcul sur les jours ouvrés du pays, même règle que create).
        $updated = $this->service->update($absence, [
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-10',
        ]);

        $this->assertSame(3.0, (float) $updated->days_count);
        $this->assertSame('2026-04-08', $updated->start_date->toDateString());
    }

    public function test_update_via_api_rejects_non_pending_with_422(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);
        $this->service->approve($absence, $this->manager);

        // #4933 : approuvée = état terminal pour la modification.
        Sanctum::actingAs($this->employee);
        $this->putJson("/api/v1/absences/{$absence->id}", [
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-14',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'ABSENCE_NOT_EDITABLE');
    }

    public function test_overlapping_request_throws_date_conflict(): void
    {
        $this->creditBalance(25.0);

        $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-10',
        ]);

        $this->expectException(AbsenceDateConflictException::class);

        // 08/04 ∈ [06/04, 10/04] → chevauchement (start ≤ end existant ET
        // end ≥ start existant).
        $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-14',
        ]);
    }

    public function test_approve_non_pending_throws_absence_not_pending(): void
    {
        $this->creditBalance(25.0);

        $absence = $this->service->create($this->employee, [
            'absence_type_id' => $this->type->id,
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-08',
        ]);
        $this->service->reject($absence, 'Motif');

        $this->expectException(AbsenceNotPendingException::class);

        $absence->refresh();
        $this->service->approve($absence, $this->manager);
    }
}
