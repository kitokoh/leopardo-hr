<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Permissions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\RestaurantManager\Domain\Manifests\RestaurantManagerManifest;

/**
 * Permissions documentaires `restaurant.*` de la verticale RestaurantManager
 * (RESTO-306, issue #6187 — matrice RBAC BC-25).
 *
 * Le RBAC de la plateforme est basé sur les rôles de l'employé
 * (App\Core\Auth\Domain\Models\Employee : champs `role` + `manager_role`,
 * méthode `hasManagerRole(...)`) : il n'existe pas de table de permissions.
 * Les permissions `restaurant.*` déclarées par le manifest
 * (RestaurantManagerManifest::permissions(), RESTO-106/#6163) sont donc des
 * constantes documentaires, mappées sur les rôles des personas de la spec
 * `docs/specifications/SOLUTION_RESTAURANT_MANAGER.md` (§1.2), et consommées
 * par les Policies du module (voir `docs/architecture/RBAC_RESTAURANT_MATRIX.md`).
 *
 * Convention de mapping (fail-closed) :
 * - `MANAGE`  → configuration : gérant / propriétaire (principal, rh).
 * - `MANAGER` → pilotage opérationnel de la salle (principal, rh, manager).
 * - `SERVER`, `KITCHEN`, `RIDER`, `REPORTS` → lecture / opérationnel :
 *   ouvert à tous les sous-rôles métier du restaurant
 *   (principal, rh, manager, server, kitchen, rider).
 *
 * Toute permission inconnue est refusée : `requiresManagerRoles()` retourne
 * un tableau vide (aucun rôle requis → aucune autorisation).
 *
 * @see RestaurantManagerManifest
 * @see docs/architecture/RBAC_RESTAURANT_MATRIX.md
 */
final class RestaurantPermissions
{
    /** Configuration globale (établissements, catalogue, tarifs, rapports, clotures). */
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

    /**
     * Retourne les `manager_role` (avec `role = 'manager'`) requis pour une
     * permission `restaurant.*` donnee.
     *
     * Mapping documentaire utilise par les Policies du module via
     * `Employee::hasManagerRole(...)` ; une permission inconnue retombe sur
     * un tableau vide (fail-closed : aucun role n'autorise l'action).
     *
     * @return array<int, string> Rôles requis (vide = refus pour tout le monde).
     */
    public function requiresManagerRoles(string $permission): array
    {
        return match ($permission) {
            self::MANAGE => ['principal', 'rh'],
            self::MANAGER => ['principal', 'rh', 'manager'],
            self::SERVER, self::KITCHEN, self::RIDER, self::REPORTS => ['principal', 'rh', 'manager', 'server', 'kitchen', 'rider'],
            self::PRINT_JOBS_MANAGE => ['principal', 'rh', 'manager'],
            self::PRINT_JOBS_OPERATE => ['principal', 'rh', 'manager', 'server', 'kitchen'],
            default => [],
        };
    }

    /**
     * Règle EFFECTIVE de la permission `restaurant.reports` (#8180) — seule
     * permission `restaurant.*` consommée par des call sites HTTP (rapports
     * agrégés + export CSV, surfaces sans modèle propre).
     *
     * Implémentation canonique unique, invoquée par le Gate
     * `restaurant.reports` enregistré dans `AuthServiceProvider` : les call
     * sites utilisent `$actor->can()/cannot('restaurant.reports')` et ne
     * doivent JAMAIS réimplémenter la règle localement (la copie inline des
     * contrôleurs était devenue un 403 systématique — bug d'origine de
     * #8180 : capacité indéfinie → `cannot()` vaut toujours true).
     *
     * Alignée sur l'architecture d'autorisation canonique du module (#7599,
     * ressource-scopée) — les valeurs `manager`/`server`/`kitchen`/`rider`
     * du mapping documentaire ci-dessus sont MORTES pour ce cas : elles ne
     * sont pas assignables via `manager_role` (trou n°2 de l'épique #7597) :
     *
     *  - aucune assignation `restaurant_branch` dans l'entreprise →
     *    comportement historique : `principal`/`rh` ;
     *  - assignations présentes (scoping actif, fail-closed) → niveau
     *    `manage` sur au moins une succursale assignée (le `principal`
     *    passe toujours via `accessibleResourceIds` = null).
     */
    public function canViewReports(Employee $actor): bool
    {
        if (! $actor->isResourceTypeScoped('restaurant_branch')) {
            return $actor->hasManagerRole('principal', 'rh');
        }

        $manageable = $actor->accessibleResourceIds(
            'restaurant_branch',
            EmployeeResourceAssignment::LEVEL_MANAGE
        );

        return $manageable === null || $manageable !== [];
    }

    /**
     * REST-OS-6 (#8608) — règle EFFECTIVE de `restaurant.manage` (configuration
     * du tenant : établissements, catalogue, tarifs, clôtures).
     *
     * Réservée à la direction (`principal`/`rh`) : c'est de la configuration
     * d'entreprise, pas un geste de salle. Fail-closed : tout autre rôle est
     * refusé.
     */
    public function canManage(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * REST-OS-6 (#8608) — règle EFFECTIVE de `restaurant.manager` (pilotage
     * opérationnel de la salle : zones, tables, menus, horaires).
     *
     * Direction + gérant de salle. Fail-closed pour les rôles opérationnels
     * (serveur, cuisine, livreur, caisse, stock).
     */
    public function canManageFloor(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager');
    }

    /**
     * REST-OS-6 (#8608) — règle EFFECTIVE de `restaurant.server` (prise de
     * commande, service, encaissement).
     *
     * Direction + gérant + serveur + caissier. La cuisine et le livreur ne
     * prennent pas de commande (fail-closed).
     */
    public function canServe(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'server', 'cashier');
    }

    /**
     * REST-OS-6 (#8608) — règle EFFECTIVE de `restaurant.kitchen` (file de
     * commandes en cuisine).
     *
     * Direction + gérant + cuisine. Le serveur, le caissier et le livreur
     * n'accèdent pas à l'écran cuisine (fail-closed).
     */
    public function canWorkKitchen(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'kitchen');
    }

    /**
     * REST-OS-6 (#8608) — règle EFFECTIVE de `restaurant.rider` (tournées de
     * livraison).
     *
     * Direction + gérant + livreur. Fail-closed pour les autres rôles.
     */
    public function canDeliver(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh', 'manager', 'rider');
    }

    /**
     * REST-OS-6 (#8608) — point d'entrée unique des Gates `restaurant.*`
     * (hors `restaurant.reports` et `restaurant.print_jobs.*`, qui ont leurs
     * propres règles). Toute permission inconnue est refusée (fail-closed) :
     * aucune capacité implicite n'est accordée par simple présence d'un rôle.
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
}
