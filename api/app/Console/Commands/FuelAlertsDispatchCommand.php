<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Infrastructure\Services\FuelAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * fuel:alerts-dispatch — Détecte et notifie les anomalies FuelStation
 * (FUEL-019, issue #5813).
 *
 * Idempotent : chaque alerte notifiée est journalisée avec une clé unique
 * par tenant — un rejeu ne re-notifie jamais la même anomalie.
 *
 * Usage : php artisan fuel:alerts-dispatch
 * Scheduler : quotidien à 06:30 (route console).
 */
class FuelAlertsDispatchCommand extends Command
{
    protected $signature = 'fuel:alerts-dispatch';

    protected $description = 'Détecte et notifie les anomalies FuelStation (relevés, clôtures, écarts, maintenance).';

    public function handle(FuelAlertService $alerts): int
    {
        // Tous les tenants actifs (le job de déduplication est par tenant).
        $companies = Company::query()
            ->withoutGlobalScopes()
            ->where('status', 'active')
            ->get();

        $total = 0;

        foreach ($companies as $company) {
            $actor = Employee::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('role', 'manager')
                ->first();

            if (! $actor instanceof Employee) {
                continue;
            }

            try {
                $total += count($alerts->dispatchDaily($actor));
            } catch (\Throwable $e) {
                Log::error('fuel:alerts-dispatch failed for company', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("[fuel:alerts-dispatch] {$total} alerte(s) notifiée(s).");

        return self::SUCCESS;
    }
}
