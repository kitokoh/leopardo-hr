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
 *
 * Issue #7725 — une entrée peut porter `children` (UN niveau de sous-menu,
 * ex. Comptabilité, Stations-service) : le parent est un déclencheur
 * repliable, les enfants sont les liens navigables. La palette, la recherche
 * et les raccourcis consomment la liste APLATIE (`flattenNavEntries`) : seuls
 * les écrans réellement navigables y figurent.
 */
import {
  HomeIcon,
  ChartBarIcon,
  GlobeAltIcon,
  UsersIcon,
  UserGroupIcon,
  BuildingOfficeIcon,
  BuildingStorefrontIcon,
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
  PlayIcon,
  PaintBrushIcon,
  LinkIcon,
  ArrowDownTrayIcon,
  MegaphoneIcon,
  TagIcon,
  ClipboardDocumentListIcon,
  CpuChipIcon,
  EnvelopeIcon,
  BanknotesIcon,
  ScaleIcon,
  ReceiptPercentIcon,
  CalculatorIcon,
  CalendarIcon,
  RocketLaunchIcon,
  PresentationChartLineIcon,
  WrenchScrewdriverIcon,
  ServerIcon,
  CogIcon,
  DocumentTextIcon,
  TableCellsIcon,
  BookOpenIcon,
  CalendarDaysIcon,
  BuildingLibraryIcon,
  ChartPieIcon,
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
 * Sections du menu, dans l'ordre d'affichage (#7725). Chaque section est
 * repliable et son état est mémorisé (localStorage `admin.nav.openGroups`).
 */
export const NAV_GROUPS = [
  { id: 'pilotage', titleKey: 'navigation.groups.pilotage' },
  { id: 'entreprise', titleKey: 'navigation.groups.entreprise' },
  { id: 'modules-clients', titleKey: 'navigation.clientModules' },
  { id: 'facturation-offres', titleKey: 'navigation.groups.facturationOffres' },
  { id: 'rh-paie', titleKey: 'navigation.groups.rhPaie' },
  { id: 'plateforme', titleKey: 'navigation.groups.plateforme' },
  { id: 'systeme', titleKey: 'navigation.groups.systeme' },
]

/**
 * Anciens identifiants de section (persistance `admin.nav.openGroups`) :
 * - `clientModules` → `modules-clients` (avant #7554) ;
 * - `metier` → `plateforme` (avant #7725 : « Métier plateforme ») ;
 * - `referentiels-paie` → `rh-paie` (avant #7855 : « Référentiels paie »).
 */
export const LEGACY_GROUP_IDS = {
  clientModules: 'modules-clients',
  metier: 'plateforme',
  'referentiels-paie': 'rh-paie',
}

/**
 * Entrées de navigation. `name` doit correspondre au `name` de la route
 * (`src/router/index.js`) : c'est la clé du surlignage actif. Un parent de
 * sous-menu (`children`) n'est PAS navigable : il ne porte pas de `path` et
 * son surlignage suit celui de ses enfants.
 *
 * @typedef {{
 *   name: string,
 *   path?: string,
 *   titleKey: string,
 *   menuTitleKey?: string,
 *   icon: object,
 *   permission: string|null,
 *   group: string,
 *   shortcut?: string,
 *   descKey?: string,
 *   badgeKey?: string,
 *   flag?: string,
 *   children?: object[],
 * }} NavEntry
 * @type {NavEntry[]}
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
  // #7496/#7725 — conversions du funnel d'acquisition par étape/jour/source :
  // c'est un écran de PILOTAGE (perm `metrics.view`, comme Analytique), plus
  // une entrée du groupe « Entreprises clientes ».
  {
    name: 'acquisition-funnel',
    path: '/crm/acquisition-funnel',
    titleKey: 'navigation.acquisitionFunnel',
    icon: ArrowTrendingUpIcon,
    permission: 'metrics.view',
    group: 'pilotage',
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
    name: 'subscriptions',
    path: '/subscriptions',
    titleKey: 'navigation.subscriptions',
    icon: CreditCardIcon,
    permission: 'billing.view',
    group: 'entreprise',
    shortcut: 'S',
    descKey: 'adminPalette.itemSubscriptionsDesc',
  },
  // #7725 — fusion des deux entrées Support (« Support » `/support` et
  // « Centre support client » `/support-tickets`, même permission, même
  // groupe) : une seule entrée, l'ancienne route redirige (`router/index.js`).
  {
    name: 'support',
    path: '/support',
    titleKey: 'navigation.support',
    icon: LifebuoyIcon,
    permission: 'support.manage',
    group: 'entreprise',
    descKey: 'adminPalette.itemSupportDesc',
    badgeKey: 'supportTickets',
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
  {
    name: 'growth',
    path: '/growth',
    titleKey: 'navigation.growth',
    icon: RocketLaunchIcon,
    permission: 'companies.manage',
    group: 'entreprise',
    descKey: 'adminPalette.itemGrowthDesc',
  },

  // ── Modules des entreprises clientes (#7329) ───────────────────────────────
  // #7554 — comptabilité : les écrans sont rattachés à la permission
  // plateforme qui couvre le périmètre d'une entreprise cliente.
  // #7725 — UNE entrée « Comptabilité » avec sous-menu (Démarrer / Tableau de
  // bord / Paramétrage) : plus de 3 entrées de premier niveau ni d'écran de
  // settings posé nu sous « Modules clients ».
  {
    name: 'accounting',
    titleKey: 'navigation.accounting',
    icon: CalculatorIcon,
    permission: 'companies.view',
    group: 'modules-clients',
    children: [
      {
        name: 'accounting-activation',
        path: '/accounting/activation',
        titleKey: 'navigation.accountingActivation',
        menuTitleKey: 'navigation.accountingStart',
        icon: PlayIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-dashboard',
        path: '/accounting/dashboard',
        titleKey: 'navigation.accountingDashboard',
        menuTitleKey: 'navigation.accountingOverview',
        icon: PresentationChartLineIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      // #7776 — écrans du rôle comptable (spec 5534) : le cycle mensuel
      // complet (documents → journal → lettrage → clôture → états) sans API.
      {
        name: 'accounting-documents',
        path: '/accounting/documents',
        titleKey: 'navigation.accountingDocuments',
        icon: DocumentTextIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-chart',
        path: '/accounting/chart',
        titleKey: 'navigation.accountingChart',
        icon: TableCellsIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-ledger',
        path: '/accounting/ledger',
        titleKey: 'navigation.accountingLedger',
        icon: BookOpenIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-lettering',
        path: '/accounting/lettering',
        titleKey: 'navigation.accountingLettering',
        icon: LinkIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-fiscal-years',
        path: '/accounting/fiscal-years',
        titleKey: 'navigation.accountingFiscalYears',
        icon: CalendarDaysIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-bank',
        path: '/accounting/bank',
        titleKey: 'navigation.accountingBank',
        icon: BuildingLibraryIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-statements',
        path: '/accounting/statements',
        titleKey: 'navigation.accountingStatements',
        icon: ChartPieIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
      {
        name: 'accounting-settings',
        path: '/accounting/settings',
        titleKey: 'navigation.accountingSettings',
        menuTitleKey: 'navigation.accountingConfig',
        icon: WrenchScrewdriverIcon,
        permission: 'companies.view',
        group: 'modules-clients',
      },
    ],
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
  // #7725 — UNE entrée « Stations-service » avec sous-menu (Hub / Opérations) :
  // le hub et ses opérations (#7554) ne sont plus deux entrées de premier
  // niveau. Noms de routes normalisés kebab-case (`fuel-station`).
  {
    name: 'fuel-station-menu',
    titleKey: 'navigation.fuelStation',
    icon: BoltIcon,
    permission: null,
    group: 'modules-clients',
    children: [
      {
        name: 'fuel-station',
        path: '/fuel-station',
        titleKey: 'navigation.fuelStation',
        menuTitleKey: 'navigation.fuelStationHub',
        icon: BuildingStorefrontIcon,
        permission: null,
        group: 'modules-clients',
      },
      {
        name: 'fuel-station-operations',
        path: '/fuel-station/operations',
        titleKey: 'navigation.fuelStationOperations',
        menuTitleKey: 'navigation.fuelStationOps',
        icon: FireIcon,
        permission: null,
        group: 'modules-clients',
      },
    ],
  },

  // ── Facturation & offres (#7725) ───────────────────────────────────────────
  // #7430 (BC-21 BILLING) — les offres sont paramétrables (#7429). Sonde de
  // permission alignée sur `GET /platform/plans`
  // (`platform.permission:plans.view`). (· Passerelles de paiement rejoindront
  // ce groupe quand l'issue PSP sera livrée.)
  {
    name: 'settings-plans',
    path: '/settings/plans',
    titleKey: 'plans.nav',
    icon: TagIcon,
    permission: 'plans.view',
    group: 'facturation-offres',
  },

  // ── RH & Paie (#7855, ex-« Référentiels paie » #7725) ─────────────────────
  // #7554 — pages de paramétrage paie routées mais inatteignables : elles ont
  // rejoint le menu ; #7725 leur donne un groupe métier DISTINCT ; #7855
  // regroupe le para-RH éparpillé (référentiels paie + Formations) sous
  // « RH & Paie », avec les 4 référentiels en sous-menu d'UNE entrée
  // « Référentiels paie » (modèle Comptabilité, un seul niveau, règle #7725).
  // AUCUNE route ne change.
  // #7973 — les API /admin/* de ces écrans (barèmes, cotisations, taux,
  // fériés) exigent désormais `settings.manage` : le menu suit la même sonde.
  {
    name: 'payroll-references',
    titleKey: 'navigation.groups.referentielsPaie',
    icon: BanknotesIcon,
    permission: 'settings.manage',
    group: 'rh-paie',
    children: [
      {
        name: 'social-contributions',
        path: '/settings/payroll/social-contributions',
        titleKey: 'navigation.contributions',
        icon: BanknotesIcon,
        permission: 'settings.manage',
        group: 'rh-paie',
      },
      {
        name: 'tax-slabs',
        path: '/settings/payroll/tax-slabs',
        titleKey: 'navigation.taxBrackets',
        icon: ScaleIcon,
        permission: 'settings.manage',
        group: 'rh-paie',
      },
      {
        name: 'tax-rates',
        path: '/settings/payroll/tax-rates',
        titleKey: 'navigation.legalRates',
        icon: ReceiptPercentIcon,
        permission: 'settings.manage',
        group: 'rh-paie',
      },
      {
        name: 'payroll-holidays',
        path: '/settings/payroll/holidays',
        titleKey: 'holidays.nav.title',
        icon: CalendarIcon,
        permission: 'settings.manage',
        group: 'rh-paie',
      },
    ],
  },
  // #7855 — « Formations » quitte « Modules clients » pour « RH & Paie »
  // (même route `/training`, même permission).
  {
    name: 'training',
    path: '/training',
    titleKey: 'navigation.training',
    icon: AcademicCapIcon,
    permission: null,
    group: 'rh-paie',
  },

  // ── Plateforme (#7725, ex-« Métier plateforme » + config plateforme) ──────
  {
    name: 'showcase-editor',
    path: '/showcase',
    titleKey: 'navigation.showcase',
    icon: PaintBrushIcon,
    permission: 'showcase.manage',
    group: 'plateforme',
  },
  // #7726 — « Passerelles de paiement » : configuration Stripe/Chargily de la
  // plateforme (clés chiffrées BDD, fallback env). Même groupe que les offres ;
  // permission alignée sur `GET /platform/billing/gateways` (billing.manage).
  {
    name: 'settings-payment-gateways',
    path: '/settings/payment-gateways',
    titleKey: 'paymentGateways.nav',
    icon: CreditCardIcon,
    permission: 'billing.manage',
    group: 'parametres',
  },
  {
    name: 'settings-email-templates',
    path: '/settings/emails',
    titleKey: 'navigation.emailTemplates',
    icon: EnvelopeIcon,
    permission: 'settings.manage',
    group: 'plateforme',
  },
  {
    name: 'settings-ai-assistant',
    path: '/settings/ai',
    titleKey: 'navigation.aiAssistant',
    icon: CpuChipIcon,
    permission: 'settings.manage',
    group: 'plateforme',
  },
  {
    name: 'chat',
    path: '/chat',
    titleKey: 'navigation.chat',
    icon: ChatBubbleLeftRightIcon,
    permission: 'companies.manage',
    group: 'plateforme',
  },
  {
    name: 'webhooks',
    path: '/webhooks',
    titleKey: 'navigation.webhooks',
    icon: LinkIcon,
    permission: 'webhooks.manage',
    group: 'plateforme',
  },
  {
    name: 'exports',
    path: '/exports',
    titleKey: 'navigation.exports',
    icon: ArrowDownTrayIcon,
    permission: 'companies.manage',
    group: 'plateforme',
  },
  {
    name: 'marketing-oauth',
    path: '/marketing/oauth',
    titleKey: 'marketing.oauth.nav_title',
    icon: MegaphoneIcon,
    // #7973 — l'API /admin/platform/marketing/oauth-config exige settings.manage.
    permission: 'settings.manage',
    group: 'plateforme',
    descKey: 'adminPalette.itemMarketingDesc',
  },
  // BC-29 COMMUNICATION — R6 (#7691) : boîte mail connectée + IA (fils
  // classés, file de confirmations, réglages). La garde réelle est côté API
  // (feature flag tenant `communication`) : un compte sans accès voit
  // l'état verrouillé explicite de l'écran.
  {
    name: 'communication',
    path: '/communication',
    titleKey: 'communicationApp.moduleTitle',
    icon: EnvelopeIcon,
    permission: null,
    group: 'plateforme',
  },
  // #7554 — page routée mais inatteignable (`/solutions/survey-stats`).
  // #7725 — nom de route normalisé kebab-case (`solution-survey-stats`).
  {
    name: 'solution-survey-stats',
    path: '/solutions/survey-stats',
    titleKey: 'navigation.surveyStats',
    icon: ClipboardDocumentListIcon,
    permission: null,
    group: 'plateforme',
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

  // #7725 — « Mon compte » (`/settings`) et « Déconnexion » (`/logout`) ne
  // sont plus des entrées de la sidebar : ils vivent dans le menu utilisateur
  // du header (`components/layout/Header.vue`).
]

/**
 * Liste APLATIE des entrées NAVIGABLES : les parents de sous-menu (sans
 * `path`) sont remplacés par leurs enfants. C'est la liste que consomment la
 * palette de commandes, la recherche de l'en-tête et les raccourcis (#7725).
 *
 * @returns {object[]}
 */
export function flattenNavEntries(entries = NAV_ENTRIES) {
  const flat = []
  for (const entry of entries) {
    if (Array.isArray(entry.children) && entry.children.length > 0) {
      flat.push(...entry.children)
      continue
    }
    flat.push(entry)
  }
  return flat
}

/**
 * État de rendu d'une entrée pour un compte plateforme donné. Un parent de
 * sous-menu est rendu si AU MOINS UN de ses enfants l'est (et masqué sinon).
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

  if (Array.isArray(entry.children) && entry.children.length > 0) {
    const childStates = entry.children.map((child) => navEntryState(child, capabilities))
    if (childStates.some((state) => state === NAV_STATE_SHOWN)) return NAV_STATE_SHOWN
    if (childStates.some((state) => state === NAV_STATE_PENDING)) return NAV_STATE_PENDING
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
 * raccourcis). Les entrées en attente de sonde sont exclues, et les parents
 * de sous-menu sont aplatis en leurs écrans navigables.
 *
 * @param {{
 *   hasPermission?: (permission: string|null) => boolean,
 *   travelFlagReady?: boolean,
 *   travelFlagActive?: boolean,
 * }} capabilities
 * @returns {object[]}
 */
export function visibleNavEntries(capabilities = {}) {
  return flattenNavEntries().filter((entry) => navEntryState(entry, capabilities) === NAV_STATE_SHOWN)
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
  return flattenNavEntries().filter((entry) => entry.shortcut).map((entry) => ({
    key: entry.shortcut,
    label: shortcutLabel(entry),
    path: entry.path,
    titleKey: entry.titleKey,
    permission: entry.permission,
  }))
}
