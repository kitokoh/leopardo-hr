<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Plan 60 jours — issue #5156 : suivi d'usage des pilotes.
 *
 * « Pilote actif » doit être mesuré, pas déclaré (cible : ≥ 2 pilotes
 * actifs / semaine au gate J60). Cette commande agrège par compagnie pilote :
 * logins de la fenêtre, pointages créés, runs de paie, employés actifs et
 * dernière activité — sans table supplémentaire (audit/attendance/payroll
 * existants).
 *
 * Usage :
 *   php artisan pilot:report                          # compagnies marquées pilote, 7 j
 *   php artisan pilot:report --company=acme-dz        # slugs ciblés
 *   php artisan pilot:report --days=30 --json         # fenêtre 30 j, sortie JSON
 *   php artisan pilot:report --all                    # toutes les compagnies
 *
 * Marquage pilote : `metadata.pilot` ou `metadata.is_pilot` = true sur la
 * ligne `public.companies` (registre), ou slugs passés explicitement.
 */
class PilotReportCommand extends Command
{
    protected $signature = 'pilot:report
        {--company=* : Slugs de compagnies à rapporter (répétable)}
        {--days=7 : Fenêtre d\'analyse en jours}
        {--all : Toutes les compagnies (défaut : marquées pilote)}
        {--json : Sortie JSON structurée}';

    protected $description = 'Rapport d\'usage hebdomadaire par compagnie pilote (logins, pointages, paie, employés actifs)';

    public function handle(TenantManager $tenantManager): int
    {
        $days = max(1, (int) $this->option('days'));
        $start = Carbon::now()->subDays($days)->startOfDay();

        $companies = $this->resolveCompanies();

        if ($companies->isEmpty()) {
            $this->warn('Aucune compagnie pilote trouvée (metadata.pilot/is_pilot) — utiliser --company=slug ou --all.');

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        foreach ($companies as $company) {
            try {
                $rows[] = $tenantManager->withinTenant($company, fn (): array => $this->companyMetrics($company, $start, $days));
            } catch (Throwable $e) {
                $this->error("Échec du rapport pour {$company->slug}: {$e->getMessage()}");
                $rows[] = $this->emptyMetrics($company, $days, $e->getMessage());
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode(['generated_at' => Carbon::now()->toIso8601String(), 'window_days' => $days, 'companies' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->renderMarkdown($rows, $days);
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, Company> */
    private function resolveCompanies(): Collection
    {
        $slugs = (array) $this->option('company');

        $query = Company::query();

        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        } elseif (! $this->option('all')) {
            // metadata est JSONB : extraction texte (`->>`) pour une
            // comparaison fiable quel que soit le type stocké.
            $query->where(function ($q): void {
                $q->whereRaw("metadata->>'pilot' = 'true'")
                    ->orWhereRaw("metadata->>'is_pilot' = 'true'");
            });
        }

        return $query->orderBy('name')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function companyMetrics(Company $company, Carbon $start, int $days): array
    {
        $logins = Employee::query()->whereNotNull('last_login_at')->where('last_login_at', '>=', $start)->count();
        $pointages = AttendanceLog::query()->where('date', '>=', $start->toDateString())->count();
        $runsPaie = PayrollRun::query()->where('created_at', '>=', $start)->count();
        $employesActifs = Employee::query()->where('status', 'active')->count();
        $employesActifsFenetre = AttendanceLog::query()->where('date', '>=', $start->toDateString())->distinct()->count('employee_id');

        $derniereActivite = DB::table('attendance_logs')
            ->selectRaw('MAX(created_at) AS d')
            ->union(DB::table('payroll_runs')->selectRaw('MAX(created_at) AS d'))
            ->union(DB::table('audit_logs')->selectRaw('MAX(created_at) AS d'))
            ->value('d');

        $paieStatuses = PayrollRun::query()
            ->where('created_at', '>=', $start)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'slug' => $company->slug,
            'company' => $company->name,
            'country' => $company->country,
            'status' => $company->status,
            'window_days' => $days,
            'logins' => $logins,
            'pointages' => $pointages,
            'runs_paie' => $runsPaie,
            'paie_statuses' => $paieStatuses,
            'employes_actifs' => $employesActifs,
            'employes_actifs_fenetre' => $employesActifsFenetre,
            'derniere_activite' => $derniereActivite !== null ? Carbon::parse((string) $derniereActivite)->toIso8601String() : null,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(Company $company, int $days, string $error): array
    {
        return [
            'slug' => $company->slug,
            'company' => $company->name,
            'country' => $company->country,
            'status' => $company->status,
            'window_days' => $days,
            'logins' => 0,
            'pointages' => 0,
            'runs_paie' => 0,
            'paie_statuses' => [],
            'employes_actifs' => 0,
            'employes_actifs_fenetre' => 0,
            'derniere_activite' => null,
            'error' => $error,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function renderMarkdown(array $rows, int $days): void
    {
        $this->line('');
        $this->info("## Usage pilotes — fenêtre {$days} j (".Carbon::now()->toDateString().')');
        $this->line('');
        $this->line('| Compagnie | Pays | Logins | Pointages | Runs paie | Employés actifs | Actifs (fenêtre) | Dernière activité |');
        $this->line('|---|---|---|---|---|---|---|---|');
        foreach ($rows as $row) {
            $last = $row['derniere_activite'] !== null ? substr((string) $row['derniere_activite'], 0, 10) : '—';
            $err = $row['error'] !== null ? ' ⚠️ '.$row['error'] : '';
            $this->line("| {$row['company']} ({$row['slug']}) | {$row['country']} | {$row['logins']} | {$row['pointages']} | {$row['runs_paie']} | {$row['employes_actifs']} | {$row['employes_actifs_fenetre']} | {$last}{$err} |");
        }
        $this->line('');
        $this->line('Source : logins (employees.last_login_at), pointages (attendance_logs.date), paie (payroll_runs.created_at).');
    }
}
