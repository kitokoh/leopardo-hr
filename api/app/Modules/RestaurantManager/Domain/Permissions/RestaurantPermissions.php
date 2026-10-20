<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Permissions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;

/**
 * Permissions `restaurant.*` de la verticale RestaurantManager
 * (RESTO-306, issue #6187 — matrice RBAC BC-25).
 *
 * Le RBAC de la plateforme est base sur les roles de l'employe
 * (App\Core\Auth\Domain\Models\Employee : champs `role` + `manager_role`,
 * methode `hasManagerRole(...)`) : il n'existe pas de table de permissions.
 * Les permissions `restaurant.*` declarees par le manifest
 * (RestaurantManagerManifest::permissions(), RESTO-106/#6163) sont mappees sur
 * les personas de la spec `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md`
 * (§1.2) et consommees par les Policies / Gates du module (voir
 * `docs/architecture/RBAC_RESTAURANT_MATRIX.md`).
 *
 * MODELE EFFECTIF (REST-OS-6, #8608) — deux plans, jamais confondus :
 *
 *  1. Plan DIRECTION : `manager_role` assignable uniquement (`principal`,
 *     `rh`, ...). Les valeurs metier `manager` / `server` / `cashier` /
 *     `kitchen` / `rider` ne sont PAS des valeurs assignables de
 *     `manager_role` (StoreEmployeeRequest / UpdateEmployeeRequest :
 *     in:principal,rh,dept,comptable,superviseur,marketing). Les passer a
 *     `hasManagerRole()` produit un motif MORT (condition toujours fausse)
 *     que le garde CI `check-manager-role-enum.sh` (#7600, R3 de l'epique
 *     #7597) refuse explicitement.
 *
 *  2. Plan OPERATIONNEL : assignation de ressource
 *     (`employee_resource_assignments`, epique #7597) sur le type
 *     `restaurant_branch`, avec les niveaux ordonnes
 *     `view` < `operate` < `manage` (EmployeeResourceAssignment::satisfies).
 *     C'est le SEUL mecanisme qui exprime « cette personne fait la cuisine
 *     dans CETTE succursale ».
 *
 * Chaque regle ci-dessous est FAIL-CLOSED et compose les deux plans :
 *  - aucune assignation `restaurant_branch` dans l'entreprise (scoping non
 *    active) -> comportement historique : direction uniquement ;
 *  - scoping actif -> au moins une succursale accessible au niveau exige
 *    (`null` = acces total pour `principal`, et pour `rh` en lecture).
 *
 * Toute permission inconnue est refusee : `can()` retourne false et
 * `requiresManagerRoles()` retourne un tableau vide (aucun role requis ->
 * aucune autorisation implicite).
 *
 * @see RestaurantManagerManifest
 * @see docs/architecture/RBAC_RESTAURANT_MATRIX.md
 */
final class RestaurantPermissions
{
    /** Configuration globale (etablissements, catalogue, tarifs, clotures). */
    public const MANAGE = 'restaurant.manage';

    /** Pilotage operationnel de la salle (zones, tables, menus, horaires). */
    public const MANAGER = 'restaurant.manager';

    /** Operations serveur / caisse (lecture + prise de commande). */
    public const SERVER = 'restaurant.server';

    /** File de commandes en cuisine (ecran). */
    public const KITCHEN = 'restaurant.kitchen';

    /** Tournees de livraison. */
    public const RIDER = 'restaurant.rider';

    /** Lecture / rapports (croise tous les roles operationnels). */
    public const REPORTS = 'restaurant.reports';

    /** Gestion des travaux d'impression (reimpression, annulation, diagnostic). */
    public const PRINT_JOBS_MANAGE = 'restaurant.print_jobs.manage';

    /** Operation d'impression (recuperation/accuse cote bridge cuisine). */
    public const PRINT_JOBS_OPERATE = 'restaurant.print_jobs.operate';

    /** Type de ressource ressource-scopee de la verticale (epique #7597). */
    public const RESOURCE_TYPE = 'restaurant_branch';

    /**
     * Roles de direction (`manager_role` reellement assignables) requis par
     * permission. C'est un PLANCHER : les regles effectives passent par les
     * methodes `can*()` ci-dessous, qui ajoutent le plan operationnel
     * (assignation de ressource). Une permission inconnue retombe sur un
     * tableau vide (fail-closed : aucun role n'autorise l'action).
     *
     * @return array<int, string> Roles requis (vide = refus pour tout le monde).
     */
    public function requiresManagerRoles(string $permission): array
    {
        return match ($permission) {
            self::MANAGE => ['principal', 'rh'],
            self::MANAGER => ['principal', 'rh'],
            self::SERVER, self::KITCHEN, self::RIDER, self::REPORTS => ['principal', 'rh'],
            self::PRINT_JOBS_MANAGE => ['principal', 'rh'],
            self::PRINT_JOBS_OPERATE => ['principal', 'rh'],
            default => [],
        };
    }

