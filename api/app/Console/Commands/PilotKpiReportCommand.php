<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Plan 60 jours — issue #5158 : agrégation des KPI mesurables en base pour
 * le gate de décision J60 (A/B/C).
 *
 * Ce commande couvre les KPI calculables côté API :
 *   KPI-1 conversion signup → dashboard (≥ 30 %)
 *   KPI-2 trial provisioning (< 2 min, cible < 30 s)
 *   KPI-5 pilotes actifs (≥ 2 / semaine) — réutilise `pilot:report`
 *
 * Les KPI externes (CI verte, coverage Payroll, MRR, issues, ratio fix/feat,
 * coût agents) sont agrégés par `dev-hub/tools/kpi-gate.sh` qui produit le
 * snapshot daté `docs/pilotes/KPI_GATE_<date>.md` (méthode documentée dans
 * `docs/ops/MESURE_KPI.md`).
 *
 * Usage : php artisan pilot:kpi-report [--days=30] [--json]
 */
class PilotKpiReportCommand extends Command
{
    protected $signature = 'pilot:kpi-report {--days=30 : Fenêtre d\'analyse en jours} {--json : Sortie JSON structurée}';

    protected $description = 'Agrège les KPI du gate J60 mesurables en base (conversion, provisioning, pilotes actifs)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $start = Carbon::now()->subDays($days);

        // ── KPI-1 : conversion signup → dashboard (≥ 30 %) ───────────────
        $signups = DB::table('company_requests')->where('created_at', '>=', $start)->count();
        $converted = DB::table('company_requests')
            ->where('created_at', '>=', $start)
            ->where('status', 'approved')
            ->count();
        $conversionRate = $signups > 0 ? round(($converted / $signups) * 100, 1) : null;

        // ── KPI-2 : trial provisioning (< 2 min, cible < 30 s) ────────────
        $provisionedRows = DB::table('trial_provisionings')
            ->whereNotNull('provisioned_at')
            ->where('created_at', '>=', $start)
            ->pluck('provisioned_at', 'created_at');

        $durations = [];
        foreach ($provisionedRows as $created => $provisioned) {
            try {
                $durations[] = (int) Carbon::parse((string) $created)->diffInSeconds(Carbon::parse((string) $provisioned), false);
            } catch (\Throwable) {
                // ligne mal formée → ignorée (ne fausse pas le KPI)
            }
        }
        $durations = array_values(array_filter($durations, static fn (int $d): bool => $d >= 0));
        $countProvisioned = count($durations);

        $provisioning = [
            'provisioned_count' => $countProvisioned,
            'avg_seconds' => $countProvisioned > 0 ? (int) round(array_sum($durations) / $countProvisioned) : null,
            'p50_seconds' => $countProvisioned > 0 ? $this->percentile($durations, 50) : null,
            'p95_seconds' => $countProvisioned > 0 ? $this->percentile($durations, 95) : null,
            'share_under_120s' => $countProvisioned > 0 ? round((count(array_filter($durations, static fn (int $d): bool => $d < 120)) / $countProvisioned) * 100, 1) : null,
        ];

        // ── KPI-5 : pilotes actifs (≥ 2 / semaine) — réutilise pilot:report
        Artisan::call('pilot:report', ['--json' => true, '--days' => '7']);
        $pilotReportRaw = json_decode(Artisan::output(), true);
        /** @var list<array<string, mixed>> $pilotCompanies */
        $pilotCompanies = is_array($pilotReportRaw) && isset($pilotReportRaw['companies']) && is_array($pilotReportRaw['companies'])
            ? array_values($pilotReportRaw['companies'])
            : [];
        $pilotesActifs = count(array_filter(
            $pilotCompanies,
            static fn (array $c): bool => (int) ($c['pointages'] ?? 0) > 0
                || (int) ($c['runs_paie'] ?? 0) > 0
                || (int) ($c['logins'] ?? 0) > 0
        ));

        $report = [
            'generated_at' => Carbon::now()->toIso8601String(),
            'window_days' => $days,
            'kpi_1_conversion_signup_dashboard' => [
                'signups' => $signups,
                'converted' => $converted,
                'rate_percent' => $conversionRate,
                'target_percent' => 30,
            ],
            'kpi_2_trial_provisioning' => $provisioning,
            'kpi_5_pilotes_actifs' => [
                'active' => $pilotesActifs,
                'total_pilotes_suivis' => count($pilotCompanies),
                'target' => 2,
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("KPI-1 conversion signup→dashboard ({$days} j) : {$converted}/{$signups} = ".($conversionRate ?? 'n/a').' % (cible ≥ 30 %)');
            $this->info('KPI-2 provisioning : '.($provisioning['p50_seconds'] ?? 'n/a').' s (p50), '.($provisioning['p95_seconds'] ?? 'n/a').' s (p95), '.($provisioning['share_under_120s'] ?? 'n/a').' % < 2 min (cible < 30 s)');
            $this->info("KPI-5 pilotes actifs (7 j) : {$pilotesActifs} (cible ≥ 2)");
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile(array $values, int $p): int
    {
        sort($values);
        $index = (int) ceil(($p / 100) * count($values)) - 1;

        return $values[max(0, min(count($values) - 1, $index))];
    }
}
