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
  | 'fuel';
export type FeatureState = 'available' | 'trial' | 'locked';

/**
 * Métiers (verticales) portés par la plateforme. Chaque verticale est un
 * ensemble de modules métier activés par un feature flag tenant.
 * (#7225 — audit 2026-09-10 : le menu listait « Restaurant » à une agence de
 * voyage car les modules métier étaient rangés dans les groupes transverses.)
 */
export type BusinessVertical = 'restaurant' | 'travel' | 'education' | 'fuel';

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
 * partiel ne fasse jamais réapparaître pointage/employés chez un indépendant.
 */
const SOLO_HIDDEN_MODULE_KEYS: ClientModuleKey[] = [
  'employees',
  'attendance',
  'attendance_geo',
  'absences',
  'contracts',
  'payroll',
  'training',
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
    group: 'finance',
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
  {
    key: 'travel',
    href: '/travel/portal',
    label: 'Agence de voyage',
    group: 'general',
    capabilityKeys: ['travelagency', 'travel', 'can_view_travel', 'can_manage_travel'],
    featureKeys: ['travelagency', 'travel_agency'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Agence de voyage (ventes, réservations, check-in)',
    scope: 'business',
    vertical: 'travel',
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
];
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
  '/travel/portal': 'travel',
  '/fuel': 'fuel',
  '/fuel/pump': 'fuel',
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
    if (module.key === 'edu_manager') {
      // Direction scolaire : principal/rh ou manager sans sous-rôle (propriétaire).
      return managerRole === '' || managerRole === 'principal' || managerRole === 'rh';
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
  // #7235 — un profil Indépendant ne voit ni pointage ni gestion d'employés,
  // quelle que soit la donnée de gate par ailleurs.
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
