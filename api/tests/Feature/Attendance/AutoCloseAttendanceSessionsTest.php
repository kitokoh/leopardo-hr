<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\GeoAttendanceSession;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * ADR-0016 Phases 4-5 (#5355, #5356) — fermeture automatique des sessions GPS
 * via la commande fusionnée `attendance:auto-close --sessions-only`.
 *
 * #4797 — la commande doit itérer TOUS les tenants actifs (multi-schéma).
 * Avant le correctif, le scheduler ne fermait que les sessions du schéma par
 * défaut. Scénarios portés depuis `AutoCloseGeoSessionsCommandTest` (commande
 * dépréciée `smart-attendance:auto-close` supprimée en Phase 5).
 */
class AutoCloseAttendanceSessionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private GeoAttendanceSession $staleSession;

    private GeoAttendanceSession $recentSession;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'status' => 'active',
        ]);
        $this->company = $company;

        /** @var \App\Core\Auth\Domain\Models\Employee $employee */
        $employee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->staleSession = GeoAttendanceSession::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'started_at' => Carbon::now()->subHours(20),
            'status' => GeoAttendanceSession::STATUS_DETECTED,
            'check_in_lat' => 36.75,
            'check_in_lng' => 3.05,
        ]);

        $this->recentSession = GeoAttendanceSession::create([
            'company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'started_at' => Carbon::now()->subHours(2),
            'status' => GeoAttendanceSession::STATUS_DETECTED,
            'check_in_lat' => 36.75,
            'check_in_lng' => 3.05,
        ]);
    }

    public function test_command_closes_stale_sessions_only(): void
    {
        $exit = Artisan::call('attendance:auto-close', [
            '--sessions-only' => true,
            '--hours' => 14,
        ]);

        $this->assertSame(0, $exit);

        $this->staleSession->refresh();
        $this->assertNotNull($this->staleSession->ended_at);
        $this->assertSame(GeoAttendanceSession::STATUS_PENDING_VALIDATION, $this->staleSession->status);

        $this->recentSession->refresh();
        $this->assertNull($this->recentSession->ended_at);
    }

    public function test_command_dry_run_does_not_modify(): void
    {
        $exit = Artisan::call('attendance:auto-close', [
            '--sessions-only' => true,
            '--hours' => 14,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);

        $this->staleSession->refresh();
        $this->assertNull($this->staleSession->ended_at);
        $this->assertSame(GeoAttendanceSession::STATUS_DETECTED, $this->staleSession->status);
    }

    public function test_command_targets_specific_company(): void
    {
        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create([
            'status' => 'active',
        ]);

        /** @var \App\Core\Auth\Domain\Models\Employee $otherEmployee */
        $otherEmployee = \App\Core\Auth\Domain\Models\Employee::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $otherStale = GeoAttendanceSession::create([
            'company_id' => $otherCompany->id,
            'employee_id' => $otherEmployee->id,
            'started_at' => Carbon::now()->subHours(30),
            'status' => GeoAttendanceSession::STATUS_DETECTED,
            'check_in_lat' => 36.75,
            'check_in_lng' => 3.05,
        ]);

        $exit = Artisan::call('attendance:auto-close', [
            '--sessions-only' => true,
            '--hours' => 14,
            '--company' => $this->company->id,
        ]);

        $this->assertSame(0, $exit);

        // Seule la session de la société ciblée est fermée.
        $this->staleSession->refresh();
        $this->assertNotNull($this->staleSession->ended_at);

        $otherStale->refresh();
        $this->assertNull($otherStale->ended_at);
    }
}
