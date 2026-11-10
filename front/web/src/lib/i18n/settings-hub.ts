/**
 * settings-hub.ts — catalogue ×4 et plan du hub « Paramètres » de l'espace
 * tenant (#9330).
 *
 * Contexte : `/settings` était une redirection sèche vers `/settings/account`
 * (`app/(dashboard)/settings/page.tsx` → `redirect()`), et `/settings/team`
 * une autre vers `/employees`. Résultat : la sidebar n'avait **aucune entrée
 * « Paramètres »** et les réglages du tenant étaient éparpillés
 * (`/settings/encaissements`, `/settings/devices`, `/settings/notifications`,
 * `/billing`, `/modules`, `/employees`…) sans point d'entrée unique. Un
 * `principal` qui veut « régler son espace » devait deviner l'URL.
 *
 * Ce module porte :
 *  - `SETTINGS_HUB` : le plan déclaratif des tuiles, avec **la garde exacte**
 *    de la page cible (aucune tuile ne doit mener à un écran « accès réservé ») ;
 *  - `buildSettingsHub()` : la fonction pure qui filtre par rôle — c'est elle
 *    que les tests verrouillent, pas le JSX ;
 *  - les replis FR du catalogue (mécanique `t(locale, clé, repli)` du dépôt,
 *    cf. `team-roles.ts`) : le texte visible n'est jamais inline dans le TSX
 *    (garde CI PA2-I18N-014).
 *
 * Source de vérité des traductions : `shared/i18n/locales/{fr,en,ar,tr}.json`,
 * namespace `settingsHub.*`. Les catalogues consommés par `t()`
 * (`front/web/src/lib/i18n/locales/*.json`) sont régénérés par
 * `shared/i18n/sync/sync-web.js`.
 */
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const SETTINGS_HUB_FR = {
  title: 'Paramètres',
  subtitle: 'Tout le réglage de votre espace, au même endroit.',
  menuLabel: 'Paramètres',
  groupAccount: 'Mon compte',
  groupCompany: 'Entreprise',
  groupPlatform: 'Plateforme',
  tileAccountTitle: 'Mon compte',
  tileAccountBody: 'Profil, e-mail et mot de passe.',
  tileSecurityTitle: 'Sécurité et 2FA',
  tileSecurityBody: 'Double authentification et codes de secours.',
  tileNotificationsTitle: 'Notifications',
  tileNotificationsBody: 'Ce que vous recevez, et sur quel canal.',
  tileDevicesTitle: 'Appareils connectés',
  tileDevicesBody: 'Sessions actives et déconnexion à distance.',
  tileBrandingTitle: 'Image de marque',
  tileBrandingBody: 'Logo, couleurs et nom affiché de votre entreprise.',
  tileTeamTitle: 'Équipe et rôles',
  tileTeamBody: 'Inviter, attribuer les rôles et les accès aux modules.',
  tilePaymentsTitle: 'Encaissements',
  tilePaymentsBody: 'Comptes de paiement, IBAN et mobile money.',
  tileModulesTitle: 'Modules et plan',
  tileModulesBody: 'Activer un module et voir ce qui est inclus.',
  tileIntegrationsTitle: 'Intégrations',
  tileIntegrationsBody: 'Clés API, webhooks et connecteurs.',
  tileBillingTitle: 'Abonnement et factures',
  tileBillingBody: 'Plan en cours, factures et moyen de paiement.',
  tileCompanyTitle: 'Fiche entreprise',
  tileCompanyBody: 'Raison sociale, fuseau horaire et devise.',
  notAdminHint:
    'Certaines tuiles ne sont pas affichées : elles sont réservées au manager principal.',
};

export type SettingsTileKey =
  | 'account'
  | 'security'
  | 'notifications'
  | 'devices'
  | 'company'
  | 'branding'
  | 'team'
  | 'payments'
  | 'modules'
  | 'integrations'
  | 'billing';

