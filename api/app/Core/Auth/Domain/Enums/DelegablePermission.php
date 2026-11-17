<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Enums;

/**
 * IAM-L5 (#9095) — registre FERMÉ des permissions délégables par un rôle
 * personnalisé de tenant.
 *
 * ## Pourquoi un registre fermé
 *
 * Un rôle personnalisé ne peut accorder que des capacités que son **créateur
 * détient déjà** (règle de plafond, `RoleDelegationGuard`). Pour que ce plafond
 * soit calculable, le catalogue doit être un ensemble **connu du moteur** :
 * une chaîne libre rendrait la comparaison impossible et ouvrirait la porte à
 * une permission inventée que personne ne vérifie.
 *
 * ## Dérivé, pas indépendant
 *
 * Chaque case correspond **exactement** à une clé de
 * `EmployeeResource::capabilities()` — le contrat de session existant — ou à un
 * grant de module (`ModuleKey`). Aucune permission nouvelle n'est inventée ici :
 * on ne fait que rendre **nommable** ce que le RBAC applique déjà.
 *
 * @see \App\Http\Resources\Api\V1\EmployeeResource::capabilities()
 * @see ModuleKey
 */
enum DelegablePermission: string
{
    case ViewDashboard = 'can_view_dashboard';
    case CreateEmployees = 'can_create_employees';
    case ManageInvitations = 'can_manage_invitations';
    case ManageBiometrics = 'can_manage_biometrics';
    case ViewPayroll = 'can_view_payroll';
    case ViewFleet = 'can_view_fleet';
    case IsPrincipal = 'is_principal';

    /**
     * Capacité de CRÉER/MODIFIER des rôles personnalisés. Réservée au
     * `principal` : un non-principal ne peut pas se donner — ni donner à
     * quiconque — le pouvoir de fabriquer des rôles. C'est le scénario #11 de
     * la matrice IAM (« un non-principal ne peut pas accorder
     * `delegation.manage` »).
     */
    case ManageDelegation = 'delegation.manage';

    /**
     * Libelle lisible du catalogue (ecran « Roles personnalises », #9518).
     *
     * Le front ne doit pas inventer ses libelles : la source unique est ici,
     * a cote de la cle qu'elle decrit.
     */
    public function label(): string
    {
        return match ($this) {
            self::ViewDashboard => 'Tableau de bord',
            self::CreateEmployees => 'Creer des collaborateurs',
            self::ManageInvitations => 'Gerer les invitations',
            self::ManageBiometrics => 'Gerer la biometrie',
            self::ViewPayroll => 'Consulter la paie',
            self::ViewFleet => 'Consulter la flotte',
            self::IsPrincipal => 'Proprietaire de l\'entreprise',
            self::ManageDelegation => 'Gerer les roles personnalises',
        };
    }

    /**
     * Regroupement du catalogue (l'ecran affiche une section par categorie).
     */
    public function category(): string
    {
        return match ($this) {
            self::ViewDashboard => 'general',
            self::CreateEmployees, self::ManageInvitations, self::ManageBiometrics => 'equipe',
            self::ViewPayroll => 'finance',
            self::ViewFleet => 'operations',
            self::IsPrincipal, self::ManageDelegation => 'administration',
        };
    }

    /**
     * Le catalogue complet, tel que l'expose `GET /company/roles`
     * (`data.catalog`) : `{key, label, category}` par entree.
     *
     * @return list<array{key: string, label: string, category: string}>
     */
    public static function catalog(): array
    {
        return array_map(
            static fn (self $case): array => [
                'key' => $case->value,
                'label' => $case->label(),
                'category' => $case->category(),
            ],
            self::cases(),
        );
    }

    /**
     * Le catalogue BORNE par ce que `$ceiling` detient deja.
     *
     * C'est le plafond de delegation applique a l'AFFICHAGE : un `rh` ne se
     * voit proposer que les permissions qu'il peut reellement deleguer, au
     * lieu de composer un role puis de se faire refuser en 422. Le refus
     * serveur reste en place (defense en profondeur) : ce filtre est un
     * confort d'interface, jamais le garde.
     *
     * @param  list<string>  $ceiling
     * @return list<array{key: string, label: string, category: string}>
     */
    public static function catalogBoundedBy(array $ceiling): array
    {
        return array_values(array_filter(
            self::catalog(),
            static fn (array $entry): bool => in_array($entry['key'], $ceiling, true),
        ));
    }

    /**
     * Toutes les clés du registre (validation `Rule::in`, exposition front).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /** Une clé appartient-elle au registre ? (fail-closed sur l'inconnu) */
    public static function isValid(string $key): bool
    {
        return self::tryFrom($key) !== null;
    }

    /**
     * Les permissions qu'un `manager_role` donné détient **par l'enum**.
     *
     * Miroir exact de `EmployeeResource::capabilities()` : c'est la source du
     * plafond. Un rôle personnalisé ne pourra jamais dépasser cette liste pour
     * son créateur.
     *
     * @return list<string>
     */
    public static function forManagerRole(?string $managerRole): array
    {
        if ($managerRole === null || $managerRole === '') {
            return [];
        }

        $permissions = [self::ViewDashboard->value, self::ViewFleet->value];

        if (in_array($managerRole, ['principal', 'rh'], true)) {
            $permissions[] = self::CreateEmployees->value;
            $permissions[] = self::ManageInvitations->value;
        }

        if (in_array($managerRole, ['principal', 'superviseur'], true)) {
            $permissions[] = self::ManageBiometrics->value;
        }

        if (in_array($managerRole, ['principal', 'comptable'], true)) {
            $permissions[] = self::ViewPayroll->value;
        }

        if ($managerRole === 'principal') {
            $permissions[] = self::IsPrincipal->value;
            $permissions[] = self::ManageDelegation->value;
        }

        return $permissions;
    }
}