    /**
     * Regle EFFECTIVE de la permission `restaurant.reports` (#8180) — rapports
     * agreges + export CSV, surfaces sans modele propre.
     *
     * Implementation canonique unique, invoquee par le Gate
     * `restaurant.reports` enregistre dans `AuthServiceProvider` : les call
     * sites utilisent `$actor->can()/cannot('restaurant.reports')` et ne
     * doivent JAMAIS reimplementer la regle localement (la copie inline des
     * controleurs etait devenue un 403 systematique — bug d'origine de
     * #8180 : capacite indefinie -> `cannot()` vaut toujours true).
     *
     * Alignee sur l'architecture d'autorisation canonique du module (#7599,
     * ressource-scopee) :
     *
     *  - aucune assignation `restaurant_branch` dans l'entreprise ->
     *    comportement historique : `principal`/`rh` ;
     *  - assignations presentes (scoping actif, fail-closed) -> niveau
     *    `manage` sur au moins une succursale assignee (le `principal` passe
     *    toujours via `accessibleResourceIds` = null).
     */
    public function canViewReports(Employee $actor): bool
    {
        if (! $actor->isResourceTypeScoped(self::RESOURCE_TYPE)) {
            return $actor->hasManagerRole('principal', 'rh');
        }

        $manageable = $actor->accessibleResourceIds(
            self::RESOURCE_TYPE,
            EmployeeResourceAssignment::LEVEL_MANAGE
        );

        return $manageable === null || $manageable !== [];
    }

    /**
     * REST-OS-6 (#8608) — regle EFFECTIVE de `restaurant.manage` (configuration
     * du tenant : etablissements, catalogue, tarifs, clotures).
     *
     * Direction uniquement : c'est de la configuration d'entreprise, pas un
     * geste de salle, donc aucune assignation operationnelle ne l'ouvre.
     * Fail-closed : tout autre role est refuse.
     */
    public function canManage(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * REST-OS-6 (#8608) — regle EFFECTIVE de `restaurant.manager` (pilotage
     * operationnel de la salle : zones, tables, menus, horaires).
     *
     * Direction, ou niveau `manage` sur au moins une succursale assignee.
     * Fail-closed pour les niveaux `view` / `operate`.
     */
    public function canManageFloor(Employee $actor): bool
    {
        return $this->canOnAnyBranch($actor, EmployeeResourceAssignment::LEVEL_MANAGE);
    }

    /**
     * REST-OS-6 (#8608) — regle EFFECTIVE de `restaurant.server` (prise de
     * commande, service, encaissement).
     *
     * Direction, ou niveau `operate` sur au moins une succursale assignee
     * (un niveau `manage` satisfait `operate` : les niveaux sont ordonnes).
     */
    public function canServe(Employee $actor): bool
    {
        return $this->canOnAnyBranch($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * REST-OS-6 (#8608) — regle EFFECTIVE de `restaurant.kitchen` (file de
     * commandes en cuisine).
     *
     * Direction, ou niveau `operate` sur au moins une succursale assignee.
     */
    public function canWorkKitchen(Employee $actor): bool
    {
        return $this->canOnAnyBranch($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * REST-OS-6 (#8608) — regle EFFECTIVE de `restaurant.rider` (tournees de
     * livraison).
     *
     * Direction, ou niveau `operate` sur au moins une succursale assignee.
     */
    public function canDeliver(Employee $actor): bool
    {
        return $this->canOnAnyBranch($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * REST-OS-6 (#8608) — point d'entree unique des Gates `restaurant.*`
     * (hors `restaurant.print_jobs.*`, qui ont leurs propres regles).
     *
     * Toute permission inconnue est refusee (fail-closed) : aucune capacite
     * implicite n'est accordee par simple presence d'un role.
     */
    public function can(Employee $actor, string $permission): bool
    {
        return match ($permission) {
            self::MANAGE => $this->canManage($actor),
            self::MANAGER => $this->canManageFloor($actor),
            self::SERVER => $this->canServe($actor),
            self::KITCHEN => $this->canWorkKitchen($actor),
            self::RIDER => $this->canDeliver($actor),
            self::REPORTS => $this->canViewReports($actor),
            default => false,
        };
    }

    /**
     * Plan commun : direction (`manager_role` assignable) OU au moins une
     * succursale `restaurant_branch` accessible au niveau minimum exige.
     *
     * Fail-closed : un niveau inconnu (`levelRank < 0`) ne satisfait rien,
     * `accessibleResourceIds` retourne alors une liste vide.
     */
    private function canOnAnyBranch(Employee $actor, string $minLevel): bool
    {
        if (! $actor->isResourceTypeScoped(self::RESOURCE_TYPE)) {
            // Scoping non active : comportement historique, direction seule.
            return $actor->hasManagerRole('principal', 'rh');
        }

        $accessible = $actor->accessibleResourceIds(self::RESOURCE_TYPE, $minLevel);

        return $accessible === null || $accessible !== [];
    }
}
