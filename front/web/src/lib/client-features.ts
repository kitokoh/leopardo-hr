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
  label: string;
  group: 'general' | 'hr' | 'finance' | 'platform';
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
  {
    key: 'restaurant',
    href: '/restaurant',
    label: 'Restaurant',
    group: 'general',
    capabilityKeys: ['restaurant', 'restaurant.kitchen', 'restaurantmanager', 'can_view_restaurant'],
    featureKeys: ['restaurantmanager', 'restaurant'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Restaurant Manager',
    scope: 'business',
    vertical: 'restaurant',
  },
  {
    key: 'dashboard',
    href: '/dashboard',
    label: 'Tableau de bord',
    group: 'general',
    capabilityKeys: ['can_view_dashboard', 'dashboard'],
    featureKeys: ['dashboard', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Tableau de bord',
  },
  {
    key: 'employees',
    href: '/employees',
    label: 'Employés',
    group: 'hr',
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
    capabilityKeys: ['attendance', 'can_view_attendance'],
    featureKeys: ['attendance', 'time_tracking', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Pointage et présence',
  },
  {
    key: 'attendance_geo',
    href: '/attendance/geo',
    label: 'Attendance — Sessions GPS',
    group: 'hr',
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
    capabilityKeys: ['contracts', 'can_view_contracts'],
    featureKeys: ['contracts', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Contrats RH',
  },
  {
    key: 'payroll',
    href: '/payroll',
    label: 'Paie',
    group: 'finance',
    capabilityKeys: ['payroll', 'can_view_payroll', 'can_manage_payroll'],
    featureKeys: ['payroll', 'pay_slips'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Paie et bulletins',
  },
  {
    key: 'training',
    href: '/training',
    label: 'Formations',
    // #7432 — le champ `group` doit refléter l'emplacement RÉEL du module dans
    // la navigation : « Formations » est rendue dans le sous-menu RH
    // (`dashboard-nav.ts` → `HR_SUBMENU_KEYS`), pas dans l'espace finance.
    group: 'hr',
    capabilityKeys: ['training', 'can_view_training'],
    featureKeys: ['training'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Formation',
  },
  {
    key: 'reports',
    href: '/reports',
    label: 'Rapports',
    group: 'finance',
    capabilityKeys: ['reports', 'can_view_reports'],
    featureKeys: ['reports', 'analytics'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Rapports avancés',
  },
  {
    key: 'partner',
    href: '/partner',
    label: 'Programme Partenaire',
    group: 'general',
    capabilityKeys: ['is_partner'],
    featureKeys: ['growth_module'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Programme Partenaire',
  },
  {
    key: 'billing',
    href: '/billing',
    label: 'Facturation',
    group: 'platform',
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
    capabilityKeys: ['integrations', 'can_manage_integrations'],
    featureKeys: ['integrations', 'api_access', 'webhooks'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Intégrations',
  },
  {
    key: 'marketing',
    href: '/social-marketing',
    label: 'Marketing',
    group: 'general',
    capabilityKeys: ['marketing', 'can_view_marketing'],
    featureKeys: ['marketing', 'social_marketing'],
    allowedRoles: ['manager'],
    upgradeLabel: 'Marketing & réseaux sociaux',
  },
  // #5626 — Module Comptabilité (backend #5288/#5422 livré, front/web manquait
  // d'une entrée sidebar). Rôles : comptable et principal uniquement.
  {
    key: 'accounting',
    href: '/accounting',
    label: 'Comptabilité',
    group: 'finance',
    capabilityKeys: ['accounting', 'can_view_accounting', 'can_manage_accounting'],
    featureKeys: ['accounting', 'accounting_module'],
    allowedRoles: ['manager'],
    upgradeLabel: 'Module Comptabilité',
  },
  // #5715 — CRM Client (tenant-scoped, ADR-CRM-DUAL-CONTEXTS). Le CRM
  // commercial Leopardo reste dans l'admin plateforme : cette entrée est
  // l'espace client du tenant, porté par la feature flag `crm`.
  {
    key: 'crm',
    href: '/crm',
    label: 'CRM Client',
    group: 'general',
    capabilityKeys: ['crm', 'can_view_crm'],
    featureKeys: ['crm'],
    allowedRoles: ['manager'],
    upgradeLabel: 'CRM Client',
  },
  {
    key: 'restaurant_kitchen',
    href: '/restaurant/kitchen',
    label: 'Restaurant',
    group: 'general',
    capabilityKeys: ['restaurant', 'restaurant.kitchen'],
    featureKeys: ['restaurantmanager'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Restaurant',
    scope: 'business',
    vertical: 'restaurant',
  },
  // #7225 — verticale Agence de voyage (BC-13/TRAVEL). Le portail client
  // `/travel/portal` existait mais n'était déclaré dans AUCUNE entrée de
  // navigation : un manager d'agence de voyage n'avait aucun point d'entrée
  // métier dans le menu. Feature flag tenant `travelagency`
  // (TravelAgencyManifest::code(), ActivateTravelAgencyAction).
  // BC-24 (#7633) — l'entrée pointe désormais sur le hub GÉRANT `/travel`
  // (KPIs + réseau, voyages, réservations, rapports) ; le portail voyageur
  // devient une sous-entrée dédiée `travel_portal` (même pattern que
  // restaurant/restaurant_kitchen, même gating feature flag tenant).
  {
    key: 'travel',
    href: '/travel',
    label: 'Agence de voyage',
    group: 'general',
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
    capabilityKeys: ['retail', 'can_view_retail', 'can_manage_retail'],
    featureKeys: ['retail'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Commerce (produits, stock, caisse)',
    scope: 'business',
    vertical: 'commerce',
  },
  // #7225 — verticale Station-service (BC-15 FUEL) : la page `/fuel/pump`
  // existait sans entrée de navigation (même défaut que Travel).
  {
    key: 'fuel',
    href: '/fuel/pump',
    label: 'Station-service',
    group: 'general',
    capabilityKeys: ['fuel_station', 'fuel', 'can_view_fuel'],
    featureKeys: ['fuel_station', 'fuel'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Station-service (pompes, volumes, écarts)',
    scope: 'business',
    vertical: 'fuel',
  },
  // #7400 — Flotte & suivi des véhicules de service. Module HORIZONTAL
  // (`scope: 'core'`) : toute PME de terrain a des véhicules, ce n'est pas
  // rattaché à la verticale Agence de voyage. Le suivi n'existait que côté
  // admin plateforme (`front/admin-dashboard/src/views/fleet/FleetView.vue`) ;
  // l'agence ne pouvait ni voir ses véhicules, ni leur position, ni leurs
  // itinéraires. L'API est déjà complète (`/vehicles`, `/vehicles/{id}/trips`,
  // `/fleet/*`) et réservée aux managers (`api.manager`, sécurité #2217) :
  // la capacité `can_view_fleet` rejoue ce gate, et la feature `fleet` (ajoutée
  // au registre plateforme) permettra de vendre/activer le module par plan
  // quand le middleware `module.fleet` sera tranché (voir #7400).
  {
    key: 'fleet',
    href: '/fleet',
    label: 'Flotte',
    group: 'general',
    capabilityKeys: ['can_view_fleet', 'fleet'],
    featureKeys: ['fleet'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Flotte (véhicules, positions, itinéraires)',
    scope: 'core',
  },
  // BC-16 EDU — EduManager (EDU-011/012/013, #5827/#5828/#5829). Navigation
  // rôle-aware : manager direction (principal/rh) → administration scolaire ;
  // employé enseignant → espace enseignant (périmètre = ses classes, gardé
  // par les Policies EduManager côté API). L'entrée est portée par la
  // feature flag `edumanager` (activation tenant #5817).
  {
    key: 'edu_manager',
    href: '/edu-manager',
    label: 'EduManager',
    group: 'hr',
    capabilityKeys: ['edumanager', 'can_view_edumanager'],
    featureKeys: ['edumanager'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'EduManager',
    scope: 'business',
    vertical: 'education',
  },

  // BC-27 SHOWCASE (#6862) — module HORIZONTAL « Site vitrine » : le
  // responsable du tenant crée, édite et publie le site public de son
  // entreprise en 1 clic (page `/showcase`). Le module backend existe
  // (`app/Modules/Showcase`, routes `/api/v1/showcase/*`) et son site public
  // est servi par `/public/vitrine/{slug}` ; il manquait l'entrée d'espace
  // client. Deux clés de résolution : la sélection d'inscription
  // (`company.modules.showcase`) ET le feature flag tenant
  // (`company_showcase`) — l'ordre de `featureKeys` fait autorité pour la
  // sélection explicite (même sémantique que #7235).
  {
    key: 'showcase',
    href: '/showcase',
    label: 'Site vitrine',
    group: 'general',
    capabilityKeys: ['company_showcase', 'showcase', 'can_view_showcase', 'can_manage_showcase'],
    featureKeys: ['showcase', 'company_showcase'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Site vitrine public de l\'entreprise',
  },

  // BC-19 DEVICE (#7425) — module « Caméras » : le backend est complet et
  // testé (routes `/cameras`, viewer public `/view/cam`), mais AUCUNE entrée
  // de navigation ne l'exposait — le flag tenant `cameras` existait, la
  // surface non. Le module est porté par ce seul flag (`module.cameras`
  // renvoie 403 FEATURE_NOT_ENABLED sinon) et réservé au responsable du
  // tenant (`api.manager:principal,rh` sur `api/routes/modules/cameras.php`).
  // Un sous-rôle « sécurité » n'existe pas encore : on ne l'invente pas.
  {
    key: 'cameras',
    href: '/cameras',
    label: 'Caméras',
    group: 'general',
    capabilityKeys: ['cameras', 'can_view_cameras'],
    featureKeys: ['cameras'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Surveillance caméras (mur, permissions, partage tiers)',
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

const ROUTE_TO_MODULE: Record<string, ClientModuleKey> = {
  '/dashboard': 'dashboard',
  '/employees': 'employees',
  '/attendance': 'attendance',
  '/attendance/geo': 'attendance_geo',
  '/absences': 'absences',
  '/contracts': 'contracts',
  '/payroll': 'payroll',
  '/training': 'training',
  '/reports': 'reports',
  '/billing': 'billing',
  '/settings/developer': 'integrations',
  '/social-marketing': 'marketing',
  '/social': 'marketing',
  '/accounting': 'accounting',
  '/crm': 'crm',
  '/crm/accounts': 'crm',
  '/crm/contacts': 'crm',
  '/crm/leads': 'crm',
  '/crm/pipeline': 'crm',
  '/restaurant': 'restaurant',
  '/restaurant/pos': 'restaurant',
  '/restaurant/kitchen': 'restaurant_kitchen',
  '/travel': 'travel',
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
  '/fuel/pump': 'fuel',
  '/fleet': 'fleet',
  '/edu-manager': 'edu_manager',
  '/edu-manager/campuses': 'edu_manager',
  '/edu-manager/academic-years': 'edu_manager',
  '/edu-manager/subjects': 'edu_manager',
  '/edu-manager/classes': 'edu_manager',
  '/edu-manager/students': 'edu_manager',
  '/edu-manager/admissions': 'edu_manager',
  '/edu-manager/assessments': 'edu_manager',
  '/edu-manager/report-cards': 'edu_manager',
  '/edu-manager/teacher': 'edu_manager',
  '/showcase': 'showcase',
  // BC-19 (#7425) — mur de caméras et sous-routes de détail (`/cameras/{id}`),
  // ces dernières résolues par le match de préfixe de `getModuleAccessForPath`.
  '/cameras': 'cameras',
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
