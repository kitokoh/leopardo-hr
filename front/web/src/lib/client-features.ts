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
  | 'edu_manager';

export type FeatureState = 'available' | 'trial' | 'locked';

export type ClientModule = {
  key: ClientModuleKey;
  href?: string;
  label: string;
  group: 'general' | 'hr' | 'finance' | 'platform';
  capabilityKeys: string[];
  featureKeys: string[];
  allowedRoles: string[];
  upgradeLabel: string;
};

export type ClientModuleAccess = ClientModule & {
  state: FeatureState;
  enabled: boolean;
  reason: 'available' | 'trial' | 'feature_locked' | 'role_locked';
};

export const CLIENT_MODULES: ClientModule[] = [
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
    label: 'Employes',
    group: 'hr',
    capabilityKeys: ['employees', 'can_view_employees', 'can_create_employees'],
    featureKeys: ['employees', 'employee_management', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Gestion des employes',
  },
  {
    key: 'attendance',
    href: '/attendance',
    label: 'Pointages',
    group: 'hr',
    capabilityKeys: ['attendance', 'can_view_attendance'],
    featureKeys: ['attendance', 'time_tracking', 'rh'],
    allowedRoles: ['super_admin', 'admin', 'manager', 'employee'],
    upgradeLabel: 'Pointage et presence',
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
    upgradeLabel: 'Absences et conges',
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
    upgradeLabel: 'Rapports avances',
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
    label: 'Integrations',
    group: 'platform',
    capabilityKeys: ['integrations', 'can_manage_integrations'],
    featureKeys: ['integrations', 'api_access', 'webhooks'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Integrations',
  },
  {
    key: 'marketing',
    href: '/social-marketing',
    label: 'Marketing',
    group: 'general',
    capabilityKeys: ['marketing', 'can_view_marketing'],
    featureKeys: ['marketing', 'social_marketing'],
    allowedRoles: ['manager'],
    upgradeLabel: 'Marketing & reseaux sociaux',
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
    key: 'restaurant',
    href: '/restaurant/kitchen',
    label: 'Restaurant',
    group: 'general',
    capabilityKeys: ['restaurant', 'restaurant.kitchen'],
    featureKeys: ['restaurantmanager'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Restaurant',
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
  '/restaurant/kitchen': 'restaurant',
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

function resolveModuleState(module: ClientModule, user?: StoredAuthUser | null): FeatureState {
  if (!user) {
    return 'locked';
  }

  if (module.key === 'dashboard') {
    return 'available';
  }

  const capabilityState = stateFromValue(valueFor(module.capabilityKeys, user.capabilities));
  if (capabilityState) {
    return capabilityState;
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
    (user.capabilities && Object.keys(user.capabilities).length > 0) ||
    (user.features && Object.keys(user.features).length > 0) ||
    (user.company?.features && Object.keys(user.company.features).length > 0) ||
    (user.plan?.features && Object.keys(user.plan.features).length > 0);

  return hasGateData ? 'locked' : 'available';
}

export function getClientModuleAccess(user?: StoredAuthUser | null): ClientModuleAccess[] {
  return CLIENT_MODULES.map((module) => {
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
