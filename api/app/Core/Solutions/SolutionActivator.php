<?php

declare(strict_types=1);

namespace App\Core\Solutions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Solutions\Exceptions\SolutionMissingDependencyException;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\SolutionActivated;
use Illuminate\Support\Facades\DB;

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
        private readonly SolutionPermissionInstaller $permissionInstaller,
    ) {}

    public function isActive(Company $company, string $code): bool
    {
        return $company->hasFeature($code);
    }

    /**
     * Activation au provisioning (BC-25, #6693) — plan d'activation complet :
     * active d'abord les modules requis du manifest (commande `activate_modules`
     * de la spec PLATFORM_ONBOARDING_AND_VERTICAL_SOLUTIONS.md), puis la
     * solution elle-même. Contrairement à `activate()` (fail-closed strict,
     * pour l'activation manuelle d'un tenant déjà en vie), ici les modules
     * requis font partie du pack demandé par le prospect au signup : un
     * tenant frais n'a que `rh` d'actif, il faut donc activer les modules
     * transversaux du pack avant la solution.
     *
     * @return array{code: string, status: string, missing: list<string>}
     */
    public function activateWithDependencies(Company $company, string $code, ?int $actorId = null): array
    {
        $manifest = $this->catalogue->resolve($code); // 404 si inconnu

        $enabled = [];
        foreach ($manifest->requiredModules() as $module) {
            if (! $company->hasFeature($module)) {
                $company->setFeature($module, true);
                $enabled[] = $module;
            }
        }

        if ($enabled !== []) {
            $company->save();

            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $actorId,
                'action' => 'solution.dependencies_activated',
                'auditable_type' => Company::class,
                'auditable_id' => null,
                'old_values' => ['modules' => []],
                'new_values' => [
                    'solution' => $code,
                    'modules' => $enabled,
                ],
            ]);
        }

        return $this->activate($company, $code, $actorId);
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

        // BOS-013 (#8200) — flag + installation effective des permissions
        // déclarées par le manifest (grants du principal) + audit enrichi,
        // dans UNE transaction d'activation : tout est posé, ou rien.
        DB::transaction(function () use ($company, $code, $manifest, $actorId): void {
            $company->setFeature($code, true);
            $company->save();

            $installed = $this->permissionInstaller->install($company, $manifest);

            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $actorId,
                'action' => 'solution.activated',
                'auditable_type' => Company::class,
                'auditable_id' => null,
                'old_values' => ['status' => 'inactive'],
                'new_values' => [
                    'solution' => $code,
                    'industry' => $manifest->industry()->value,
                    'required_modules' => $manifest->requiredModules(),
                    'permissions_installed' => $installed,
                ],
            ]);
        });

        // Audit 2026-09-14 : poser le flag ne suffit pas à rendre une verticale
        // UTILISABLE. Une solution peut avoir besoin d'un référentiel ou d'une
        // configuration initiale — ex. TravelAgency sans pays/villes ne peut
        // créer aucun trajet, alors que le flag est actif et l'UI accessible.
        // Le module concerné écoute cet événement pour installer ses données
        // d'amorçage (idempotent), sans couplage core → module.
        SolutionActivated::dispatch($company, $code);

        return ['code' => $code, 'status' => 'activated', 'missing' => []];
    }

    /**
     * Désactivation manuelle d'une solution — BOS-013 (#8200) : retrait
     * propre des grants installés à l'activation, dans la même logique
     * transactionnelle (opération inverse), audit symétrique
     * (`solution.deactivated`). Idempotente : une solution inactive est un
     * no-op (`already_inactive`) ; code inconnu refusé (fail-closed).
     *
     * @return array{code: string, status: string}
     */
    public function deactivate(Company $company, string $code, ?int $actorId = null): array
    {
        $manifest = $this->catalogue->resolve($code); // 404 si inconnu

        if (! $this->isActive($company, $code)) {
            return ['code' => $code, 'status' => 'already_inactive'];
        }

        // Les codes de permission PARTAGÉS avec une autre solution encore
        // active du tenant sont conservés (ex. `restaurant.manager`, déclaré
        // par les deux manifests de la verticale restaurant).
        $keptCodes = [];
        foreach ($this->catalogue->codes() as $otherCode) {
            if ($otherCode === $code || ! $this->isActive($company, $otherCode)) {
                continue;
            }

            $keptCodes = array_merge($keptCodes, array_keys($this->catalogue->resolve($otherCode)->permissions()));
        }

        DB::transaction(function () use ($company, $code, $manifest, $actorId, $keptCodes): void {
            $revoked = $this->permissionInstaller->revoke($company, $manifest, $keptCodes);

            $company->setFeature($code, false);
            $company->save();

            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $actorId,
                'action' => 'solution.deactivated',
                'auditable_type' => Company::class,
                'auditable_id' => null,
                'old_values' => ['status' => 'active'],
                'new_values' => [
                    'solution' => $code,
                    'industry' => $manifest->industry()->value,
                    'permissions_revoked' => $revoked,
                ],
            ]);
        });

        return ['code' => $code, 'status' => 'deactivated'];
    }

    /**
     * Installation des permissions d'une solution DÉJÀ active — chemin de la
     * console plateforme, qui pose le flag directement sans passer par
     * `activate()`. Idempotent, fail-closed (code inconnu refusé).
     *
     * @return list<string> codes nouvellement installés
     */
    public function installPermissionsFor(Company $company, string $code): array
    {
        return $this->permissionInstaller->install($company, $this->catalogue->resolve($code));
    }

    /** Le code appartient-il à l'allowlist du catalogue (fail-closed) ? */
    public function isKnownSolution(string $code): bool
    {
        return $this->catalogue->has($code);
    }
}
