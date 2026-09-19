import type { LucideIcon } from 'lucide-react';
import {
  BarChart3,
  Building2,
  Calculator,
  CalendarX,
  ChefHat,
  Clock,
  Contact,
  CreditCard,
  FileText,
  Fuel,
  GraduationCap,
  Handshake,
  LayoutDashboard,
  MapPin,
  Megaphone,
  Plane,
  Plug,
  School,
  Store,
  Ticket,
  Truck,
  Users,
  UtensilsCrossed,
  Video,
  Wallet,
} from 'lucide-react';
import type { StoredAuthUser } from '@/lib/i18n';
export type ClientModuleKey =
  | 'dashboard'
  | 'employees'
  | 'attendance'
  | 'attendance_geo'
  | 'absences'
  | 'contracts'
  | 'payroll'
  | 'training'
  | 'reports'
  | 'partner'
  | 'billing'
  | 'integrations'
  | 'marketing'
  | 'accounting'
  | 'crm'
  | 'restaurant'
  | 'restaurant_kitchen'
  | 'edu_manager'
  | 'travel'
  | 'travel_portal'
  | 'fuel'
  | 'fleet'
  | 'cameras'
  | 'showcase'
  | 'commerce';
export type FeatureState = 'available' | 'trial' | 'locked';

/**
 * Métiers (verticales) portés par la plateforme. Chaque verticale est un
 * ensemble de modules métier activés par un feature flag tenant.
 * (#7225 — audit 2026-09-10 : le menu listait « Restaurant » à une agence de
 * voyage car les modules métier étaient rangés dans les groupes transverses.)
 */
export type BusinessVertical = 'restaurant' | 'travel' | 'education' | 'fuel' | 'commerce';

/**
 * Portée d'un module :
 * - `core`     : transverse, utile à toute entreprise (RH, paie, rapports…)
 * - `business` : métier — n'a de sens que pour la verticale du tenant.
 */
export type ClientModuleScope = 'core' | 'business';

/**
 * #7724 — groupes VISUELS de la barre de navigation. Le champ `group` du
 * catalogue est désormais la SEULE source du groupement (l'ancienne liste
 * `HR_SUBMENU_KEYS` de `dashboard-nav.ts` est dérivée du catalogue) :
 * - `general`    : liens directs (Tableau de bord, Rapports) ;
 * - `hr`         : sous-menu « RH » (Employés … Paie) ;
 * - `finance`    : sous-menu « Finance » (Comptabilité) ;
 * - `growth`     : sous-menu « Clients & croissance » (CRM, Marketing…) ;
 * - `operations` : sous-menu « Opérations » (Flotte, Caméras) ;
 * - `platform`   : hors barre (panneau « Modules & plan »), inchangé.
 */
export type ClientModuleGroup = 'general' | 'hr' | 'finance' | 'growth' | 'operations' | 'platform';

/**
 * #7235 — Outils d'ÉQUIPE : sans objet pour un profil `solo` (indépendant).
 * La règle est aussi posée côté serveur (`Company::TEAM_TOOLS`, appliquée au
 * provisioning) ; on la rejoue ici pour qu'une session ancienne ou un payload
 * partiel ne fasse jamais réapparaître la gestion d'employés ou de contrats
 * chez un indépendant.
 *
 * #7423 — seuls les outils de **pilotage d'équipe** restent fermés : le socle
 * RH (`SOLO_FLOOR_MODULE_KEYS`) est garanti, cf. ci-dessous.
 */
const SOLO_HIDDEN_MODULE_KEYS: ClientModuleKey[] = [
  'employees',
  'contracts',
  'training',
  'attendance_geo',
];

/**
 * #7423 — PLANCHER D'ACCÈS du profil `solo` : le socle RH qu'un indépendant
 * garde **quel que soit son profil et sa sélection** (il travaille aussi : il
 * se pointe, pose ses congés, reçoit ses bulletins).
 *
 * Miroir exact de `Company::SOLO_FLOOR_TOOLS` (source de vérité serveur) et
 * appliqué AVANT la branche « la sélection fait autorité » : le plancher est un
 * MINIMUM, pas un défaut — une sélection qui ne le couvre pas ne peut pas le
 * retirer.
 */
