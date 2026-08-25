<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Infrastructure\Services\AttendancePeriodClosureService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Issue #5267 — clôture d'une période de pointage (verrouille les
 * corrections de pointage sur la période).
 *
 * Usage :
 *   php artisan attendance:close-period --company=<slug> --month=2026-05
 *   php artisan attendance:close-period --company=<slug>            (mois courant)
 *
 * Idempotent : une clôture existante pour la même période est conservée
 * (aucun doublon, aucun audit dupliqué).
 */
class CloseAttendancePeriod extends Command
{
    protected $signature = 'attendance:close-period
        {--company= : Slug de l\'entreprise (obligatoire)}
        {--month= : Mois à clôturer au format YYYY-MM (défaut : mois courant)}';

    protected $description = 'Clôturer une période de pointage — verrouille les corrections de pointage sur la période';

    public function __construct(
        private readonly AttendancePeriodClosureService $closures,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companySlug = (string) $this->option('company');
        if ($companySlug === '') {
            $this->error('Option --company=<slug> obligatoire.');

            return self::FAILURE;
        }

        $month = (string) ($this->option('month') ?? now()->format('Y-m'));
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error("--month invalide (attendu YYYY-MM, reçu « {$month} »).");

            return self::FAILURE;
        }

        $company = Company::query()->where('slug', $companySlug)->first();
        if ($company === null) {
            $this->error("Entreprise « {$companySlug} » introuvable.");

            return self::FAILURE;
        }

        $actor = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            $this->error("Aucun employé actif pour tracer la clôture de « {$companySlug} ».");

            return self::FAILURE;
        }

        $periodStart = Carbon::createFromFormat('Y-m', $month);
        if ($periodStart === null) {
            $this->error("--month invalide (attendu YYYY-MM, reçu « {$month} »).");

            return self::FAILURE;
        }
        $periodStart = $periodStart->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $closure = $this->closures->closePeriod($company->id, $periodStart, $periodEnd, $actor);

        $this->info(sprintf(
            'Période %s → %s clôturée (entreprise %s, clôture #%d).',
            $closure->period_start->toDateString(),
            $closure->period_end->toDateString(),
            $companySlug,
            $closure->id
        ));

        return self::SUCCESS;
    }
}
