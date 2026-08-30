<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\Models\AccountingReportingSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * BC-22-D10 (issue #6243) — snapshots horodatés des read models de reporting.
 *
 * Matérialise un read model pour une période : clé unique
 * `(company_id, report, period_from, period_to)`, version incrémentée UNIQUEMENT
 * quand le contenu change (deux recomputes identiques → même version,
 * idempotence), `refreshed_at` = fraîcheur exposée à l'API.
 *
 * Stratégie d'activation : documentée dans
 * `docs/architecture/ANALYTICS_SNAPSHOTS.md` — le snapshot est activé par
 * commande/job quand un endpoint de reporting dépasse son budget p95
 * (`dev-hub/tools/performance-budgets.json`) ; tant qu'il n'est pas activé,
 * le read model reste calculé à la volée (déterministe) et la réponse API
 * expose `snapshot.source = "live"`.
 *
 * Isolation tenant : toutes les requêtes filtrent explicitement `company_id`
 * (withoutGlobalScopes + where, fail-closed #3727) — appelable hors contexte
 * HTTP (jobs tenant-scoped, console).
 */
final class AccountingReportingSnapshotService
{
    public const REPORT_ACCOUNTING_DASHBOARD = 'accounting_dashboard';

    public function __construct(private readonly AccountingDashboardService $dashboard) {}

    /**
     * Recompute idempotent du snapshot d'un read model pour une période.
     */
    public function recompute(string $companyId, string $report, ?string $from = null, ?string $to = null): AccountingReportingSnapshot
    {
        [$from, $to] = $this->resolvePeriod($from, $to);

        $payload = $this->computePayload($companyId, $report, $from, $to);

        return DB::transaction(function () use ($companyId, $report, $from, $to, $payload): AccountingReportingSnapshot {
            $existing = $this->query($companyId, $report, $from, $to)->first();

            if ($existing === null) {
                /** @var AccountingReportingSnapshot $snapshot */
                $snapshot = AccountingReportingSnapshot::query()->create([
                    'company_id' => $companyId,
                    'report' => $report,
                    'period_from' => $from,
                    'period_to' => $to,
                    'version' => 1,
                    'payload' => $payload,
                    'refreshed_at' => now(),
                ]);

                return $snapshot;
            }

            // Idempotence : contenu identique → même version, fraîcheur
            // rafraîchie ; contenu différent → version suivante.
            $sameContent = $existing->payload === $payload;

            $existing->forceFill([
                'payload' => $payload,
                'version' => $sameContent ? $existing->version : $existing->version + 1,
                'refreshed_at' => now(),
            ])->save();

            return $existing->refresh();
        });
    }

    /**
     * Snapshot le plus récent d'un read model pour une période (null si aucun).
     */
    public function latest(string $companyId, string $report, ?string $from = null, ?string $to = null): ?AccountingReportingSnapshot
    {
        [$from, $to] = $this->resolvePeriod($from, $to);

        return $this->query($companyId, $report, $from, $to)->first();
    }

    /**
     * Métadonnées de fraîcheur exposées à l'API (bloc `snapshot`).
     *
     * @return array<string, mixed>
     */
    public function metadata(string $companyId, ?string $from = null, ?string $to = null): array
    {
        $snapshot = $this->latest($companyId, self::REPORT_ACCOUNTING_DASHBOARD, $from, $to);

        if ($snapshot === null) {
            return ['source' => 'live'];
        }

        return [
            'source' => 'snapshot',
            'report' => $snapshot->report,
            'version' => $snapshot->version,
            'refreshed_at' => $snapshot->refreshed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function computePayload(string $companyId, string $report, string $from, string $to): array
    {
        if ($report === self::REPORT_ACCOUNTING_DASHBOARD) {
            return $this->dashboard->summary($companyId, $from, $to);
        }

        throw new \InvalidArgumentException(__('accounting.errors.report_unknown', ['report' => $report]));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Modules\Accounting\Domain\Models\AccountingReportingSnapshot>
     */
    private function query(string $companyId, string $report, string $from, string $to)
    {
        return AccountingReportingSnapshot::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('report', $report)
            ->where('period_from', $from)
            ->where('period_to', $to);
    }

    /**
     * Période par défaut identique au read model (début de mois → aujourd'hui).
     *
     * @return array{0: string, 1: string} [from, to] au format Y-m-d
     */
    private function resolvePeriod(?string $from, ?string $to): array
    {
        $from = $from !== null ? Carbon::parse($from)->toDateString() : Carbon::now()->startOfMonth()->toDateString();
        $to = $to !== null ? Carbon::parse($to)->toDateString() : Carbon::now()->toDateString();

        return [$from, $to];
    }
}