const SOLO_FLOOR_MODULE_KEYS: ClientModuleKey[] = [
  'attendance',
  'absences',
  'payroll',
];

export type ClientModule = {
  key: ClientModuleKey;
  href?: string;
  /**
   * Libellé de COMPATIBILITÉ (donnée, pas une string d'UI) : la source unique
   * des libellés affichés est l'i18n (`dashboard.modules`, 4 locales). Ce champ
   * ne sert que de repli technique (#7724).
   */
  label: string;
  group: ClientModuleGroup;
  /** #7724 — icône du module (pills de la barre et cartes du rail métier). */
  icon?: LucideIcon;
  /**
   * #7724 — hiérarchie du rail « Mon métier » : un sous-écran métier
   * (Cuisine, Portail voyageur) est rattaché à sa carte parente au lieu
   * d'être promu au premier niveau.
   */
  parentKey?: ClientModuleKey;
  capabilityKeys: string[];
  featureKeys: string[];
  allowedRoles: string[];
  upgradeLabel: string;
  /** Défaut : `core` (transverse). */
  scope?: ClientModuleScope;
  /** Renseigné pour les modules `business` : verticale de rattachement. */
  vertical?: BusinessVertical;
};
export type ClientModuleAccess = ClientModule & {
  state: FeatureState;
  enabled: boolean;
  reason: 'available' | 'trial' | 'feature_locked' | 'role_locked';
};
export const CLIENT_MODULES: ClientModule[] = [
  // #7724 — le catalogue est ORDONNÉ comme la barre : liens directs
  // (Tableau de bord, Rapports), puis les groupes visuels dérivés du champ
  // `group` (RH, Finance, Clients & croissance, Opérations), puis la
  // plateforme (panneau « Modules & plan ») et enfin les verticales métier
  // (rail « Mon métier », hiérarchisé par `parentKey`).
  {
    key: 'dashboard',
    href: '/dashboard',
    label: 'Tableau de bord',
    group: 'general',
    icon: LayoutDashboard,
    capabilityKeys: ['can_view_dashboard', 'dashboard'],
    featureKeys: ['dashboard', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Tableau de bord',
  },
  {
    key: 'reports',
    href: '/reports',
    label: 'Rapports',
    // #7724 — « Rapports » accompagne le tableau de bord en lien direct
    // (pilotage transverse), il ne vit plus dans l'espace finance.
    group: 'general',
    icon: BarChart3,
    capabilityKeys: ['reports', 'can_view_reports'],
    featureKeys: ['reports', 'analytics'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Rapports avancés',
  },

  // ── RH ▾ (sous-menu dérivé de `group: 'hr'`) ──────────────────────────────
  {
    key: 'employees',
    href: '/employees',
    label: 'Employés',
    group: 'hr',
    icon: Users,
    capabilityKeys: ['employees', 'can_view_employees', 'can_create_employees'],
    featureKeys: ['employees', 'employee_management', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Gestion des employés',
  },
  {
    key: 'attendance',
    href: '/attendance',
    label: 'Pointages',
    group: 'hr',
    icon: Clock,
    capabilityKeys: ['attendance', 'can_view_attendance'],
    featureKeys: ['attendance', 'time_tracking', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Pointage et présence',
  },
  {
    key: 'attendance_geo',
    href: '/attendance/geo',
    // #7724 — plus de franglais « Attendance — Sessions GPS » : le label de
    // compat s'aligne sur l'i18n (`dashboard.modules.attendance_geo`).
    label: 'Sessions GPS',
    group: 'hr',
    icon: MapPin,
    capabilityKeys: ['smart_attendance', 'can_view_smart_attendance'],
    featureKeys: ['smart_attendance', 'geo_attendance', 'attendance', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Smart Attendance — Présence géolocalisée',
  },
  {
    key: 'absences',
    href: '/absences',
    label: 'Absences',
    group: 'hr',
    icon: CalendarX,
    capabilityKeys: ['absences', 'can_view_absences'],
    featureKeys: ['absences', 'leave_management', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Absences et congés',
  },
  {
    key: 'contracts',
    href: '/contracts',
    label: 'Contrats',
    group: 'hr',
    icon: FileText,
    capabilityKeys: ['contracts', 'can_view_contracts'],
    featureKeys: ['contracts', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Contrats RH',
  },
  {
    key: 'training',
    href: '/training',
    label: 'Formations',
    group: 'hr',
    icon: GraduationCap,
    capabilityKeys: ['training', 'can_view_training'],
    featureKeys: ['training'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Formation',
  },
  {
    key: 'payroll',
    href: '/payroll',
    // #7724 — la Paie rejoint le groupe RH : elle n'est plus une pill de
    // premier niveau éparpillée alors qu'Absences/Contrats/Formations sont
    // sous le sous-menu « RH ». Aucun changement de route ni de gating.
    label: 'Paie',
    group: 'hr',
    icon: Wallet,
    capabilityKeys: ['payroll', 'can_view_payroll', 'can_manage_payroll'],
    featureKeys: ['payroll', 'pay_slips'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Paie et bulletins',
  },

  // ── Finance ▾ ─────────────────────────────────────────────────────────────
  // #5626 — Module Comptabilité (backend #5288/#5422 livré, front/web manquait
  // d'une entrée sidebar). Rôles : comptable et principal uniquement.
  {
    key: 'accounting',
    href: '/accounting',
    label: 'Comptabilité',
    group: 'finance',
    icon: Calculator,
    capabilityKeys: ['accounting', 'can_view_accounting', 'can_manage_accounting'],
    featureKeys: ['accounting', 'accounting_module'],
    allowedRoles: ['manager'],
    upgradeLabel: 'Module Comptabilité',
  },

  // ── Clients & croissance ▾ ────────────────────────────────────────────────
  // #5715 — CRM Client (tenant-scoped, ADR-CRM-DUAL-CONTEXTS). Le CRM
  // commercial Leopardo reste dans l'admin plateforme : cette entrée est
  // l'espace client du tenant, porté par la feature flag `crm`.
  {
    key: 'crm',
    href: '/crm',
    label: 'CRM Client',
    group: 'growth',
    icon: Contact,
    capabilityKeys: ['crm', 'can_view_crm'],
    featureKeys: ['crm'],
    allowedRoles: ['manager'],
    upgradeLabel: 'CRM Client',
  },
  {
    key: 'marketing',
    href: '/social-marketing',
    label: 'Marketing',
    group: 'growth',
    icon: Megaphone,
    capabilityKeys: ['marketing', 'can_view_marketing'],
    featureKeys: ['marketing', 'social_marketing'],
    allowedRoles: ['manager'],
    upgradeLabel: 'Marketing & réseaux sociaux',
  },
  // BC-27 SHOWCASE (#6862) — module HORIZONTAL « Site vitrine » : le
  // responsable du tenant crée, édite et publie le site public de son
  // entreprise en 1 clic (page `/showcase`). Deux clés de résolution : la
  // sélection d'inscription (`company.modules.showcase`) ET le feature flag
  // tenant (`company_showcase`) — l'ordre de `featureKeys` fait autorité pour
  // la sélection explicite (même sémantique que #7235).
  {
    key: 'showcase',
    href: '/showcase',
    label: 'Site vitrine',
    group: 'growth',
    icon: Building2,
    capabilityKeys: ['company_showcase', 'showcase', 'can_view_showcase', 'can_manage_showcase'],
    featureKeys: ['showcase', 'company_showcase'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Site vitrine public de l\'entreprise',
  },
  {
    key: 'partner',
    href: '/partner',
    label: 'Programme Partenaire',
    group: 'growth',
    icon: Handshake,
    capabilityKeys: ['is_partner'],
    featureKeys: ['growth_module'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Programme Partenaire',
  },

  // ── Opérations ▾ ──────────────────────────────────────────────────────────
  // #7400 — Flotte & suivi des véhicules de service. Module HORIZONTAL
  // (`scope: 'core'`) : toute PME de terrain a des véhicules. L'API est
  // réservée aux managers (`api.manager`, sécurité #2217) : la capacité
  // `can_view_fleet` rejoue ce gate, et la feature `fleet` permettra de
  // vendre/activer le module par plan (voir #7400).
  {
    key: 'fleet',
    href: '/fleet',
    label: 'Flotte',
    group: 'operations',
    icon: Truck,
    capabilityKeys: ['can_view_fleet', 'fleet'],
    featureKeys: ['fleet'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Flotte (véhicules, positions, itinéraires)',
    scope: 'core',
  },
  // BC-19 DEVICE (#7425) — module « Caméras » : porté par le seul flag tenant
  // `cameras` (`module.cameras` renvoie 403 FEATURE_NOT_ENABLED sinon) et
  // réservé au responsable du tenant (`api.manager:principal,rh`).
  {
    key: 'cameras',
    href: '/cameras',
    label: 'Caméras',
    group: 'operations',
    icon: Video,
    capabilityKeys: ['cameras', 'can_view_cameras'],
    featureKeys: ['cameras'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Surveillance caméras (mur, permissions, partage tiers)',
  },

  // ── Plateforme (panneau « Modules & plan », hors barre) ───────────────────
  {
    key: 'billing',
    href: '/billing',
    label: 'Facturation',
    group: 'platform',
    icon: CreditCard,
    capabilityKeys: ['billing', 'can_manage_billing'],
    featureKeys: ['billing'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Facturation',
  },
  {
    key: 'integrations',
    href: '/settings/developer',
    label: 'Intégrations',
    group: 'platform',
    icon: Plug,
    capabilityKeys: ['integrations', 'can_manage_integrations'],
    featureKeys: ['integrations', 'api_access', 'webhooks'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Intégrations',
  },

  // ── Verticales métier (rail « Mon métier ») ───────────────────────────────
  // #7724 — `restaurant` reprend sa place dans l'ordre du catalogue (il était
  // déclaré AVANT `dashboard`, anomalie d'ordre).
  {
    key: 'restaurant',
    href: '/restaurant',
    label: 'Restaurant',
    group: 'general',
    icon: UtensilsCrossed,
    capabilityKeys: ['restaurant', 'restaurant.kitchen', 'restaurantmanager', 'can_view_restaurant'],
    featureKeys: ['restaurantmanager', 'restaurant'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Restaurant Manager',
    scope: 'business',
    vertical: 'restaurant',
  },
  {
    key: 'restaurant_kitchen',
    href: '/restaurant/kitchen',
    // #7724 — label dédupliqué (il dupliquait « Restaurant ») et sous-écran
    // rattaché à sa carte parente dans le rail métier (`parentKey`).
    label: 'Cuisine',
    group: 'general',
    icon: ChefHat,
    parentKey: 'restaurant',
    capabilityKeys: ['restaurant', 'restaurant.kitchen'],
    featureKeys: ['restaurantmanager'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Restaurant',
    scope: 'business',
    vertical: 'restaurant',
  },
  // #7225 — verticale Agence de voyage (BC-13/TRAVEL). Feature flag tenant
  // `travelagency` (TravelAgencyManifest::code(), ActivateTravelAgencyAction).
  // BC-24 (#7633) — l'entrée pointe sur le hub GÉRANT `/travel` ; le portail
  // voyageur est une sous-entrée dédiée `travel_portal`.
  {
    key: 'travel',
    href: '/travel',
    label: 'Agence de voyage',
    group: 'general',
    icon: Plane,
    capabilityKeys: ['travelagency', 'travel', 'can_view_travel', 'can_manage_travel'],
    featureKeys: ['travelagency', 'travel_agency'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Agence de voyage (ventes, réservations, check-in)',
    scope: 'business',
    vertical: 'travel',
  },
  {
    key: 'travel_portal',
    href: '/travel/portal',
    label: 'Portail voyageur',
    group: 'general',
    icon: Ticket,
    // #7724 — sous-écran hiérarchisé sous la carte « Agence de voyage ».
    parentKey: 'travel',
    capabilityKeys: ['travelagency', 'travel', 'can_view_travel', 'can_manage_travel'],
    featureKeys: ['travelagency', 'travel_agency'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Portail voyageur (recherche de trajets, réservation)',
    scope: 'business',
    vertical: 'travel',
  },
  // BC-17 RETAIL (#7675) — verticale Commerce (vente au détail). Le backend
  // est complet (#7672/#7673/#7674 : produits/catégories, stock, POS sous
  // `/v1/retail/*`, middleware `module.retail`) ; cette entrée expose
  // l'espace vendeur `/commerce` (hub + produits, stock, caisse). Même
  // pattern que `travel` (BC-24 #7633) : feature flag tenant `retail`.
  {
    key: 'commerce',
    href: '/commerce',
    label: 'Commerce',
    group: 'general',
    icon: Store,
    capabilityKeys: ['retail', 'can_view_retail', 'can_manage_retail'],
    featureKeys: ['retail'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Commerce (produits, stock, caisse)',
    scope: 'business',
    vertical: 'commerce',
  },
  // #7225 — verticale Station-service (BC-15 FUEL).
  {
    key: 'fuel',
    href: '/fuel/pump',
    label: 'Station-service',
    group: 'general',
    icon: Fuel,
    capabilityKeys: ['fuel_station', 'fuel', 'can_view_fuel'],
    featureKeys: ['fuel_station', 'fuel'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Station-service (pompes, volumes, écarts)',
    scope: 'business',
    vertical: 'fuel',
  },
  // BC-16 EDU — EduManager (EDU-011/012/013, #5827/#5828/#5829). Navigation
  // rôle-aware, portée par la feature flag `edumanager` (activation tenant
  // #5817). #7724 — c'est une VERTICALE métier (`vertical: 'education'`),
  // plus jamais classée `group: 'hr'` ; label aligné sur l'i18n.
  {
    key: 'edu_manager',
    href: '/edu-manager',
    label: 'Scolarité',
    group: 'general',
    icon: School,
    capabilityKeys: ['edumanager', 'can_view_edumanager'],
    featureKeys: ['edumanager'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'EduManager',
    scope: 'business',
    vertical: 'education',
  },
];

/**
 * #7322 — Outils HORIZONTAUX que le client (responsable du tenant) peut
 * s'auto-activer depuis le panneau « Modules & plan ». Miroir strict de
 * `Company::HORIZONTAL_TOOLS` côté API (allowlist fail-closed) : une clé
 * inconnue y répond 422. Les VERTICALES (restaurant, travel, fuel, éducation)
 * en sont exclues — elles requièrent des seeders/dépendances de pack et
 * passent par l'admin plateforme.
 */
export const SELF_ACTIVATABLE_MODULE_KEYS: ClientModuleKey[] = [
  'employees',
  'attendance',
  'absences',
  'contracts',
  'payroll',
  'training',
  'reports',
  'accounting',
  'crm',
  'marketing',
  'showcase',
  // BC-19 DEVICE (#7476) — miroir strict de `Company::HORIZONTAL_TOOLS` : le
  // module Caméras (déjà dans le catalogue, #7425) devient auto-activable par
  // le client. La parité des deux listes est verrouillée par
  // `__tests__/client-features-self-activation.test.ts`.
  'cameras',
];

export function isSelfActivable(module: Pick<ClientModule, 'key'>): boolean {
  return SELF_ACTIVATABLE_MODULE_KEYS.includes(module.key);
}

/**
 * #7724 — sous-routes et alias historiques qui résolvent vers un module.
 * Les routes PRINCIPALES sont dérivées des `href` du catalogue : plus de
 * double déclaration (`/social` et `/social-marketing` étaient déclarées
 * séparément alors que `/social` n'est qu'un alias historique).
 */
const MODULE_ROUTE_ALIASES: Record<string, ClientModuleKey> = {
  // Alias historique du module marketing.
  '/social': 'marketing',
  '/crm/accounts': 'crm',
  '/crm/contacts': 'crm',
  '/crm/leads': 'crm',
  '/crm/pipeline': 'crm',
  '/restaurant/pos': 'restaurant',
  // BC-24 (#7633) — sous-routes de l'espace gérant travel (hub + pages A2–A5).
  '/travel/network': 'travel',
  '/travel/trips': 'travel',
  '/travel/bookings': 'travel',
  '/travel/reports': 'travel',
  '/travel/portal': 'travel_portal',
  // BC-17 (#7675) — espace vendeur Commerce (hub + sous-pages).
  '/commerce': 'commerce',
  '/commerce/products': 'commerce',
  '/commerce/stock': 'commerce',
  '/commerce/pos': 'commerce',
  '/fuel': 'fuel',
  '/edu-manager/campuses': 'edu_manager',
  '/edu-manager/academic-years': 'edu_manager',
  '/edu-manager/subjects': 'edu_manager',
  '/edu-manager/classes': 'edu_manager',
  '/edu-manager/students': 'edu_manager',
  '/edu-manager/admissions': 'edu_manager',
  '/edu-manager/assessments': 'edu_manager',
  '/edu-manager/report-cards': 'edu_manager',
  '/edu-manager/teacher': 'edu_manager',
  // BC-19 (#7425) — les sous-routes de détail (`/cameras/{id}`) sont résolues
  // par le match de préfixe de `getModuleAccessForPath`.
};

/**
 * #7724 — table route → module DÉRIVÉE du catalogue (une seule source de
 * vérité), complétée par les alias/sous-routes. L'ordre d'itération préserve
 * la sémantique du match de préfixe (`getModuleAccessForPath`) : les routes
 * principales (catalogue) passent avant les alias.
 */
const ROUTE_TO_MODULE: Record<string, ClientModuleKey> = {
  ...Object.fromEntries(
    dedupeModulesByKey(CLIENT_MODULES)
      .filter((module): module is ClientModule & { href: string } => !!module.href)
      .map((module) => [module.href, module.key]),
  ),
  ...MODULE_ROUTE_ALIASES,
};
function normalizedRole(user?: StoredAuthUser | null): string {
  if (!user?.role) {
    return 'guest';
  }
  return user.role.toLowerCase();
}
function hasRoleAccess(module: ClientModule, user?: StoredAuthUser | null): boolean {
  const role = normalizedRole(user);
  if (role === 'manager') {
    const managerRole = (user?.manager_role ?? '').toLowerCase();
    if (module.key === 'billing' || module.key === 'integrations') {
      return managerRole === 'principal';
    }
    if (module.key === 'marketing') {
      return ['principal', 'marketing'].includes(managerRole);
    }
    if (module.key === 'crm') {
      return ['principal', 'rh'].includes(managerRole);
    }
    if (module.key === 'showcase') {
      // BC-27 : l'API réserve la gestion de la vitrine au responsable du
      // tenant (`api.manager:principal,rh`). On rejoue la même règle côté
      // navigation pour ne jamais exposer un module qui répondrait 403.
      return ['principal', 'rh'].includes(managerRole);
    }
    if (module.key === 'edu_manager') {
      // Direction scolaire : principal/rh ou manager sans sous-rôle (propriétaire).
      return managerRole === '' || managerRole === 'principal' || managerRole === 'rh';
    }
    if (module.key === 'cameras') {
      // BC-19 (#7425) : l'API réserve tout `/cameras` au responsable du tenant
      // (`api.manager:principal,rh`) — même miroir que `showcase`.
      return ['principal', 'rh'].includes(managerRole);
    }
    return true;
  }
  return module.allowedRoles.includes(role);
}
function valueFor(keys: string[], values?: Record<string, unknown> | null): unknown {
  if (!values) {
    return undefined;
  }
  // Payload sous forme de liste de clés (ex. features: ['rh', 'finance']) —
  // la présence de la clé vaut « activé » (#3379).
  if (Array.isArray(values)) {
    return keys.some((key) => values.includes(key)) ? true : undefined;
  }
  const matchedKey = keys.find((key) => Object.prototype.hasOwnProperty.call(values, key));
  return matchedKey ? values[matchedKey] : undefined;
}
function stateFromValue(value: unknown): FeatureState | null {
  if (value === undefined) {
    return null;
  }
  if (value === true || value === 'enabled' || value === 'available') {
    return 'available';
  }
  if (value === 'trial') {
    return 'trial';
  }
  return 'locked';
}
/**
 * #7235 — Valeur d'un module dans la sélection explicite de l'inscription.
 * On cherche la première `featureKey` déclarée dans `company.modules`
 * (l'ordre de `featureKeys` porte la sémantique déjà utilisée par le reste du
 * fichier) ; clé absente ⇒ `undefined` (la résolution continue normalement).
 */
function explicitToolValue(module: ClientModule, user?: StoredAuthUser | null): unknown {
  const selection = user?.company?.modules;
  if (!selection || typeof selection !== 'object' || Array.isArray(selection)) {
    return undefined;
  }
  const matchedKey = module.featureKeys.find((key) =>
    Object.prototype.hasOwnProperty.call(selection, key),
  );
  return matchedKey ? selection[matchedKey] : undefined;
}
function resolveModuleState(module: ClientModule, user?: StoredAuthUser | null): FeatureState {
  if (!user) {
    return 'locked';
  }
  if (module.key === 'dashboard') {
    return 'available';
  }
  // #7423 — PLANCHER D'ACCÈS garanti : tout tenant `solo` garde son socle RH
  // (pointage, absences, paie) même si sa sélection ne le coche pas. La branche
  // est AVANT « la sélection fait autorité » : le plancher est un minimum.
  if (user.company?.type === 'solo' && SOLO_FLOOR_MODULE_KEYS.includes(module.key)) {
    return 'available';
  }
  // #7235 — un profil Indépendant ne voit pas les outils de PILOTAGE D'ÉQUIPE
  // (employés, contrats, formation), quelle que soit la donnée de gate.
  if (user.company?.type === 'solo' && SOLO_HIDDEN_MODULE_KEYS.includes(module.key)) {
    return 'locked';
  }
  const capabilityState = stateFromValue(valueFor(module.capabilityKeys, user.capabilities));
  if (capabilityState) {
    return capabilityState;
  }
  // #7235 — Sélection explicite faite à l'inscription (metadata.modules →
  // /auth/me `company.modules`). Elle fait AUTORITÉ sur les replis
  // historiques : sans cela, le fallback `rh` des modules RH rendait
  // pointage/employés visibles à TOUT LE MONDE, y compris à une entreprise
  // n'ayant coché ni l'un ni l'autre.
  const explicitState = stateFromValue(explicitToolValue(module, user));
  if (explicitState) {
    return explicitState;
  }
  // Features tenant au niveau racine (/auth/me → EmployeeResource → FeatureFlag::for).
  const rootFeatureState = stateFromValue(valueFor(module.featureKeys, user.features));
  if (rootFeatureState) {
    return rootFeatureState;
  }
  const companyFeatureState = stateFromValue(valueFor(module.featureKeys, user.company?.features));
  if (companyFeatureState) {
    return companyFeatureState;
  }
  const planFeatureState = stateFromValue(valueFor(module.featureKeys, user.plan?.features));
  if (planFeatureState) {
    return planFeatureState;
  }
  // #3379 : fail-closed quand le backend fournit bien des données de
  // features/capabilities mais que la clé du module est absente (ex. un
  // non-partenaire sans is_partner). Si AUCUNE donnée de gate n'est
  // présente (ancienne session, contrat pas encore branché), on retombe
  // sur le rôle comme seul garde plutôt que de tout verrouiller.
  const hasGateData =
    !!user.capabilities ||
    !!user.features ||
    !!user.company?.features ||
    !!user.plan?.features;
  return hasGateData ? 'locked' : 'available';
}
/**
 * #6450 — la navigation portail rendait « Encountered two children with the
 * same key, `restaurant` » lorsque plusieurs entrées de module partagent la
 * même `key` (collision introduite par la maturation multi-verticale BC-25).
 * On dédoublonne par `key` (première déclaration gagnante) pour garantir des
 * clés React uniques par construction, quelle que soit la composition de
 * CLIENT_MODULES après merge des branches.
 */
function dedupeModulesByKey(modules: ClientModule[]): ClientModule[] {
  const seen = new Set<ClientModuleKey>();
  const unique: ClientModule[] = [];
  for (const entry of modules) {
    if (seen.has(entry.key)) {
      continue;
    }
    seen.add(entry.key);
    unique.push(entry);
  }
  return unique;
}
export function getClientModuleAccess(user?: StoredAuthUser | null): ClientModuleAccess[] {
  return dedupeModulesByKey(CLIENT_MODULES).map((module) => {
    const roleAllowed = hasRoleAccess(module, user);
    const state = resolveModuleState(module, user);
    const enabled = roleAllowed && state !== 'locked';
    return {
      ...module,
      state,
      enabled,
      reason: enabled ? state : roleAllowed ? 'feature_locked' : 'role_locked',
    };
  });
}
/**
 * #7225 — découpage de la navigation en deux axes :
 * - `core`      : modules transverses (utiles à toute entreprise), filtrés par
 *                 rôle/plan comme avant ;
 * - `business`  : modules **métier** réellement activés pour ce tenant
 *                 (ex. Agence de voyage), regroupés par verticale ;
 * - `lockedBusiness` : modules métier non activés — ils ne doivent PAS
 *                 encombrer le menu (c'était le défaut : « Restaurant » affiché
 *                 à une agence de voyage). Ils restent découvrables dans la
 *                 carte « Plan & Modules ».
 *
 * Un tenant sans verticale activée n'a donc aucune section métier : le menu
 * s'adapte au métier au lieu de lister toutes les verticales de la plateforme.
 */
export function getSidebarSections(access: ClientModuleAccess[]): {
  core: ClientModuleAccess[];
  business: ClientModuleAccess[];
  lockedBusiness: ClientModuleAccess[];
  verticals: BusinessVertical[];
} {
  const isBusiness = (module: ClientModuleAccess) => (module.scope ?? 'core') === 'business';
  const core = access.filter((module) => !isBusiness(module));
  const business = access.filter((module) => isBusiness(module) && module.enabled);
  const lockedBusiness = access.filter((module) => isBusiness(module) && !module.enabled);
  const verticals = Array.from(
    new Set(business.map((module) => module.vertical).filter((v): v is BusinessVertical => !!v)),
  );
  return { core, business, lockedBusiness, verticals };
}

export function getModuleAccessForPath(pathname: string, user?: StoredAuthUser | null): ClientModuleAccess | null {
  // Try exact match first
  let moduleKey: ClientModuleKey | undefined = ROUTE_TO_MODULE[pathname];
  // Fall back to prefix match for nested routes (e.g. /attendance/geo/sessions)
  if (!moduleKey) {
    const matched = Object.entries(ROUTE_TO_MODULE).find(([route]) => pathname.startsWith(route + '/'));
    moduleKey = matched?.[1];
  }
  if (!moduleKey) {
    return null;
  }
  return getClientModuleAccess(user).find((module) => module.key === moduleKey) ?? null;
}

/**
 * #7245 — Signature stable de la « surface modules » d'une session.
 *
 * Les features du tenant sont figées dans `auth_user` au moment du login
 * (`storeAuthSession`) : quand la plateforme activait un module ou une
 * verticale après coup, le client déjà connecté continuait de voir son ancien
 * menu (« j'ai activé le module, rien ne change »). Le layout recharge
 * `/auth/me` et ne réécrit la session que si cette signature a bougé.
 *
 * Volontairement indépendante de l'ordre des clés JSON : deux payloads
 * équivalents doivent produire la même signature.
 */
export function sessionModuleSignature(user?: StoredAuthUser | null): string {
  if (!user) {
    return '';
  }

  return stableSerialize([
    user.features ?? null,
    user.capabilities ?? null,
    user.company?.features ?? null,
    user.company?.modules ?? null,
    user.plan?.features ?? null,
  ]);
}

function stableSerialize(value: unknown): string {
  if (value === null || value === undefined) {
    return 'null';
  }

  if (Array.isArray(value)) {
    return `[${value.map(stableSerialize).join(',')}]`;
  }

  if (typeof value === 'object') {
    const entries = Object.entries(value as Record<string, unknown>)
      .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));
    return `{${entries.map(([key, item]) => `${JSON.stringify(key)}:${stableSerialize(item)}`).join(',')}}`;
  }

  return JSON.stringify(value) ?? 'null';
}

/**
 * #7245 — Applique à la session locale la seule « surface d'activation »
 * renvoyée par `/auth/me` : features du tenant, capacités du manager et
 * features de la société.
 *
 * Le reste (`company.modules` déclaré à l'inscription, `metadata` d'onboarding,
 * identité…) est conservé tel quel : l'assistant d'onboarding pousse des mises
 * à jour optimistes côté client, et un rechargement de session ne doit jamais
 * les écraser. C'est ce qui permet de rafraîchir les modules SANS bloquer le
 * rafraîchissement quand l'assistant est ouvert (le cas d'un client dont
 * l'onboarding n'est pas terminé — c'est-à-dire la majorité des comptes).
 */
export function mergeActivationSurface(
  current: StoredAuthUser,
  fresh: StoredAuthUser,
): StoredAuthUser {
  const company = current.company
    ? {
        ...current.company,
        features: fresh.company?.features ?? current.company.features ?? null,
      }
    : (fresh.company ?? null);

  return {
    ...current,
    features: fresh.features ?? current.features ?? null,
    capabilities: fresh.capabilities ?? current.capabilities ?? null,
    company,
  };
}