export type SettingsGroupKey = 'account' | 'company' | 'platform';

export type SettingsTile = {
  key: SettingsTileKey;
  group: SettingsGroupKey;
  href: string;
  /** Clé i18n du titre (`settingsHub.*`). */
  titleKey: string;
  /** Clé i18n du descriptif (`settingsHub.*`). */
  bodyKey: string;
  /** Repli FR (3ᵉ argument de `t`). */
  titleFallback: string;
  bodyFallback: string;
  /** Garde EXACTE de la page cible. */
  isAllowed: (user: StoredAuthUser | null) => boolean;
};

/** Seul le manager principal gère les encaissements (parité `api.manager:principal`). */
function isPrincipal(user: StoredAuthUser | null): boolean {
  return (user?.manager_role ?? '').toLowerCase() === 'principal';
}

/** L'image de marque est modifiable par `principal|rh` (cf. commentaire de branding/page.tsx). */
function canEditBranding(user: StoredAuthUser | null): boolean {
  const role = (user?.manager_role ?? '').toLowerCase();
  return role === 'principal' || role === 'rh';
}

/** Tout manager porte l'espace Équipe (les mutations fines restent gardées par l'API). */
function isManager(user: StoredAuthUser | null): boolean {
  return (user?.role ?? '').toLowerCase() === 'manager';
}

/**
 * Plan du hub. L'ORDRE est celui d'affichage : ce qui sert le plus souvent
 * d'abord. Une tuile dont `isAllowed` est faux n'est **jamais** rendue — le hub
 * ne propose pas de lien qui aboutirait sur un écran « accès réservé ».
 */
export const SETTINGS_TILES: SettingsTile[] = [
  {
    key: 'account',
    group: 'account',
    href: '/settings/account',
    titleKey: 'settingsHub.tileAccountTitle',
    bodyKey: 'settingsHub.tileAccountBody',
    titleFallback: SETTINGS_HUB_FR.tileAccountTitle,
    bodyFallback: SETTINGS_HUB_FR.tileAccountBody,
    isAllowed: () => true,
  },
  {
    key: 'security',
    group: 'account',
    href: '/settings/security/2fa',
    titleKey: 'settingsHub.tileSecurityTitle',
    bodyKey: 'settingsHub.tileSecurityBody',
    titleFallback: SETTINGS_HUB_FR.tileSecurityTitle,
    bodyFallback: SETTINGS_HUB_FR.tileSecurityBody,
    isAllowed: () => true,
  },
  {
    key: 'notifications',
    group: 'account',
    href: '/settings/notifications',
    titleKey: 'settingsHub.tileNotificationsTitle',
    bodyKey: 'settingsHub.tileNotificationsBody',
    titleFallback: SETTINGS_HUB_FR.tileNotificationsTitle,
    bodyFallback: SETTINGS_HUB_FR.tileNotificationsBody,
    isAllowed: () => true,
  },
  {
    key: 'devices',
    group: 'account',
    href: '/settings/devices',
    titleKey: 'settingsHub.tileDevicesTitle',
    bodyKey: 'settingsHub.tileDevicesBody',
    titleFallback: SETTINGS_HUB_FR.tileDevicesTitle,
    bodyFallback: SETTINGS_HUB_FR.tileDevicesBody,
    isAllowed: () => true,
  },
  {
    key: 'team',
    group: 'company',
    href: '/employees',
    titleKey: 'settingsHub.tileTeamTitle',
    bodyKey: 'settingsHub.tileTeamBody',
    titleFallback: SETTINGS_HUB_FR.tileTeamTitle,
    bodyFallback: SETTINGS_HUB_FR.tileTeamBody,
    isAllowed: isManager,
  },
  {
    key: 'branding',
    group: 'company',
    href: '/settings/branding',
    titleKey: 'settingsHub.tileBrandingTitle',
    bodyKey: 'settingsHub.tileBrandingBody',
    titleFallback: SETTINGS_HUB_FR.tileBrandingTitle,
    bodyFallback: SETTINGS_HUB_FR.tileBrandingBody,
    isAllowed: canEditBranding,
  },
  {
    key: 'payments',
    group: 'company',
    href: '/settings/encaissements',
    titleKey: 'settingsHub.tilePaymentsTitle',
    bodyKey: 'settingsHub.tilePaymentsBody',
    titleFallback: SETTINGS_HUB_FR.tilePaymentsTitle,
    bodyFallback: SETTINGS_HUB_FR.tilePaymentsBody,
    isAllowed: isPrincipal,
  },
  {
    key: 'modules',
    group: 'platform',
    href: '/modules',
    titleKey: 'settingsHub.tileModulesTitle',
    bodyKey: 'settingsHub.tileModulesBody',
    titleFallback: SETTINGS_HUB_FR.tileModulesTitle,
    bodyFallback: SETTINGS_HUB_FR.tileModulesBody,
    isAllowed: () => true,
  },
  {
    key: 'billing',
    group: 'platform',
    href: '/billing',
    titleKey: 'settingsHub.tileBillingTitle',
    bodyKey: 'settingsHub.tileBillingBody',
    titleFallback: SETTINGS_HUB_FR.tileBillingTitle,
    bodyFallback: SETTINGS_HUB_FR.tileBillingBody,
    // `/billing` est `principal`-only côté navigation (#7762) : aligner la
    // tuile évite d'envoyer un manager `dept` sur « module non inclus ».
    isAllowed: isPrincipal,
  },
  {
    key: 'integrations',
    group: 'platform',
    href: '/settings/developer',
    titleKey: 'settingsHub.tileIntegrationsTitle',
    bodyKey: 'settingsHub.tileIntegrationsBody',
    titleFallback: SETTINGS_HUB_FR.tileIntegrationsTitle,
    bodyFallback: SETTINGS_HUB_FR.tileIntegrationsBody,
    // `/settings/developer` est `integrations` = `['principal']` dans la matrice.
    isAllowed: isPrincipal,
  },
];

