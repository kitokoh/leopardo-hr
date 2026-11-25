<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Planning\Domain\Models\LeaveAccrual;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeavePolicy;
use App\Support\SearchPathGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeaveCarryForward extends Command
{
    protected $signature = 'leave:carry-forward {--year= : Year to carry forward from (defaults to previous year)}';

    protected $description = 'Carry forward unused leave balances to the new year and expire old carry-forwards';

    public function handle(): int
    {
        // Les commandes console peuvent être invoquées depuis un worker ayant
        // laissé un search_path public ou tenant spécifique. Cette commande
        // globale lit et écrit dans le schéma tenant partagé canonique.
        //
        // Issue #9115 : SearchPathGuard restaure l'état d'entrée dans un
        // finally. Sans cela le worker gardait `shared_tenants` pour la tâche
        // suivante — exactement le mode de défaillance qui faisait échouer le
        // job Backend (42P01 puis 25P02 en cascade).
        return SearchPathGuard::run('shared_tenants, public', fn (): int => $this->carryForward());
    }

    private function carryForward(): int
    {
        $fromYear = (int) ($this->option('year') ?? (now()->year - 1));
        $toYear = $fromYear + 1;

        $this->info("Processing carry-forward from {$fromYear} to {$toYear}...");

        // Commande globale : elle traite tous les tenants et ne doit pas
        // heriter d'un `current_company` eventuellement present dans le
        // processus de test/worker.
        $policies = LeavePolicy::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')
            ->where('active', true)
            ->where('carry_forward', true)
            ->get();

        $carried = 0;
        $expired = 0;

        foreach ($policies as $policy) {
            // #9924 — RLS sur `leave_balances` / `leave_accruals`. La policy
            // compare `company_id` a `app.current_company_id` ; sans tenant
            // publie, `current_setting(..., true)` vaut NULL et AUCUNE ligne
            // n'est visible — les updates seraient silencieusement sans effet.
            //
            // `crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')` ne contourne que le scope Eloquent, pas
            // RLS. `withinTenant()` publie le tenant ET pose le search_path,
            // puis restaure l'etat precedent en `finally`.
            //
            // `LeavePolicy` n'a pas de relation `company()` : on resout la
            // company par son id. Une politique orpheline (company supprimee)
            // est ignoree — il n'y a alors aucun tenant a publier, et ses
            // soldes ne sont plus atteignables de toute facon.
            $company = Company::query()->find($policy->company_id);

            if ($company === null) {
                Log::warning('Carry-forward : politique orpheline ignoree', [
                    'leave_policy_id' => $policy->id,
                    'company_id' => $policy->company_id,
                ]);

                continue;
            }

            app(TenantManager::class)->withinTenant($company, function () use ($policy, $fromYear, $toYear, &$carried): void {
                $this->carryForwardForPolicy($policy, $fromYear, $toYear, $carried);
            });
        }

        $this->expireOldCarryForwards($toYear, $expired);

        $this->info("Carry-forward complete: {$carried} balance(s) carried, {$expired} expired.");

        return self::SUCCESS;
    }

    /**
     * Report des soldes d'une politique, sous le tenant deja publie.
     */
    private function carryForwardForPolicy(LeavePolicy $policy, int $fromYear, int $toYear, int &$carried): void
    {
        $balances = LeaveBalance::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')
            ->where('company_id', $policy->company_id)
            ->where('absence_type_id', $policy->absence_type_id)
            ->where('year', $fromYear)
            ->get();

        foreach ($balances as $oldBalance) {
            try {
                DB::transaction(function () use ($policy, $oldBalance, $toYear, &$carried): void {
                    // Issue #2416 : les jours `pending` (demandes en attente,
                    // réservés par AbsenceService depuis #2329) doivent être
                    // déduits du solde reportable — sinon une demande
                    // approuvée après le report consomme le solde de la
                    // nouvelle année.
                    $unused = max(0, $oldBalance->balance - $oldBalance->used - $oldBalance->pending);
                    if ($unused <= 0) {
                        return;
                    }

                    $maxCarry = $policy->carry_forward_max ?? $unused;
                    $carryAmount = min($unused, $maxCarry);

                    $newBalance = LeaveBalance::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')->firstOrCreate(
                        [
                            'company_id' => $policy->company_id,
                            'employee_id' => $oldBalance->employee_id,
                            'absence_type_id' => $policy->absence_type_id,
                            'year' => $toYear,
                        ],
                        ['balance' => 0, 'used' => 0, 'pending' => 0]
                    );

                    $newBalance->increment('balance', $carryAmount);

                    LeaveAccrual::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')->create([
                        'company_id' => $policy->company_id,
                        'employee_id' => $oldBalance->employee_id,
                        'leave_policy_id' => $policy->id,
                        'amount' => $carryAmount,
                        'type' => 'carry_forward',
                        'description' => "Report de {$carryAmount} jour(s) de ".($toYear - 1),
                        'effective_date' => now()->startOfYear()->toDateString(),
                    ]);

                    $carried++;
                });
            } catch (\Throwable $e) {
                Log::warning("Carry-forward failed for employee {$oldBalance->employee_id}: {$e->getMessage()}");

            }
        }
    }

    private function expireOldCarryForwards(int $currentYear, int &$expired): void
    {
        $policies = LeavePolicy::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')
            ->where('active', true)
            ->where('carry_forward', true)
            ->whereNotNull('carry_forward_expiry_days')
            ->where('carry_forward_expiry_days', '>', 0)
            ->get();

        foreach ($policies as $policy) {
            $expiryDate = now()->startOfYear()->addDays($policy->carry_forward_expiry_days);

            if (now()->lt($expiryDate)) {
                continue;
            }

            // #9924 — meme raison que ci-dessus : sans tenant publie, la
            // policy RLS rend les lignes invisibles et l'expiration ne
            // s'applique jamais (echec silencieux).
            $company = Company::query()->find($policy->company_id);

            if ($company === null) {
                continue;
            }

            app(TenantManager::class)->withinTenant($company, function () use ($policy, $currentYear, &$expired): void {
                $this->expireForPolicy($policy, $currentYear, $expired);
            });
        }
    }

    /**
     * Expiration des reports d'une politique, sous le tenant deja publie.
     */
    private function expireForPolicy(LeavePolicy $policy, int $currentYear, int &$expired): void
    {
        $carryForwards = LeaveAccrual::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')
            ->where('leave_policy_id', $policy->id)
            ->where('type', 'carry_forward')
            ->where('effective_date', now()->startOfYear()->toDateString())
            ->whereNull('expired_at')
            ->get();

        foreach ($carryForwards as $accrual) {
            $balance = LeaveBalance::crossTenantForSystemTask('Report de conges : commande globale qui itere tous les tenants (#9924)')
                ->where('company_id', $accrual->company_id)
                ->where('employee_id', $accrual->employee_id)
                ->where('absence_type_id', $policy->absence_type_id)
                ->where('year', $currentYear)
                ->first();

            if ($balance) {
                $deduction = min($accrual->amount, $balance->balance - $balance->used);
                if ($deduction > 0) {
                    $balance->decrement('balance', $deduction);
                }
            }

            $accrual->update(['expired_at' => now()]);
            $expired++;
        }
    }
}
