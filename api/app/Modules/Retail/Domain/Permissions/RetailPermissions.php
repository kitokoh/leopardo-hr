<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Permissions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\EmployeeResourceAssignment;
use App\Modules\Retail\Domain\Manifests\RetailManifest;

/**
 * Permissions `retail.*` de la verticale Retail (BC-17 RETAIL, #9207).
 *
 * Le RBAC de la plateforme repose sur les roles de l'employe
 * (`Employee::role` + `manager_role`, methode `hasManagerRole(...)`) : il
 * n'existe pas de table de permissions. Les permissions `retail.*` declarees
 * par le manifest (`RetailManifest::permissions()`) etaient jusqu'ici
 * PUREMENT DECLARATIVES : aucune policy ne les consommait, et toutes les
 * ecritures Retail exigeaient `hasManagerRole('principal', 'rh')`.
 *
 * Consequence metier corrigee ici : un vendeur / caissier ne pouvait ni
 * ouvrir une caisse, ni encaisser, ni creer une commande sans recevoir le
 * role `principal` — qui lui ouvrait aussi la paie et les RH. Violation
 * directe du moindre privilege.
 *
 * MODELE EFFECTIF — deux plans, jamais confondus (miroir de
 * `RestaurantPermissions`, REST-OS-6 #8608) :
 *
 *  1. Plan DIRECTION : `manager_role` assignable (`principal`, `rh`).
 *     Les valeurs metier `seller` / `cashier` / `store_manager` ne sont PAS
 *     des valeurs assignables de `manager_role` (StoreEmployeeRequest :
 *     in:principal,rh,dept,comptable,superviseur,marketing). Les passer a
 *     `hasManagerRole()` produirait un motif MORT que le garde CI
 *     `check-manager-role-enum.sh` refuse explicitement.
 *
 *  2. Plan OPERATIONNEL : assignation de ressource
 *     (`employee_resource_assignments`, epique #7597) sur le type
 *     `retail_location`, avec les niveaux ordonnes
 *     `view` < `operate` < `manage` (`EmployeeResourceAssignment::satisfies`).
 *     C'est le SEUL mecanisme qui exprime « cette personne tient la caisse
 *     de CETTE boutique ».
 *
 * Chaque regle est FAIL-CLOSED et compose les deux plans :
 *  - aucune assignation `retail_location` dans l'entreprise (scoping non
 *    active) -> comportement historique : direction uniquement ;
 *  - scoping actif -> au moins une boutique accessible au niveau exige
 *    (`null` = acces total pour `principal`, et pour `rh` en lecture).
 *
 * Toute permission inconnue est refusee : `can()` retourne false et
 * `requiresManagerRoles()` retourne un tableau vide (aucun role requis ->
 * aucune autorisation implicite).
 *
 * @see RetailManifest
 * @see \App\Modules\RestaurantManager\Domain\Permissions\RestaurantPermissions
 */
final class RetailPermissions
{
    /** Caisse POS : ouverture/fermeture de session, encaissement, tickets. */
    public const POS = 'retail.pos';

    /** Catalogue : produits, categories, prix, publication. */
    public const CATALOG = 'retail.catalog';

    /** Stocks : inventaire, mouvements, reapprovisionnement. */
    public const STOCK = 'retail.stock';

    /** Commandes : creation, encaissement, annulation, remboursement. */
    public const ORDERS = 'retail.orders';

    /** Parametres commerce : TVA, imprimante, passerelles de paiement. */
    public const SETTINGS = 'retail.settings';

    /** Rapports agreges et exports CSV (#9209). */
    public const REPORTS = 'retail.reports';

    /** Type de ressource ressource-scopee de la verticale (epique #7597). */
    public const RESOURCE_TYPE = 'retail_location';

    /**
     * Roles de direction (`manager_role` reellement assignables) requis par
     * permission. C'est un PLANCHER : les regles effectives passent par les
     * methodes `can*()` ci-dessous, qui ajoutent le plan operationnel.
     * Une permission inconnue retombe sur un tableau vide (fail-closed).
     *
     * @return array<int, string> Roles requis (vide = refus pour tout le monde).
     */
    public function requiresManagerRoles(string $permission): array
    {
        return match ($permission) {
            self::POS, self::ORDERS, self::CATALOG, self::STOCK => ['principal', 'rh'],
            self::SETTINGS, self::REPORTS => ['principal', 'rh'],
            default => [],
        };
    }

