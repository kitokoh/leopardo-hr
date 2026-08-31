<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Exceptions\DomainException;
use Illuminate\Console\Command;

/**
 * FUEL-001 — activation ops/pilote d'une solution sectorielle par tenant.
 *
 * Exemple : php artisan leopardo:solution:activate {company} fuel_station
 * Idempotente ; refuse une solution inconnue (allowlist) ou un tenant
 * dont les modules requis sont inactifs.
 */
class ActivateSolutionCommand extends Command
{
    protected $signature = 'leopardo:solution:activate
        {company : Company UUID}
        {solution : Solution code (ex. fuel_station)}
        {--actor= : Employee id for audit (optional)}';

    protected $description = 'Activate a sectorial solution (feature flag) for a tenant.';

    public function handle(SolutionActivator $activator): int
    {
        $companyId = (string) $this->argument('company');

        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            $this->error(sprintf('Company introuvable : %s', $companyId));

            return self::FAILURE;
        }

        $actorId = $this->option('actor') !== null ? (int) $this->option('actor') : null;

        try {
            $result = $activator->activate($company, (string) $this->argument('solution'), $actorId);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
