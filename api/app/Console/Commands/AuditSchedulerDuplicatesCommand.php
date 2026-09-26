<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * BOS-006B (#8140) — Audit rétrospectif des doubles exécutions du scheduler.
 *
 * Entre le 2026-05-12 (coexistence des deux définitions du scheduler :
 * `bootstrap/app.php` + `routes/console.php`) et le déploiement du correctif
 * BOS-006A (#8139), 8 commandes ont tourné en double en production. Cette
 * commande mesure — et corrige sur demande explicite — les dégâts par
 * domaine :
 *
 *  - `leave`   : acquisitions de congés en double (`leave:accrue` daily +
 *    monthly le 1er du mois — commande NON idempotente sur la période) :
 *    groupes `leave_accruals` identiques (mêmes société/employé/politique/
 *    date/libellé). Correction : conservation de la 1re acquisition,
 *    suppression des doublons + décrément du solde sous verrou (avec trace
 *    `leave_balance_logs`), sauvegarde JSON préalable.
 *  - `billing` : factures en double (`billing:generate-invoices` 02:00 +
 *    03:00). Contrôle CONFIRMATOIRE : l'avancement de `current_period_end`
 *    dans la même transaction rendait le second run sans objet, et
 *    l'unicité (company_id, subscription_id, period) interdit tout doublon
 *    depuis le 2026-08-31 (#6549). Aucune correction automatique : tout
 *    groupe trouvé est remonté pour revue manuelle.
 *  - `travel`  : événements `travel.booking.expired.v1` publiés deux fois
 *    pour la même réservation (payloads différents entre l'expireur legacy
 *    et le job canonique → deux clés d'idempotence distinctes). Correction :
 *    les doublons encore `pending` passent en `failed` (jamais dispatchés) ;
 *    les doublons déjà `published` sont comptés (émis en externe), jamais
 *    réécrits.
 *
 * Sécurité : dry-run par défaut — `--execute` est requis pour toute
 * écriture. Aucune PII dans la sortie ni les logs (identifiants techniques
 * uniquement). Rapport JSON horodaté écrit dans
 * `storage/app/audit-bos006b/` à chaque passe.
 */
class AuditSchedulerDuplicatesCommand extends Command
{
    private const JUSTIFICATION = 'audit rétrospectif doubles exécutions scheduler BOS-006B (#8140)';

    /** Introduction du double scheduler (PR qui a ajouté la 2e définition). */
    private const DEFAULT_FROM = '2026-05-12';

    protected $signature = 'audit:scheduler-duplicates
        {--domain=all : Domaine à auditer (leave, billing, travel ou all)}
        {--from= : Début de la fenêtre d\'audit (YYYY-MM-DD, défaut '.self::DEFAULT_FROM.' — introduction du double scheduler)}
        {--to= : Fin de la fenêtre d\'audit (YYYY-MM-DD, défaut : aujourd\'hui)}
        {--execute : Applique les corrections (défaut : dry-run, aucune écriture)}
        {--limit=500 : Nombre max de groupes de doublons traités par domaine}';

    protected $description = 'BOS-006B (#8140) — mesure et corrige (sur --execute) les doublons créés par le double scheduler (congés, facturation, événements Travel)';

    /** @var array<string, mixed> */
    private array $report = [];

    public function handle(): int
    {
        $domain = strtolower((string) $this->option('domain'));
        if (! in_array($domain, ['all', 'leave', 'billing', 'travel'], true)) {
            $this->error(sprintf('Valeur invalide pour --domain="%s" : leave, billing, travel ou all attendu.', $domain));

            return self::FAILURE;
        }

        [$from, $to] = $this->resolveWindow();
        if (! $from instanceof CarbonImmutable || ! $to instanceof CarbonImmutable) {
            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute');
        $limit = max(1, (int) $this->option('limit'));

        $this->info(sprintf(
            'BOS-006B — audit des doublons du double scheduler | fenêtre [%s → %s] | mode : %s',
            $from->toDateString(),
            $to->toDateString(),
            $execute ? 'EXÉCUTION (corrections activées)' : 'DRY-RUN (aucune écriture)',
        ));

        $this->report = [
            'audit' => 'BOS-006B (#8140) — doubles exécutions du scheduler',
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'mode' => $execute ? 'execute' : 'dry-run',
            'generated_at' => now()->toIso8601String(),
            'domains' => [],
        ];

        if (in_array($domain, ['all', 'leave'], true)) {
            $this->auditLeave($from, $to, $execute, $limit);
        }

        if (in_array($domain, ['all', 'billing'], true)) {
            $this->auditBilling($from, $to);
        }

        if (in_array($domain, ['all', 'travel'], true)) {
            $this->auditTravel($from, $to, $execute, $limit);
        }

        $reportPath = $this->writeReport();

        $this->newLine();
        $this->info("Rapport JSON : {$reportPath}");

        if (! $execute) {
            $this->warn('Dry-run : aucune donnée modifiée. Rejouer avec --execute pour appliquer les corrections sûres (après revue du rapport et backup vérifié).');
        }

        Log::info('bos006b.audit.completed', [
            'window' => $this->report['window'],
            'mode' => $this->report['mode'],
            'domains' => array_keys($this->report['domains']),
        ]);

        return self::SUCCESS;
    }

    // ────────────────────────────────────────────────────────────────────
    // Domaine LEAVE — acquisitions de congés en double
    // ────────────────────────────────────────────────────────────────────

    private function auditLeave(CarbonImmutable $from, CarbonImmutable $to, bool $execute, int $limit): void
    {
        $this->newLine();
        $this->info('── LEAVE : acquisitions mensuelles en double (leave:accrue daily + monthly)');

        /** @var Collection<int, LeaveAccrual> $groups */
        $groups = LeaveAccrual::query()
            ->crossTenantForSystemTask(self::JUSTIFICATION)
            ->select(['company_id', 'employee_id', 'leave_policy_id', 'effective_date', 'description'])
            ->selectRaw('COUNT(*) AS occurrences, MIN(id) AS keep_id, SUM(amount) AS total_amount')
            ->where('type', 'accrual')
            ->where('description', 'like', 'Monthly accrual%')
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('company_id', 'employee_id', 'leave_policy_id', 'effective_date', 'description')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get();

        if ($groups->isEmpty()) {
            $this->info('  Aucun doublon d\'acquisition détecté sur la fenêtre.');
            $this->report['domains']['leave'] = ['duplicates_groups' => 0, 'verdict' => 'aucun dégât'];

            return;
        }

        $rows = [];
        $totalExcess = 0.0;
        $corrected = 0;
        $flagged = 0;
        /** @var list<array<string, mixed>> $backupAccruals */
        $backupAccruals = [];
        /** @var list<array<string, mixed>> $backupBalances */
        $backupBalances = [];

        foreach ($groups as $group) {
            $excess = ((int) $group->getAttribute('occurrences')) - 1;
            $keepId = (int) $group->getAttribute('keep_id');

            /** @var Collection<int, LeaveAccrual> $duplicates */
            $duplicates = LeaveAccrual::query()
                ->crossTenantForSystemTask(self::JUSTIFICATION)
                ->where('company_id', $group->getAttribute('company_id'))
                ->where('employee_id', $group->getAttribute('employee_id'))
                ->where('leave_policy_id', $group->getAttribute('leave_policy_id'))
                ->where('effective_date', $group->getAttribute('effective_date'))
                ->where('type', 'accrual')
                ->where('description', $group->getAttribute('description'))
                ->where('id', '!=', $keepId)
                ->orderBy('id')
                ->get();

            $excessAmount = (float) $duplicates->sum('amount');
            $totalExcess += $excessAmount;

            $rows[] = [
                (string) $group->getAttribute('company_id'),
                (int) $group->getAttribute('employee_id'),
                (int) $group->getAttribute('leave_policy_id'),
                (string) $group->getAttribute('effective_date'),
                (int) $group->getAttribute('occurrences'),
                number_format($excessAmount, 2, '.', ' '),
            ];

            if ($execute) {
                foreach ($duplicates as $duplicate) {
                    $backupAccruals[] = $duplicate->toArray();
                    $result = $this->correctLeaveDuplicate($duplicate, $backupBalances);
                    $result === 'corrected' ? $corrected++ : $flagged++;
                }
            }
        }

        $this->table(
            ['company_id', 'employee_id', 'policy_id', 'date', 'occurrences', 'excès (j)'],
            $rows,
        );

        $this->info(sprintf(
            '  Groupes en double : %d | acquisitions excédentaires : %s j%s',
            $groups->count(),
            number_format($totalExcess, 2, '.', ' '),
            $execute ? sprintf(' | corrigées : %d | à revue manuelle : %d', $corrected, $flagged) : '',
        ));

        $this->report['domains']['leave'] = [
            'duplicates_groups' => $groups->count(),
            'excess_accrual_days' => round($totalExcess, 2),
            'corrected' => $corrected,
            'flagged_manual_review' => $flagged,
            'verdict' => $execute && $flagged === 0 ? 'corrigé' : 'doublons détectés',
        ];

        if ($execute && ($corrected > 0 || $flagged > 0)) {
            $this->writeBackup('leave', ['accruals_deleted' => $backupAccruals, 'balances_before' => $backupBalances]);
        }
    }

    /**
     * Annule UNE acquisition excédentaire : décrément du solde sous verrou +
     * trace `leave_balance_logs` + suppression de l'accrual. Refus sûr : si
     * le solde courant ne couvre plus le retrait (congés consommés entre
     * temps), le cas est renvoyé à la revue manuelle — jamais de solde
     * négatif forcé.
     *
     * @param  list<array<string, mixed>>  $backupBalances
     */
    private function correctLeaveDuplicate(LeaveAccrual $duplicate, array &$backupBalances): string
    {
        /** @var LeavePolicy|null $policy */
        $policy = LeavePolicy::query()
            ->crossTenantForSystemTask(self::JUSTIFICATION)
            ->find($duplicate->leave_policy_id);

        if ($policy === null) {
            $this->warn(sprintf('  [revue] politique %d introuvable pour accrual %d — non corrigé.', $duplicate->leave_policy_id, $duplicate->id));

            return 'flagged';
        }

        return DB::transaction(function () use ($duplicate, $policy, &$backupBalances): string {
            /** @var LeaveBalance|null $balance */
            $balance = LeaveBalance::query()
                ->crossTenantForSystemTask(self::JUSTIFICATION)
                ->where('company_id', $duplicate->company_id)
                ->where('employee_id', $duplicate->employee_id)
                ->where('absence_type_id', $policy->absence_type_id)
                ->where('year', (int) $duplicate->effective_date->format('Y'))
                ->lockForUpdate()
                ->first();

            if ($balance === null || $balance->balance < (float) $duplicate->amount) {
                $this->warn(sprintf(
                    '  [revue] accrual %d : solde %s < retrait %s — cas renvoyé à la revue manuelle.',
                    $duplicate->id,
                    $balance === null ? 'absent' : number_format((float) $balance->balance, 2, '.', ''),
                    number_format((float) $duplicate->amount, 2, '.', ''),
                ));

                return 'flagged';
            }

            $backupBalances[] = $balance->toArray();

            $balance->decrement('balance', (float) $duplicate->amount);
            $balance->refresh();

            LeaveBalanceLog::query()->create([
                'company_id' => $duplicate->company_id,
                'employee_id' => $duplicate->employee_id,
                'delta' => -(float) $duplicate->amount,
                'reason' => 'BOS-006B (#8140) : annulation acquisition en double (double scheduler)',
                'reference_id' => $duplicate->id,
                'balance_after' => (float) $balance->balance,
            ]);

            $duplicate->delete();

            return 'corrected';
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Domaine BILLING — contrôle confirmatoire (protection par construction)
    // ────────────────────────────────────────────────────────────────────

    private function auditBilling(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $this->newLine();
        $this->info('── BILLING : factures en double (contrôle confirmatoire)');

        /** @var Collection<int, Invoice> $groups */
        $groups = Invoice::query()
            ->crossTenantForSystemTask(self::JUSTIFICATION)
            ->select(['company_id', 'subscription_id', 'period'])
            ->selectRaw('COUNT(*) AS occurrences')
            ->whereNotNull('period')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('company_id', 'subscription_id', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        // Factures antérieures à la colonne `period` (2026-08-31, #6549) :
        // regroupement par mois de création — contrôle de la protection
        // historique par avancement de `current_period_end`.
        /** @var Collection<int, Invoice> $legacyGroups */
        $legacyGroups = Invoice::query()
            ->crossTenantForSystemTask(self::JUSTIFICATION)
            ->select(['company_id', 'subscription_id'])
            ->selectRaw("to_char(created_at, 'YYYY-MM') AS month_bucket, COUNT(*) AS occurrences")
            ->whereNull('period')
            ->whereNotNull('subscription_id')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy('company_id', 'subscription_id', DB::raw("to_char(created_at, 'YYYY-MM')"))
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $occurrences = $groups->count() + $legacyGroups->count();

        if ($occurrences === 0) {
            $this->info('  Aucune facture en double — protection par construction confirmée (avancement de période transactionnel + unicité company/subscription/period).');
            $this->report['domains']['billing'] = [
                'duplicates_groups' => 0,
                'verdict' => 'aucun dégât',
                'method' => 'unicité (company_id, subscription_id, period) + avancement current_period_end dans la même transaction',
            ];

            return;
        }

        // Ne devrait jamais arriver (contrainte unique) : revue manuelle
        // obligatoire, JAMAIS de suppression automatique de documents
        // financiers.
        foreach ($groups as $group) {
            $this->warn(sprintf(
                '  [revue] %d factures pour company=%s subscription=%s period=%s',
                (int) $group->getAttribute('occurrences'),
                (string) $group->getAttribute('company_id'),
                (int) $group->getAttribute('subscription_id'),
                (string) $group->getAttribute('period'),
            ));
        }

        foreach ($legacyGroups as $group) {
            $this->warn(sprintf(
                '  [revue] %d factures pré-period pour company=%s subscription=%s mois=%s',
                (int) $group->getAttribute('occurrences'),
                (string) $group->getAttribute('company_id'),
                (int) $group->getAttribute('subscription_id'),
                (string) $group->getAttribute('month_bucket'),
            ));
        }

        $this->error('  Doublons de factures détectés malgré la contrainte unique — REVUE MANUELLE REQUISE (aucune correction automatique possible).');

        $this->report['domains']['billing'] = [
            'duplicates_groups' => $occurrences,
            'verdict' => 'anomalie — revue manuelle requise',
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    // Domaine TRAVEL — événements travel.booking.expired.v1 en double
    // ────────────────────────────────────────────────────────────────────

    private function auditTravel(CarbonImmutable $from, CarbonImmutable $to, bool $execute, int $limit): void
    {
        $this->newLine();
        $this->info('── TRAVEL : événements travel.booking.expired.v1 en double par réservation');

        /** @var Collection<int, TravelOutboxEvent> $events */
        $events = TravelOutboxEvent::query()
            ->crossTenantForSystemTask(self::JUSTIFICATION)
            ->where('event_type', 'travel.booking.expired.v1')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('id')
            ->get(['id', 'company_id', 'payload_redacted', 'status', 'idempotency_key', 'created_at']);

        /** @var Collection<string, Collection<int, TravelOutboxEvent>> $byBooking */
        $byBooking = $events->groupBy(
            static fn (TravelOutboxEvent $event): string => sprintf(
                '%s|%s',
                $event->company_id,
                (string) ($event->payload_redacted['booking_reference'] ?? ''),
            ),
        );

        $duplicateGroups = $byBooking
            ->filter(static fn (Collection $group, string $key): bool => ! str_ends_with($key, '|') && $group->count() > 1)
            ->take($limit);

        if ($duplicateGroups->isEmpty()) {
            $this->info('  Aucun événement d\'expiration en double détecté sur la fenêtre.');
            $this->report['domains']['travel'] = ['duplicates_groups' => 0, 'verdict' => 'aucun dégât'];

            return;
        }

        $pendingCancelled = 0;
        $alreadyPublished = 0;
        /** @var list<array<string, mixed>> $backupEvents */
        $backupEvents = [];
        $rows = [];

        foreach ($duplicateGroups as $group) {
            /** @var Collection<int, TravelOutboxEvent> $group */
            $canonical = $this->canonicalTravelEvent($group);
            $duplicates = $group->reject(
                static fn (TravelOutboxEvent $event): bool => $event->id === $canonical->id,
            );

            $statuses = $duplicates
                ->map(static fn (TravelOutboxEvent $event): string => sprintf('#%d:%s', $event->id, $event->status))
                ->implode(', ');

            $reference = (string) ($canonical->payload_redacted['booking_reference'] ?? '?');
            $rows[] = [(string) $canonical->company_id, $reference, $canonical->id, $statuses];

            foreach ($duplicates as $duplicate) {
                if ($duplicate->status === TravelOutboxEvent::STATUS_PUBLISHED) {
                    // Événement déjà émis vers l'extérieur : le dégât est
                    // constaté, jamais réécrit — remonté au rapport.
                    $alreadyPublished++;

                    continue;
                }

                if ($execute && $duplicate->status === TravelOutboxEvent::STATUS_PENDING) {
                    $backupEvents[] = $duplicate->toArray();
                    $duplicate->forceFill([
                        'status' => TravelOutboxEvent::STATUS_FAILED,
                        'last_error' => 'BOS-006B (#8140) : doublon de travel.booking.expired.v1 annulé avant dispatch (canonique #'.$canonical->id.')',
                    ])->save();
                    $pendingCancelled++;
                }
            }
        }

        $this->table(['company_id', 'booking_reference', 'canonique (event id)', 'doublons (id:statut)'], $rows);

        $this->info(sprintf(
            '  Groupes en double : %d | doublons pending annulés : %d | doublons déjà publiés (constat) : %d%s',
            $duplicateGroups->count(),
            $pendingCancelled,
            $alreadyPublished,
            $execute ? '' : ' | dry-run : aucune annulation appliquée',
        ));

        $this->report['domains']['travel'] = [
            'duplicates_groups' => $duplicateGroups->count(),
            'pending_duplicates_cancelled' => $pendingCancelled,
            'published_duplicates_observed' => $alreadyPublished,
            'verdict' => $execute ? 'corrigé (pending) / constat (published)' : 'doublons détectés',
        ];

        if ($execute && $pendingCancelled > 0) {
            $this->writeBackup('travel', ['events_marked_failed' => $backupEvents]);
        }
    }

    /**
     * Le canonique d'un groupe : l'événement portant la clé d'idempotence
     * métier du job canonique (`booking-expired-{id}`) s'il existe, sinon le
     * plus ancien (premier créé).
     *
     * @param  Collection<int, TravelOutboxEvent>  $group
     */
    private function canonicalTravelEvent(Collection $group): TravelOutboxEvent
    {
        $keyed = $group->first(
            static fn (TravelOutboxEvent $event): bool => is_string($event->idempotency_key)
                && str_starts_with($event->idempotency_key, 'booking-expired-'),
        );

        return $keyed instanceof TravelOutboxEvent ? $keyed : $group->first();
    }

    // ────────────────────────────────────────────────────────────────────
    // Fenêtre, rapport, sauvegarde
    // ────────────────────────────────────────────────────────────────────

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function resolveWindow(): array
    {
        $fromRaw = (string) ($this->option('from') ?: self::DEFAULT_FROM);
        $toRaw = (string) ($this->option('to') ?: now()->toDateString());

        $from = CarbonImmutable::createFromFormat('Y-m-d', $fromRaw);
        $to = CarbonImmutable::createFromFormat('Y-m-d', $toRaw);

        if ($from === false || $from->format('Y-m-d') !== $fromRaw) {
            $this->error(sprintf('Valeur invalide pour --from="%s" : format YYYY-MM-DD attendu.', $fromRaw));

            return [null, null];
        }

        if ($to === false || $to->format('Y-m-d') !== $toRaw) {
            $this->error(sprintf('Valeur invalide pour --to="%s" : format YYYY-MM-DD attendu.', $toRaw));

            return [null, null];
        }

        if ($from->gt($to)) {
            $this->error('--from doit être antérieur ou égal à --to.');

            return [null, null];
        }

        return [$from, $to];
    }

    private function writeReport(): string
    {
        $dir = storage_path('app/audit-bos006b');
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/report-%s.json', $dir, now()->format('Ymd_His'));
        File::put($path, (string) json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }

    /** @param  array<string, mixed>  $payload */
    private function writeBackup(string $domain, array $payload): void
    {
        $dir = storage_path('app/audit-bos006b');
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/%s-backup-%s.json', $dir, $domain, now()->format('Ymd_His'));
        File::put($path, (string) json_encode([
            'domain' => $domain,
            'taken_at' => now()->toIso8601String(),
            'note' => 'Sauvegarde préalable aux corrections BOS-006B (#8140) — restauration manuelle documentée dans docs/ops/SCHEDULER_DUPLICATES_AUDIT.md',
            'data' => $payload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("  Sauvegarde pré-correction : {$path}");
    }
}