export type SettingsHubGroup = {
  key: SettingsGroupKey;
  titleKey: string;
  titleFallback: string;
  tiles: SettingsTile[];
};

export const SETTINGS_GROUP_ORDER: SettingsGroupKey[] = ['account', 'company', 'platform'];

const GROUP_TITLES: Record<SettingsGroupKey, { key: string; fallback: string }> = {
  account: { key: 'settingsHub.groupAccount', fallback: SETTINGS_HUB_FR.groupAccount },
  company: { key: 'settingsHub.groupCompany', fallback: SETTINGS_HUB_FR.groupCompany },
  platform: { key: 'settingsHub.groupPlatform', fallback: SETTINGS_HUB_FR.groupPlatform },
};

/**
 * Filtre le plan par rôle et regroupe les tuiles. Fonction PURE : c'est le
 * contrat testé (aucune tuile interdite ne doit fuiter, aucun groupe vide ne
 * doit s'afficher).
 */
export function buildSettingsHub(user: StoredAuthUser | null): SettingsHubGroup[] {
  const allowed = SETTINGS_TILES.filter((tile) => tile.isAllowed(user));
  return SETTINGS_GROUP_ORDER.map((group) => {
    const meta = GROUP_TITLES[group];
    return {
      key: group,
      titleKey: meta.key,
      titleFallback: meta.fallback,
      tiles: allowed.filter((tile) => tile.group === group),
    };
  }).filter((group) => group.tiles.length > 0);
}

/** Combien de tuiles l'utilisateur ne voit pas (message d'aide non anxiogène). */
export function hiddenSettingsTileCount(user: StoredAuthUser | null): number {
  return SETTINGS_TILES.filter((tile) => !tile.isAllowed(user)).length;
}

export function settingsHubT(locale: AppLocale, key: string, fallback: string): string {
  return t(locale, key, fallback);
}
