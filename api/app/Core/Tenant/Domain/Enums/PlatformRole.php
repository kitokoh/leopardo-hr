<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Enums;

/**
 * Issue #7553 — rôles internes de la plateforme.
 *
 * `super_admins.platform_role` (colonne publique, défaut `super_admin`)
 * porte ce rôle. Le défaut garantit la rétrocompatibilité : tous les comptes
 * créés avant cette évolution conservent les pleins pouvoirs.
 *
 * Matrice (issue #7553) :
 *
 * | rôle        | objet                                                          |
 * |-------------|----------------------------------------------------------------|
 * | super_admin | propriétaire du SaaS — tous les droits, y compris la délégation |
 * | admin       | administration complète, SAUF la distribution des rôles         |
 * | support     | tickets, comptes des entreprises, impersonation encadrée        |
 * | finance     | abonnements, facturation, offres, métriques                     |
 * | ops         | observabilité, kill switches, nœuds edge, métriques             |
 * | marketing   | CRM, annonces, vitrine                                          |
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Support = 'support';
    case Finance = 'finance';
    case Ops = 'ops';
    case Marketing = 'marketing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super administrateur',
            self::Admin => 'Administrateur plateforme',
            self::Support => 'Support',
            self::Finance => 'Finance',
            self::Ops => 'Opérations',
            self::Marketing => 'Marketing',
        };
    }

    /**
     * @return list<PlatformPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PlatformPermission::cases(),
            self::Admin => self::allExcept([PlatformPermission::TeamManage]),
            self::Support => [
                PlatformPermission::CompaniesView,
                PlatformPermission::UsersView,
                PlatformPermission::UsersManage,
                PlatformPermission::SupportManage,
                PlatformPermission::Impersonate,
                PlatformPermission::ObservabilityView,
                PlatformPermission::MetricsView,
            ],
            self::Finance => [
                PlatformPermission::CompaniesView,
                PlatformPermission::BillingView,
                PlatformPermission::BillingManage,
                PlatformPermission::PlansView,
                PlatformPermission::MetricsView,
            ],
            self::Ops => [
                PlatformPermission::CompaniesView,
                PlatformPermission::ObservabilityView,
                PlatformPermission::MetricsView,
                PlatformPermission::KillSwitchManage,
                PlatformPermission::EdgeManage,
            ],
            self::Marketing => [
                PlatformPermission::CompaniesView,
                PlatformPermission::CrmView,
                PlatformPermission::AnnouncementsManage,
                PlatformPermission::ShowcaseManage,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        $values = [];

        // Boucle explicite (et non `array_map`) : le résultat doit rester une
        // LISTE JSON, jamais un objet à clés trouées, même si une évolution de
        // `permissions()` filtre un élément du milieu de l'énumération.
        foreach ($this->permissions() as $permission) {
            $values[] = $permission->value;
        }

        return $values;
    }

    /**
     * @param  list<PlatformPermission>  $excluded
     * @return list<PlatformPermission>
     */
    private static function allExcept(array $excluded): array
    {
        $kept = [];

        foreach (PlatformPermission::cases() as $permission) {
            if (in_array($permission, $excluded, true)) {
                continue;
            }

            $kept[] = $permission;
        }

        return $kept;
    }

    public function hasPermission(PlatformPermission|string $permission): bool
    {
        $value = $permission instanceof PlatformPermission ? $permission->value : $permission;

        return in_array($value, $this->permissionValues(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /**
     * Seul le rôle `super_admin` distribue les rôles internes : un
     * administrateur plateforme gère la plateforme, pas l'équipe.
     */
    public function canManageTeam(): bool
    {
        return $this->hasPermission(PlatformPermission::TeamManage);
    }

    /**
     * Matrice rôle → permissions, sérialisable (documentation, recette).
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $matrix = [];
        foreach (self::cases() as $role) {
            $matrix[$role->value] = $role->permissionValues();
        }

        return $matrix;
    }
}