    /**
     * Regle EFFECTIVE de `retail.pos` — tenir la caisse : ouvrir/fermer une
     * session, encaisser, imprimer un ticket.
     *
     * Direction, ou niveau `operate` sur au moins une boutique assignee.
     */
    public function canOperatePos(Employee $actor): bool
    {
        return $this->canOnAnyLocation($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * Regle EFFECTIVE de `retail.orders` — creer, encaisser, annuler et
     * rembourser une commande de vente.
     *
     * Direction, ou niveau `operate` sur au moins une boutique assignee.
     */
    public function canOperateOrders(Employee $actor): bool
    {
        return $this->canOnAnyLocation($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * Regle EFFECTIVE de `retail.catalog` — gerer produits, categories et
     * prix (ecriture).
     *
     * Direction, ou niveau `operate` sur au moins une boutique assignee : un
     * vendeur doit pouvoir corriger un prix ou creer un produit en rayon.
     */
    public function canManageCatalog(Employee $actor): bool
    {
        return $this->canOnAnyLocation($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * Regle EFFECTIVE de `retail.stock` — enregistrer un mouvement de stock
     * (reception, inventaire, casse).
     *
     * Direction, ou niveau `operate` sur au moins une boutique assignee.
     */
    public function canOperateStock(Employee $actor): bool
    {
        return $this->canOnAnyLocation($actor, EmployeeResourceAssignment::LEVEL_OPERATE);
    }

    /**
     * Regle EFFECTIVE de `retail.settings` — configuration d'entreprise
     * (TVA, imprimante, passerelles de paiement).
     *
     * Direction uniquement : c'est de la configuration de tenant, pas un
     * geste de caisse, donc aucune assignation operationnelle ne l'ouvre.
     */
    public function canManageSettings(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    /**
     * Regle EFFECTIVE de `retail.reports` — rapports agreges et exports CSV.
     *
     * Direction, ou niveau `manage` sur au moins une boutique assignee : un
     * gerant de magasin voit les chiffres de SA boutique, pas ceux du tenant.
     */
    public function canViewReports(Employee $actor): bool
    {
        return $this->canOnAnyLocation($actor, EmployeeResourceAssignment::LEVEL_MANAGE);
    }

    /**
     * Point d'entree unique des Gates `retail.*`.
     *
     * Toute permission inconnue est refusee (fail-closed) : aucune capacite
     * implicite n'est accordee par simple presence d'un role.
     */
    public function can(Employee $actor, string $permission): bool
    {
        return match ($permission) {
            self::POS => $this->canOperatePos($actor),
            self::ORDERS => $this->canOperateOrders($actor),
            self::CATALOG => $this->canManageCatalog($actor),
            self::STOCK => $this->canOperateStock($actor),
            self::SETTINGS => $this->canManageSettings($actor),
            self::REPORTS => $this->canViewReports($actor),
            default => false,
        };
    }

    /**
     * L'acteur peut-il agir sur CETTE boutique au niveau exige ?
     *
     * Utilise par les policies qui portent une ressource nommee (session de
     * caisse, commande, niveau de stock) : le perimetre est alors la boutique
     * de la ressource, pas « au moins une » boutique du tenant.
     *
     * Fail-closed : un niveau inconnu ne satisfait rien.
     */
    public function canOnLocation(
        Employee $actor,
        int $locationId,
        string $minLevel = EmployeeResourceAssignment::LEVEL_OPERATE,
    ): bool {
        if (EmployeeResourceAssignment::levelRank($minLevel) < 0) {
            return false;
        }

        $accessible = $actor->accessibleResourceIds(self::RESOURCE_TYPE, $minLevel);

        // `null` = aucune restriction (principal, ou scoping non active).
        if ($accessible === null) {
            return true;
        }

        return in_array($locationId, $accessible, true);
    }

    /**
     * Plan commun : direction (`manager_role` assignable) OU au moins une
     * boutique `retail_location` accessible au niveau minimum exige.
     *
     * Fail-closed : un niveau inconnu (`levelRank < 0`) ne satisfait rien,
     * `accessibleResourceIds` retourne alors une liste vide.
     */
    private function canOnAnyLocation(Employee $actor, string $minLevel): bool
    {
        if (! $actor->isResourceTypeScoped(self::RESOURCE_TYPE)) {
            // Scoping non active : comportement historique, direction seule.
            return $actor->hasManagerRole('principal', 'rh');
        }

        $accessible = $actor->accessibleResourceIds(self::RESOURCE_TYPE, $minLevel);

        return $accessible === null || $accessible !== [];
    }
}
