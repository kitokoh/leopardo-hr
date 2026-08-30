<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Tenant\Domain\Models\Company;

/**
 * Activation d'une solution sectorielle par tenant — FUEL-001.
 *
 * Propriétés (spec §2.1) :
 *  - idempotente : ré-activer une solution déjà active est un no-op ;
 *  - fail-closed : code inconnu = refusé (allowlist `SolutionCatalogue`) ;
 *  - dépendances : les modules requis par le manifest doivent être actifs
 *    sur le tenant, sinon l'activation est refusée ;
 *  - auditée : chaque activation est tracée (`solution.activated`).
 *
 * L'activation passe par le mécanisme feature flag existant
 * (`Company::setFeature` / `KNOWN_MODULES`) — aucune table dédiée.
 */
final class SolutionActivator
{
    public function __construct(
        private readonly SolutionCatalogue $catalogue,
    ) {}

    public function isActive(Company $company, string $code): bool
    {
        return $company->hasFeature($code);
    }

    /**
     * @return array{code: string, status: string, missing: list<string>}
     */
    public function activate(Company $company, string $code, ?int $actorId = null): array
    {
        $manifest = $this->catalogue->resolve($code); // 404 si inconnu

        if ($this->isActive($company, $code)) {
            return ['code' => $code, 'status' => 'already_active', 'missing' => []];
        }

        $missing = [];

        foreach ($manifest->requiredModules() as $module) {
            if (! $company->hasFeature($module)) {
                $missing[] = $module;
            }
        }

        if ($missing !== []) {
            throw new SolutionMissingDependencyException($missing);
        }

        $company->setFeature($code, true);
        $company->save();

        AuditLog::create([
            'company_id' => $company->id,
            'user_id' => $actorId,
            'action' => 'solution.activated',
            'auditable_type' => Company::class,
            'auditable_id' => null,
            'old_values' => ['status' => 'inactive'],
            'new_values' => [
                'solution' => $code,
                'required_modules' => $manifest->requiredModules(),
            ],
        ]);

        return ['code' => $code, 'status' => 'activated', 'missing' => []];
    }
}
