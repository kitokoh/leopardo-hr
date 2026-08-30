<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ManagerWeeklyDigestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Digest hebdomadaire manager — envoyé chaque lundi matin (#5695).
 *
 * Pour chaque entreprise active, calcule les KPIs RH de la semaine écoulée
 * et envoie un email à tous les managers (role principal/rh) qui ont un email.
 *
 * Métriques :
 * - Absences en attente de validation
 * - Taux de présence moyen (7 derniers jours)
 * - Nouveaux employés créés cette semaine
 * - Contrats arrivant à échéance dans 30 jours
 */
class SendManagerWeeklyDigestCommand extends Command
{
    protected $signature = 'manager:weekly-digest
        {--dry-run : Affiche les destinataires sans envoyer}
        {--company= : Limiter à une company_id spécifique (tests)}';

    protected $description = 'Envoie le digest RH hebdomadaire aux managers (#5695)';

    public function handle(): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $onlyCompany = $this->option('company');

        $this->info($dryRun ? '[DRY-RUN] Digest hebdomadaire manager' : 'Digest hebdomadaire manager — envoi en cours...');

        $now       = Carbon::now();
        $weekStart = $now->copy()->subDays(7)->startOfDay();
        $weekEnd   = $now->copy()->startOfDay();
        $expiry    = $now->copy()->addDays(30)->toDateString();

        // Récupère toutes les entreprises actives via le schema public.
        $companiesTable = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $companiesQuery = DB::table($companiesTable)
            ->select(['id', 'name', 'language'])
            ->where('status', '!=', 'suspended');

        if ($onlyCompany) {
            $companiesQuery->where('id', (string) $onlyCompany);
        }

        $companies = $companiesQuery->get();

        $sent   = 0;
        $errors = 0;

        foreach ($companies as $company) {
            $companyId = (string) $company->id;

            // Basculer sur le schema tenant si PostgreSQL.
            if (DB::getDriverName() === 'pgsql') {
                try {
                    DB::statement("SET search_path TO shared_tenants");
                } catch (\Throwable) {
                    // Fallback : schema par défaut.
                }
            }

            try {
                $managers = DB::table('employees')
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->whereNotNull('email')
                    ->whereIn('manager_role', ['principal', 'rh'])
                    ->select(['id', 'first_name', 'last_name', 'email', 'preferred_language'])
                    ->get();

                if ($managers->isEmpty()) {
                    continue;
                }

                // KPIs entreprise ─────────────────────────────────────────
                $pendingAbsences = (int) DB::table('absences')
                    ->where('company_id', $companyId)
                    ->where('status', 'pending')
                    ->count();

                // Taux de présence moyen : logs check_in des 7 derniers jours
                $employeesActive = (int) DB::table('employees')
                    ->where('company_id', $companyId)
                    ->where('status', 'active')
                    ->count();

                $avgAttendancePct = 0;
                if ($employeesActive > 0) {
                    $checkedInDays = DB::table('attendance_logs')
                        ->where('company_id', $companyId)
                        ->whereBetween('check_in', [$weekStart, $weekEnd])
                        ->distinct()
                        ->select(DB::raw('DATE(check_in) as day'), 'employee_id')
                        ->get()
                        ->groupBy('day');

                    if ($checkedInDays->isNotEmpty()) {
                        $total = $checkedInDays->sum(fn ($day) => $day->count());
                        $days  = $checkedInDays->count();
                        $avgAttendancePct = (int) min(100, round(($total / ($employeesActive * $days)) * 100));
                    }
                }

                $newEmployees = (int) DB::table('employees')
                    ->where('company_id', $companyId)
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->count();

                $expiringContracts = DB::getSchemaBuilder()->hasTable('contracts')
                    ? (int) DB::table('contracts')
                        ->where('company_id', $companyId)
                        ->where('status', 'active')
                        ->where('end_date', '<=', $expiry)
                        ->where('end_date', '>=', $now->toDateString())
                        ->count()
                    : 0;

                $locale = (string) ($company->language ?? 'fr');
                $weekLabel = $weekStart->locale($locale)->translatedFormat('d M') . ' – ' . $weekEnd->locale($locale)->translatedFormat('d M Y');

                foreach ($managers as $manager) {
                    $managerLocale = (string) ($manager->preferred_language ?? $locale);
                    $managerName   = trim(($manager->first_name ?? '') . ' ' . ($manager->last_name ?? '')) ?: (string) $manager->email;

                    $data = [
                        'manager_name'        => $managerName,
                        'company_name'        => (string) ($company->name ?? 'Leopardo RH'),
                        'week_label'          => $weekLabel,
                        'pending_absences'    => $pendingAbsences,
                        'avg_attendance_pct'  => $avgAttendancePct,
                        'new_employees'       => $newEmployees,
                        'expiring_contracts'  => $expiringContracts,
                        'app_url'             => rtrim((string) config('app.url', 'https://app.leopardo.io'), '/'),
                    ];

                    if ($dryRun) {
                        $this->line("  → [DRY-RUN] {$manager->email} ({$companyId}) pending={$pendingAbsences} pct={$avgAttendancePct}%");
                        continue;
                    }

                    try {
                        Mail::to((string) $manager->email)
                            ->send(new ManagerWeeklyDigestMail($data, $managerLocale));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('manager:weekly-digest mail error', [
                            'email'      => $manager->email,
                            'company_id' => $companyId,
                            'error'      => $e->getMessage(),
                        ]);
                        $errors++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('manager:weekly-digest company error', [
                    'company_id' => $companyId,
                    'error'      => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->info($dryRun
            ? "[DRY-RUN] terminé — {$companies->count()} entreprise(s) analysée(s)."
            : "Digest envoyé : {$sent} email(s), {$errors} erreur(s).");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
