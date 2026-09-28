<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-006B (#8140) — commande `audit:scheduler-duplicates`.
 *
 * Le double scheduler (2026-05-12 → correctif #8139) a fait tourner
 * `leave:accrue` en daily + monthly le 1er du mois (commande non idempotente
 * → acquisitions en double), `billing:generate-invoices` à 02:00 + 03:00
 * (protégé par construction) et deux expireurs Travel concurrents
 * (événements `travel.booking.expired.v1` en double sous deux clés
 * d'idempotence différentes).
 *
 * Le test vérifie : détection dry-run sans écriture, correction rejouable
 * avec backup + trace, refus sûr sur solde insuffisant, verdict « aucun
 * dégât » billing, annulation des seuls doublons Travel encore pending.
 */
class AuditSchedulerDuplicatesTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    private AbsenceType $absenceType;

    private LeavePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/audit-bos006b'));

        $this->company = Company::query()->create([
            'name' => 'Audit BOS006B',
            'slug' => 'audit-bos006b',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Test City',
            'email' => 'audit-bos006b@test.com',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
        ]);

        $employee = new Employee([
            'matricule' => 'BOS006B-1',
            'first_name' => 'Audit',
            'last_name' => 'Duplicate',
            'email' => 'audit-duplicate@bos006b.test',
        ]);
        $employee->forceFill(['password_hash' => Hash::make('password')])->save();
        $employee->forceFill([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ])->save();
        $this->employee = $employee;

        $this->absenceType = AbsenceType::query()->create([
            'company_id' => $this->company->id,
            'code' => 'ANNUAL',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'deducts_leave' => true,
        ]);

        $this->policy = LeavePolicy::query()->create([
            'company_id' => $this->company->id,
            'absence_type_id' => $this->absenceType->id,
            'name' => 'Congés annuels',
            'accrual_type' => 'monthly',
            'accrual_amount' => 2.5,
            'requires_approval' => true,
            'active' => true,
        ]);
    }

    // ── LEAVE ────────────────────────────────────────────────────────────

    public function test_leave_dry_run_detects_without_writing(): void
    {
        $this->seedDuplicatedAccrual(balance: 5.0);

        $exit = Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave']);

        $this->assertSame(0, $exit);
        // Dry-run : aucune écriture.
        $this->assertSame(2, LeaveAccrual::query()->count());
        $this->assertSame(5.0, (float) LeaveBalance::query()->firstOrFail()->balance);

        $report = $this->readSingleReport();
        $this->assertSame('dry-run', $report['mode']);
        $this->assertSame(1, $report['domains']['leave']['duplicates_groups']);
        $this->assertSame(2.5, (float) $report['domains']['leave']['excess_accrual_days']);
    }

    public function test_leave_execute_corrects_once_with_backup_and_trace(): void
    {
        $ids = $this->seedDuplicatedAccrual(balance: 5.0);

        $exit = Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave', '--execute' => true]);

        $this->assertSame(0, $exit);

        // La première acquisition (id le plus bas) est conservée, le doublon supprimé.
        $this->assertSame([$ids[0]], LeaveAccrual::query()->orderBy('id')->pluck('id')->all());

        // Solde décrémenté de l'excès + trace d'audit du retrait.
        $this->assertSame(2.5, (float) LeaveBalance::query()->firstOrFail()->balance);
        $log = LeaveBalanceLog::query()->firstOrFail();
        $this->assertSame(-2.5, (float) $log->delta);
        $this->assertSame(2.5, (float) $log->balance_after);
        $this->assertStringContainsString('BOS-006B', (string) $log->reason);

        // Backup pré-correction écrit avec la ligne supprimée et le solde avant.
        $backup = $this->readSingleBackup('leave');
        $this->assertCount(1, $backup['data']['accruals_deleted']);
        $this->assertSame($ids[1], (int) $backup['data']['accruals_deleted'][0]['id']);
        $this->assertSame(5.0, (float) $backup['data']['balances_before'][0]['balance']);

        // Rejouable : une seconde exécution ne trouve plus rien.
        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave', '--execute' => true]);
        $this->assertSame(1, LeaveAccrual::query()->count());
        $this->assertSame(2.5, (float) LeaveBalance::query()->firstOrFail()->balance);
        $this->assertSame(1, LeaveBalanceLog::query()->count());
    }

    public function test_leave_execute_flags_low_balance_for_manual_review(): void
    {
        // Solde déjà consommé (1.0 j) < retrait (2.5 j) : jamais de solde
        // négatif forcé — le cas part en revue manuelle.
        $this->seedDuplicatedAccrual(balance: 1.0);

        $exit = Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave', '--execute' => true]);

        $this->assertSame(0, $exit);
        $this->assertSame(2, LeaveAccrual::query()->count());
        $this->assertSame(1.0, (float) LeaveBalance::query()->firstOrFail()->balance);
        $this->assertSame(0, LeaveBalanceLog::query()->count());

        $report = $this->readSingleReport();
        $this->assertSame(1, $report['domains']['leave']['flagged_manual_review']);
        $this->assertSame(0, $report['domains']['leave']['corrected']);
    }

    public function test_leave_clean_history_reports_no_damage(): void
    {
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'balance' => 2.5,
            'used' => 0,
            'pending' => 0,
            'year' => 2026,
        ]);
        $this->makeAccrual('2026-06-01', 2.5);

        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave']);

        $report = $this->readSingleReport();
        $this->assertSame(0, $report['domains']['leave']['duplicates_groups']);
        $this->assertSame('aucun dégât', $report['domains']['leave']['verdict']);
    }

    // ── BILLING ──────────────────────────────────────────────────────────

    public function test_billing_reports_no_damage_by_construction(): void
    {
        $this->makeInvoice('LEO-2026-0001', '2026-06');

        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'billing']);

        $report = $this->readSingleReport();
        $this->assertSame(0, $report['domains']['billing']['duplicates_groups']);
        $this->assertSame('aucun dégât', $report['domains']['billing']['verdict']);
    }

    public function test_billing_flags_impossible_duplicates_for_manual_review(): void
    {
        // En prod, l'unicité (company_id, subscription_id, period) interdit
        // ce cas ; subscription_id NULL échappe à la contrainte (comportement
        // PostgreSQL) — la commande doit le détecter et exiger une revue
        // manuelle, jamais supprimer un document financier.
        $this->makeInvoice('LEO-2026-0002', '2026-06');
        $this->makeInvoice('LEO-2026-0003', '2026-06');

        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'billing', '--execute' => true]);

        $report = $this->readSingleReport();
        $this->assertSame(1, $report['domains']['billing']['duplicates_groups']);
        $this->assertSame('anomalie — revue manuelle requise', $report['domains']['billing']['verdict']);
        // Jamais de correction automatique sur la facturation.
        $this->assertSame(2, Invoice::query()->count());
    }

    // ── TRAVEL ───────────────────────────────────────────────────────────

    public function test_travel_cancels_only_pending_duplicates(): void
    {
        $canonical = $this->makeExpiredEvent('BK-8140', 'booking-expired-42', TravelOutboxEvent::STATUS_PUBLISHED);
        $pendingDuplicate = $this->makeExpiredEvent('BK-8140', 'hash-legacy-1', TravelOutboxEvent::STATUS_PENDING);
        $publishedDuplicate = $this->makeExpiredEvent('BK-8140', 'hash-legacy-2', TravelOutboxEvent::STATUS_PUBLISHED);

        $exit = Artisan::call('audit:scheduler-duplicates', ['--domain' => 'travel', '--execute' => true]);

        $this->assertSame(0, $exit);

        // Le doublon pending est annulé (failed) avec un motif explicite…
        $pendingDuplicate->refresh();
        $this->assertSame(TravelOutboxEvent::STATUS_FAILED, $pendingDuplicate->status);
        $this->assertStringContainsString('BOS-006B', (string) $pendingDuplicate->last_error);

        // … le canonique et le doublon déjà publié ne sont JAMAIS réécrits.
        $this->assertSame(TravelOutboxEvent::STATUS_PUBLISHED, $canonical->refresh()->status);
        $this->assertNull($publishedDuplicate->refresh()->last_error);
        $this->assertSame(TravelOutboxEvent::STATUS_PUBLISHED, $publishedDuplicate->status);

        $report = $this->readSingleReport();
        $this->assertSame(1, $report['domains']['travel']['duplicates_groups']);
        $this->assertSame(1, $report['domains']['travel']['pending_duplicates_cancelled']);
        $this->assertSame(1, $report['domains']['travel']['published_duplicates_observed']);
    }

    public function test_travel_dry_run_keeps_everything_pending(): void
    {
        $this->makeExpiredEvent('BK-8140', 'booking-expired-42', TravelOutboxEvent::STATUS_PUBLISHED);
        $pendingDuplicate = $this->makeExpiredEvent('BK-8140', 'hash-legacy-1', TravelOutboxEvent::STATUS_PENDING);

        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'travel']);

        $this->assertSame(TravelOutboxEvent::STATUS_PENDING, $pendingDuplicate->refresh()->status);
    }

    // ── Généralités ──────────────────────────────────────────────────────

    public function test_invalid_window_is_rejected(): void
    {
        $this->assertSame(1, Artisan::call('audit:scheduler-duplicates', ['--from' => '26/05/2026']));
        $this->assertSame(1, Artisan::call('audit:scheduler-duplicates', ['--from' => '2026-06-01', '--to' => '2026-05-01']));
        $this->assertSame(1, Artisan::call('audit:scheduler-duplicates', ['--domain' => 'payroll']));
    }

    public function test_default_window_starts_at_double_scheduler_introduction(): void
    {
        // Hors fenêtre (avant le 2026-05-12) : un doublon plus ancien ne doit
        // PAS être rapporté par défaut…
        $this->makeAccrual('2026-05-01', 2.5);
        $this->makeAccrual('2026-05-01', 2.5);

        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave']);

        $report = $this->readSingleReport();
        $this->assertSame('2026-05-12', $report['window']['from']);
        $this->assertSame(0, $report['domains']['leave']['duplicates_groups']);

        // … mais il l'est avec une fenêtre explicite.
        Artisan::call('audit:scheduler-duplicates', ['--domain' => 'leave', '--from' => '2026-04-01']);

        $report = $this->readSingleReport();
        $this->assertSame(1, $report['domains']['leave']['duplicates_groups']);
    }

    // ── Fixtures & helpers ───────────────────────────────────────────────

    /** @return array{0: int, 1: int} ids des deux acquisitions (canonique, doublon) */
    private function seedDuplicatedAccrual(float $balance): array
    {
        LeaveBalance::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'balance' => $balance,
            'used' => 0,
            'pending' => 0,
            'year' => 2026,
        ]);

        // Rejoue le 1er du mois : run de 00:00 (daily fantôme) + run de
        // 03:00 (monthly) → deux acquisitions identiques.
        $first = $this->makeAccrual('2026-06-01', 2.5);
        $second = $this->makeAccrual('2026-06-01', 2.5);

        return [$first->id, $second->id];
    }

    private function makeAccrual(string $effectiveDate, float $amount): LeaveAccrual
    {
        return LeaveAccrual::query()->create([
            'company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'leave_policy_id' => $this->policy->id,
            'amount' => $amount,
            'type' => 'accrual',
            'description' => 'Monthly accrual — June 2026',
            'effective_date' => $effectiveDate,
        ]);
    }

    private function makeInvoice(string $number, string $period): Invoice
    {
        return Invoice::query()->create([
            'company_id' => $this->company->id,
            'subscription_id' => null,
            'number' => $number,
            'period' => $period,
            'amount' => 99.00,
            'tax_amount' => 0,
            'total' => 99.00,
            'currency' => 'EUR',
            'status' => 'sent',
            'due_date' => '2026-07-01',
        ]);
    }

    private function makeExpiredEvent(string $reference, string $idempotencyKey, string $status): TravelOutboxEvent
    {
        return TravelOutboxEvent::query()->create([
            'company_id' => $this->company->id,
            'event_type' => 'travel.booking.expired.v1',
            'payload_redacted' => [
                'booking_reference' => $reference,
                'trip_id' => 42,
                'reason' => 'pending_expired',
                'expired_at' => now()->toIso8601String(),
            ],
            'status' => $status,
            'attempts' => 0,
            'available_at' => now(),
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /** @return array<string, mixed> */
    private function readSingleReport(): array
    {
        $dir = storage_path('app/audit-bos006b');
        $files = glob($dir.'/report-*.json') ?: [];
        sort($files);
        $this->assertNotEmpty($files, 'Aucun rapport JSON écrit par la commande.');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents((string) end($files)), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @return array<string, mixed> */
    private function readSingleBackup(string $domain): array
    {
        $dir = storage_path('app/audit-bos006b');
        $files = glob($dir.'/'.$domain.'-backup-*.json') ?: [];
        $this->assertNotEmpty($files, "Aucun backup {$domain} écrit avant correction.");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
