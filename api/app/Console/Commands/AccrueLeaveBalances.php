<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Modules\Planning\Infrastructure\Services\LegalLeaveRulesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccrueLeaveBalances extends Command
{
    protected $signature = 'leave:accrue {--force : Run even if not 1st of month}';

    protected $description = 'Accrue monthly leave balances based on active leave policies';

    public function __construct(
        private readonly LegalLeaveRulesService $legalLeaveRules,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = now();

        if ($today->day !== 1 && ! $this->option('force')) {
            $this->info('Skipped — accrual runs on 1st of month. Use --force to override.');

            return self::SUCCESS;
        }

        $year = (int) $today->format('Y');
        $policies = LeavePolicy::where('active', true)
            ->where('accrual_type', 'monthly')
            ->get();

        $count = 0;

        foreach ($policies as $policy) {
            // Issue #5289 — plancher légal de congés du pays de l'entreprise.
            // Résolu UNE fois par politique (pas par employé) : l'acquisition
            // effective ne descend jamais sous le minimum légal mensuel
            // (ex. 2,5 j/mois pour la DZ — Loi 90-11 art. 14).
            $company = Company::query()->find($policy->company_id);
            $legalFloor = $company !== null
                ? $this->legalLeaveRules->monthlyFloorForPolicy($policy, $company)
                : null;
            $accrualAmount = $legalFloor !== null && $policy->accrual_amount < $legalFloor
                ? $legalFloor
                : $policy->accrual_amount;
            $appliedFloor = $accrualAmount > $policy->accrual_amount;

            if ($appliedFloor) {
                $countryCode = strtoupper((string) ($company->country ?? ''));
                Log::info('planning.legal_leave.floor_applied', [
                    'company_id' => $policy->company_id,
                    'policy_id' => $policy->id,
                    'country' => $countryCode,
                    'configured_monthly' => $policy->accrual_amount,
                    'legal_monthly' => $legalFloor,
                ]);
                $this->info(sprintf(
                    'Legal leave floor applied — company %s, policy %d (%s): %.2f → %.2f j/mois',
                    $policy->company_id,
                    $policy->id,
                    $countryCode,
                    $policy->accrual_amount,
                    $legalFloor
                ));
            }

            // Libellé pays capturé une fois (les closures chunk ne voient pas
            // $company en dehors de leur portée).
            $countryLabel = $appliedFloor && $company !== null
                ? strtoupper((string) $company->country)
                : '';

            // Audit #1703 : chunkById au lieu de charger tous les employés en
            // mémoire — évite des milliers de requêtes et un pic mémoire sur
            // les gros tenants (le 1er du mois).
            Employee::where('company_id', $policy->company_id)
                ->chunkById(500, function ($employees) use ($policy, $year, $today, $accrualAmount, $appliedFloor, $legalFloor, $countryLabel, &$count): void {
                    // Audit #1703 : UNE transaction par chunk (au lieu d'une
                    // par employé) — les transactions imbriquées deviennent
                    // des savepoints (isolation par employé conservée, mais
                    // plus de milliers de BEGIN/COMMIT par tenant).
                    DB::transaction(function () use ($employees, $policy, $year, $today, $accrualAmount, $appliedFloor, $legalFloor, $countryLabel, &$count): void {
                        foreach ($employees as $employee) {
                            try {
                                DB::transaction(function () use ($policy, $employee, $year, $today, $accrualAmount, $appliedFloor, $legalFloor, $countryLabel, &$count): void {
                                    $balance = LeaveBalance::firstOrCreate(
                                        [
                                            'company_id' => $policy->company_id,
                                            'employee_id' => $employee->id,
                                            'absence_type_id' => $policy->absence_type_id,
                                            'year' => $year,
                                        ],
                                        ['balance' => 0, 'used' => 0, 'pending' => 0]
                                    );

                                    if ($policy->max_balance && ($balance->balance + $accrualAmount) > $policy->max_balance) {
                                        return;
                                    }

                                    $balance->increment('balance', $accrualAmount);

                                    $description = "Monthly accrual — {$today->format('F Y')}";
                                    if ($appliedFloor && $legalFloor !== null && $countryLabel !== '') {
                                        $description .= sprintf(' (plancher légal %s : %.2f j/mois)', $countryLabel, $legalFloor);
                                    }

                                    LeaveAccrual::create([
                                        'company_id' => $policy->company_id,
                                        'employee_id' => $employee->id,
                                        'leave_policy_id' => $policy->id,
                                        'amount' => $accrualAmount,
                                        'type' => 'accrual',
                                        'description' => $description,
                                        'effective_date' => $today->toDateString(),
                                    ]);

                                    $count++;
                                });
                            } catch (\Throwable $e) {
                                Log::warning("Leave accrual failed for employee {$employee->id}: {$e->getMessage()}");
                            }
                        }
                    });
                });
        }

        $this->info("Accrued leave for {$count} employee-policy combinations.");

        return self::SUCCESS;
    }
}
