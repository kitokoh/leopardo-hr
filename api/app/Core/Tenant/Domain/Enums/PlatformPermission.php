<?php

declare(strict_types=1);

namespace App\Core\Tenant\Domain\Enums;

/**
 * Issue #7553 — permissions internes de la plateforme.
 *
 * La console plateforme ne connaissait qu'un seul niveau d'accès (être
 * super admin ou ne pas l'être) : impossible de confier le support, la
 * facturation ou l'observabilité à un collaborateur interne sans lui donner
 * les pleins pouvoirs (provisioning, impersonation, kill switches).
 *
 * Ces permissions sont volontairement peu nombreuses et lisibles : elles
 * décrivent des familles d'écrans, pas des boutons. Elles sont portées par
 * `PlatformRole` (matrice ci-dessous) et vérifiées à l'exécution par
 * `EnsurePlatformPermissionMiddleware` (`platform.permission:<perm>`).
 *
 * @see PlatformRole::permissions()
 * @see \App\Http\Middleware\EnsurePlatformPermissionMiddleware
 */
enum PlatformPermission: string
{
    case CompaniesView = 'companies.view';
    case CompaniesManage = 'companies.manage';
    case CompaniesProvision = 'companies.provision';

    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';
    case PlansView = 'plans.view';
    case PlansManage = 'plans.manage';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case Impersonate = 'impersonate';

    case KillSwitchManage = 'killswitch.manage';
    case ObservabilityView = 'observability.view';
    case MetricsView = 'metrics.view';

    case SupportManage = 'support.manage';
    case AnnouncementsManage = 'announcements.manage';
    case CrmView = 'crm.view';
    case ShowcaseManage = 'showcase.manage';
    case EdgeManage = 'edge.manage';

    /**
     * #7973 — administration des webhooks sortants de la plateforme : un
     * endpoint pointant vers une URL arbitraire est un canal d'exfiltration
     * des événements plateforme → réservé à admin + ops.
     */
    case WebhooksManage = 'webhooks.manage';

    /**
     * #7973 — réglages plateforme sensibles : templates d'e-mails officiels
     * (phishing via mails légitimes), réglages IA (clés chiffrées), barèmes
     * fiscaux / cotisations sociales (intégrité de la paie nationale),
     * fériés nationaux, validation des taux, oauth-config marketing.
     * Réservé au rôle `admin` (+ super_admin).
     */
    case SettingsManage = 'settings.manage';

    /**
     * #7973 — audit des calculs de paie CROSS-TENANT (traces salariales) :
     * finance (litiges de facturation/paie) + ops (débogage calculs).
     * Le support en est volontairement exclu (données de rémunération).
     */
    case PayrollView = 'payroll.view';

    /**
     * Délégation des rôles internes — réservée au rôle `super_admin`
     * (le propriétaire du SaaS reste le seul à distribuer les rôles).
     */
    case TeamManage = 'team.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $permission): string => $permission->value,
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::CompaniesView => 'Consulter les entreprises clientes',
            self::CompaniesManage => 'Paramétrer une entreprise cliente',
            self::CompaniesProvision => 'Provisionner une entreprise (essai, demandes)',
            self::BillingView => 'Consulter la facturation et les abonnements',
            self::BillingManage => 'Modifier la facturation et les abonnements',
            self::PlansView => 'Consulter les offres',
            self::PlansManage => 'Modifier les offres (prix, features, archivage)',
            self::UsersView => 'Consulter les utilisateurs des entreprises',
            self::UsersManage => 'Activer/suspendre un utilisateur d’entreprise',
            self::Impersonate => 'Ouvrir une session d’impersonation',
            self::KillSwitchManage => 'Activer un kill switch de module',
            self::ObservabilityView => 'Consulter l’observabilité (files, notifications)',
            self::MetricsView => 'Consulter les métriques et alertes plateforme',
            self::SupportManage => 'Traiter les tickets de support des pilotes',
            self::AnnouncementsManage => 'Publier des annonces plateforme',
            self::CrmView => 'Consulter le pipeline CRM',
            self::ShowcaseManage => 'Administrer la vitrine publique',
            self::EdgeManage => 'Administrer les nœuds edge',
            self::WebhooksManage => 'Administrer les webhooks sortants plateforme',
            self::SettingsManage => 'Administrer les réglages plateforme (e-mails, IA, barèmes, fériés)',
            self::PayrollView => 'Consulter l’audit des calculs de paie (cross-tenant)',
            self::TeamManage => 'Gérer l’équipe plateforme et distribuer les rôles',
        };
    }
}
