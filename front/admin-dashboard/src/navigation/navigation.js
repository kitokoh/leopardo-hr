/**
 * Source de vérité unique de la navigation du back-office plateforme.
 *
 * Issue #7557 — chaque entrée porte la `permission` plateforme qui la protège
 * (contrat `permissions[]` renvoyé par `/platform/auth/me`, cf. #7553) : le
 * menu latéral, la palette de commandes, la recherche de l'en-tête et les
 * raccourcis clavier filtrent sur cette donnée au lieu de dupliquer des listes
 * qui dérivent (#7554). Une entrée sans permission explicite
 * (`permission: null`) reste visible pour tout compte plateforme — la garde
 * API reste la source de vérité (un écran atteint par URL répond 403).
 *
 * Le `group` désigne la section repliable qui contient l'entrée ; l'état
 * replié/déplié est persisté par la sidebar (`admin.nav.openGroups`).
 */
import {
  HomeIcon,
  ChartBarIcon,
  GlobeAltIcon,
  UsersIcon,
  UserGroupIcon,
  BuildingOfficeIcon,
  CreditCardIcon,
  ChatBubbleLeftRightIcon,
  LifebuoyIcon,
  FunnelIcon,
  ArrowTrendingUpIcon,
  AcademicCapIcon,
  TruckIcon,
  PaperAirplaneIcon,
  BoltIcon,
  FireIcon,
  SparklesIcon,
  PaintBrushIcon,
  LinkIcon,
  ArrowDownTrayIcon,
  MegaphoneIcon,
  TagIcon,
  ClipboardDocumentListIcon,
  CpuChipIcon,
  EnvelopeIcon,
  UserCircleIcon,
  BanknotesIcon,
  ScaleIcon,
  CalculatorIcon,
  CalendarIcon,
  RocketLaunchIcon,
  PresentationChartLineIcon,
  WrenchScrewdriverIcon,
  ServerIcon,
  CogIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'

/**
 * Touches modificatrices des raccourcis clavier. Les raccourcis restent
 * techniques et ne sont jamais traduits (règle #2755) : leur libellé est
 * composé ici (`composeShortcut`) plutôt qu'écrit en clair chez les
 * consommateurs.
 */
export const SHORTCUT_MODIFIERS = {
  /** Modificateur des raccourcis de navigation (Alt+H, Alt+U…). */
  nav: 'Alt',
  /** Modificateur des raccourcis globaux (Ctrl+D…). */
  control: 'Ctrl',
}

/**
 * Compose un libellé de raccourci affichable.
 *
 * @param {string} modifier
 * @param {string} key
 * @returns {string}
 */
export function composeShortcut(modifier, key) {
  return `${modifier}+${key}`
}

/** Raccourci de bascule du thème sombre. */
export const THEME_TOGGLE_SHORTCUT = composeShortcut(SHORTCUT_MODIFIERS.control, 'D')

/** États de rendu d'une entrée de navigation. */
export const NAV_STATE_SHOWN = 'shown'
/** Entrée dont la disponibilité est sondée (flag asynchrone) : la place est réservée. */
export const NAV_STATE_PENDING = 'pending'
/** Entrée non disponible pour le compte courant : jamais rendue. */
export const NAV_STATE_HIDDEN = 'hidden'

/**
 * Sections du menu, dans l'ordre d'affichage. Chaque section est repliable et
 * son état est mémorisé (localStorage `admin.nav.openGroups`).
 */
export const NAV_GROUPS = [
  { id: 'pilotage', titleKey: 'navigation.groups.pilotage' },
  { id: 'entreprise', titleKey: 'navigation.groups.entreprise' },
  { id: 'modules-clients', titleKey: 'navigation.clientModules' },
  { id: 'metier', titleKey: 'navigation.groups.metier' },
  { id: 'parametres', titleKey: 'navigation.groups.parametres' },
  { id: 'systeme', titleKey: 'navigation.groups.systeme' },
]

/** Ancien identifiant de la section des modules clients (avant #7554). */
export const LEGACY_GROUP_IDS = { clientModules: 'modules-clients' }

/**
 * Entrées de navigation. `name` doit correspondre au `name` de la route
 * (`src/router/index.js`) : c'est la clé du surlignage actif.
 *
 * @type {{
 *   name: string,
 *   path: string,
 *   titleKey: string,
 *   icon: object,
 *   permission: string|null,
 *   group: string,
 *   shortcut?: string,
 *   descKey?: string,
 *   badgeKey?: string,
 *   flag?: string,
 * }[]}
 */
export const NAV_ENTRIES = [
  // ── Pilotage ──────────────────────────────────────────────────────────────
  {
    name: 'dashboard',
    path: '/',
    titleKey: 'navigation.dashboard',
    icon: HomeIcon,
    permission: null,
    group: 'pilotage',
    shortcut: 'H',
    descKey: 'adminPalette.itemDashboardDesc',
  },
  {
    name: 'analytics',
    path: '/analytics',
    titleKey: 'navigation.analytics',
    icon: ChartBarIcon,
    permission: 'metrics.view',
    group: 'pilotage',
    descKey: 'adminPalette.itemAnalyticsDesc',
  },
  {
    name: 'globe',
    path: '/globe',
    titleKey: 'navigation.globe',
    icon: GlobeAltIcon,
    permission: null,
    group: 'pilotage',
    descKey: 'adminPalette.itemGlobeDesc',
  },

  // ── Entreprises clientes ──────────────────────────────────────────────────
  {
    name: 'users',
    path: '/users',
    titleKey: 'navigation.users',
    icon: UsersIcon,
    permission: 'users.view',
    group: 'entreprise',
    shortcut: 'U',
    descKey: 'adminPalette.itemUsersDesc',
  },
  {
    name: 'companies',
    path: '/companies',
    titleKey: 'navigation.companies',
    icon: BuildingOfficeIcon,
    permission: 'companies.view',
    group: 'entreprise',
    shortcut: 'C',
    descKey: 'adminPalette.itemCompaniesDesc',
  },
  {
    name: 'subscriptions',
    path: '/subscriptions',
    titleKey: 'navigation.subscriptions',
    icon: CreditCardIcon,
    permission: 'billing.view',
    group: 'entreprise',
    shortcut: 'S',
    descKey: 'adminPalette.itemSubscriptionsDesc',
  },
  {
    name: 'support',
    path: '/support',
    titleKey: 'navigation.support',
    icon: ChatBubbleLeftRightIcon,
    permission: 'support.manage',
    group: 'entreprise',
    descKey: 'adminPalette.itemSupportDesc',
    badgeKey: 'supportTickets',
  },
  {
    name: 'support-tickets',
    path: '/support-tickets',
    titleKey: 'navigation.supportTickets',
    icon: LifebuoyIcon,
    permission: 'support.manage',
    group: 'entreprise',
  },
  {
    name: 'crm-pipeline',
    path: '/crm/pipeline',
    titleKey: 'navigation.crm',
    icon: FunnelIcon,
    permission: 'crm.view',
    group: 'entreprise',
    descKey: 'adminPalette.itemCrmDesc',
  },
  // #7496 — conversions du funnel d'acquisition par étape/jour/source.
  {
    name: 'acquisition-funnel',
    path: '/crm/acquisition-funnel',
    titleKey: 'navigation.acquisitionFunnel',
    icon: ArrowTrendingUpIcon,
    permission: 'metrics.view',
    group: 'entreprise',
  },
  {
    name: 'growth',
    path: '/growth',
    titleKey: 'navigation.growth',
    icon: ArrowTrendingUpIcon,
    permission: 'companies.manage',
    group: 'entreprise',
    descKey: 'adminPalette.itemGrowthDesc',
  },

  // ── Modules des entreprises clientes (#7329) ───────────────────────────────
  {
    name: 'training',
    path: '/training',
    titleKey: 'navigation.training',
    icon: AcademicCapIcon,
    permission: null,
    group: 'modules-clients',
  },
  {
    name: 'fleet',
    path: '/fleet',
    titleKey: 'navigation.fleet',
    icon: TruckIcon,
    permission: null,
    group: 'modules-clients',
    descKey: 'adminPalette.itemFleetDesc',
  },
  // TRAVEL-601 (#6078) : la sonde asynchrone du flag `travelagency` ne doit
  // plus décaler le menu — la sidebar réserve la place de l'entrée
  // (navEntryState → 'pending') au lieu de l'injecter après coup.
  {
    name: 'travel',
    path: '/travel',
    titleKey: 'navigation.travelAgency',
    icon: PaperAirplaneIcon,
    permission: null,
    group: 'modules-clients',
    flag: 'travelagency',
  },
  {
    name: 'fuelStation',
    path: '/fuel-station',
    titleKey: 'navigation.fuelStation',
    icon: BoltIcon,
    permission: null,
    group: 'modules-clients',
  },
  // #7554 — page routée mais inatteignable : le hub `fuelStation`
  // (FuelManagerView) ne proposait aucun lien vers ses opérations.
  {
    name: 'fuel-station-operations',
    path: '/fuel-station/operations',
    titleKey: 'navigation.fuelStationOperations',
    icon: FireIcon,
    permission: null,
    group: 'modules-clients',
  },
  // #7554 — comptabilité : l'ancienne condition d'affichage
  // (`user.role === 'manager' && manager_role ∈ {comptable, principal}`)
  // ne pouvait plus jamais être vraie pour une session plateforme (la garde
  // exige un compte `super_admins`). Les écrans sont désormais rattachés à la
  // permission plateforme qui couvre le périmètre d'une entreprise cliente.
  {
    name: 'accounting-activation',
    path: '/accounting/activation',
    titleKey: 'navigation.accountingActivation',
    icon: RocketLaunchIcon,
    permission: 'companies.view',
    group: 'modules-clients',
  },
  {
    name: 'accounting-dashboard',
    path: '/accounting/dashboard',
    titleKey: 'navigation.accountingDashboard',
    icon: PresentationChartLineIcon,
    permission: 'companies.view',
    group: 'modules-clients',
  },
  {
    name: 'accounting-settings',
    path: '/accounting/settings',
    titleKey: 'navigation.accountingSettings',
    icon: WrenchScrewdriverIcon,
    permission: 'companies.view',
    group: 'modules-clients',
  },

  // ── Métier plateforme ──────────────────────────────────────────────────────
  {
    name: 'chat',
    path: '/chat',
    titleKey: 'navigation.chat',
    icon: SparklesIcon,
    permission: 'companies.manage',
    group: 'metier',
  },
  {
    name: 'showcase-editor',
    path: '/showcase',
    titleKey: 'navigation.showcase',
    icon: PaintBrushIcon,
    permission: 'showcase.manage',
    group: 'metier',
  },
  {
    name: 'webhooks',
    path: '/webhooks',
    titleKey: 'navigation.webhooks',
    icon: LinkIcon,
    permission: 'companies.manage',
    group: 'metier',
  },
  {
    name: 'exports',
    path: '/exports',
    titleKey: 'navigation.exports',
    icon: ArrowDownTrayIcon,
    permission: 'companies.manage',
    group: 'metier',
  },
  {
    name: 'marketing-oauth',
    path: '/marketing/oauth',
    titleKey: 'marketing.oauth.nav_title',
    icon: MegaphoneIcon,
    permission: null,
    group: 'metier',
    descKey: 'adminPalette.itemMarketingDesc',
  },
  // #7554 — page routée mais inatteignable (`/solutions/survey-stats`).
  {
    name: 'solutionSurveyStats',
    path: '/solutions/survey-stats',
    titleKey: 'navigation.surveyStats',
    icon: ClipboardDocumentListIcon,
    permission: null,
    group: 'metier',
  },

  // ── Paramètres ─────────────────────────────────────────────────────────────
  {
    name: 'settings',
    path: '/settings',
    titleKey: 'navigation.account',
    icon: UserCircleIcon,
    permission: null,
    group: 'parametres',
    descKey: 'adminPalette.itemSettingsDesc',
  },
  // #7430 (BC-21 BILLING) — les offres sont paramétrables (#7429) : l'écran
  // « Offres & tarifs » vit sous Paramètres, comme les autres réglages
  // plateforme. Sonde de permission alignée sur `GET /platform/plans`
  // (`platform.permission:plans.view`).
  {
    name: 'settings-plans',
    path: '/settings/plans',
    titleKey: 'plans.nav',
    icon: TagIcon,
    permission: 'plans.view',
    group: 'parametres',
  },
  {
    name: 'settings-email-templates',
    path: '/settings/emails',
    titleKey: 'navigation.emailTemplates',
    icon: EnvelopeIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  {
    name: 'settings-ai-assistant',
    path: '/settings/ai',
    titleKey: 'navigation.aiAssistant',
    icon: CpuChipIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  // #7554 — pages de paramétrage paie routées mais inatteignables : elles
  // rejoignent le groupe Paramètres, protégées par la permission de
  // paramétrage plateforme (même famille que « E-mails » / « Assistant IA »).
  {
    name: 'social-contributions',
    path: '/settings/payroll/social-contributions',
    titleKey: 'navigation.contributions',
    icon: BanknotesIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  {
    name: 'tax-slabs',
    path: '/settings/payroll/tax-slabs',
    titleKey: 'navigation.taxBrackets',
    icon: ScaleIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  {
    name: 'tax-rates',
    path: '/settings/payroll/tax-rates',
    titleKey: 'navigation.legalRates',
    icon: CalculatorIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  {
    name: 'payroll-holidays',
    path: '/settings/payroll/holidays',
    titleKey: 'holidays.nav.title',
    icon: CalendarIcon,
    permission: 'companies.manage',
    group: 'parametres',
  },
  // #7554 — pied de sidebar supprimé : l'identité et la sortie de session
  // vivent désormais dans le menu (groupe Paramètres), plus dans un bloc
  // redondant collé en bas de colonne.
  {
    name: 'logout',
    path: '/logout',
    titleKey: 'navigation.logout',
    icon: ArrowRightOnRectangleIcon,
    permission: null,
    group: 'parametres',
  },

  // ── Système ────────────────────────────────────────────────────────────────
  {
    name: 'edge',
    path: '/edge',
    titleKey: 'navigation.edge',
    icon: ServerIcon,
    permission: 'edge.manage',
    group: 'systeme',
    descKey: 'adminPalette.itemEdgeDesc',
  },
  {
    name: 'system',
    path: '/system',
    titleKey: 'navigation.system',
    icon: CogIcon,
    permission: 'observability.view',
    group: 'systeme',
  },
  {
    name: 'platform-team',
    path: '/team',
    titleKey: 'navigation.team',
    icon: UserGroupIcon,
    permission: 'team.manage',
    group: 'systeme',
  },
]

/**
 * État de rendu d'une entrée pour un compte plateforme donné.
 *
 * @param {object} entry entrée de NAV_ENTRIES
 * @param {{
 *   hasPermission?: (permission: string|null) => boolean,
 *   travelFlagReady?: boolean,
 *   travelFlagActive?: boolean,
 * }} capabilities
 * @returns {'shown'|'pending'|'hidden'}
 */
export function navEntryState(entry, capabilities = {}) {
  const { hasPermission, travelFlagReady = false, travelFlagActive = false } = capabilities

  if (entry.permission && typeof hasPermission === 'function' && !hasPermission(entry.permission)) {
    return NAV_STATE_HIDDEN
  }

  if (entry.flag === 'travelagency') {
    if (!travelFlagReady) return NAV_STATE_PENDING
    return travelFlagActive ? NAV_STATE_SHOWN : NAV_STATE_HIDDEN
  }

  return NAV_STATE_SHOWN
}

/**
 * Entrées réellement disponibles pour le compte courant (palette, recherche,
 * raccourcis). Les entrées en attente de sonde sont exclues.
 *
 * @param {{
 *   hasPermission?: (permission: string|null) => boolean,
 *   travelFlagReady?: boolean,
 *   travelFlagActive?: boolean,
 * }} capabilities
 * @returns {object[]}
 */
export function visibleNavEntries(capabilities = {}) {
  return NAV_ENTRIES.filter((entry) => navEntryState(entry, capabilities) === NAV_STATE_SHOWN)
}

/**
 * Libellé affichable d'un raccourci (ex. « Alt+H »).
 *
 * @param {{ shortcut?: string }} entry
 * @returns {string}
 */
export function shortcutLabel(entry) {
  return entry.shortcut ? composeShortcut(SHORTCUT_MODIFIERS.nav, entry.shortcut) : ''
}

/**
 * Raccourcis clavier des entrées de navigation, dérivés de la même liste pour
 * ne plus dériver de l'implémentation (#3275/#7554).
 *
 * @returns {{ key: string, label: string, path: string, titleKey: string, permission: string|null }[]}
 */
export function navShortcuts() {
  return NAV_ENTRIES.filter((entry) => entry.shortcut).map((entry) => ({
    key: entry.shortcut,
    label: shortcutLabel(entry),
    path: entry.path,
    titleKey: entry.titleKey,
    permission: entry.permission,
  }))
}
