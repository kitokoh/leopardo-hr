import type { StoredAuthUser } from '@/lib/i18n';

export type ClientModuleKey =
  | 'dashboard'
  | 'employees'
  | 'attendance'
  | 'absences'
  | 'contracts'
  | 'payroll'
  | 'training'
  | 'reports'
  | 'billing'
  | 'integrations';

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
    key: 'billing',
    label: 'Facturation',
    group: 'platform',
    capabilityKeys: ['billing', 'can_manage_billing'],
    featureKeys: ['billing'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Facturation',
  },
  {
    key: 'integrations',
    label: 'Integrations',
    group: 'platform',
    capabilityKeys: ['integrations', 'can_manage_integrations'],
    featureKeys: ['integrations', 'api_access', 'webhooks'],
    allowedRoles: ['super_admin', 'admin', 'manager'],
    upgradeLabel: 'Integrations',
  },
];

const ROUTE_TO_MODULE: Record<string, ClientModuleKey> = {
  '/dashboard': 'dashboard',
  '/employees': 'employees',
  '/attendance': 'attendance',
  '/absences': 'absences',
  '/contracts': 'contracts',
  '/payroll': 'payroll',
  '/training': 'training',
  '/reports': 'reports',
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

    return true;
  }

  return module.allowedRoles.includes(role);
}

function valueFor(keys: string[], values?: Record<string, unknown> | null): unknown {
  if (!values) {
    return undefined;
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

  const companyFeatureState = stateFromValue(valueFor(module.featureKeys, user.company?.features));
  if (companyFeatureState) {
    return companyFeatureState;
  }

  const planFeatureState = stateFromValue(valueFor(module.featureKeys, user.plan?.features));
  if (planFeatureState) {
    return planFeatureState;
  }

  return 'available';
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
  const moduleKey = ROUTE_TO_MODULE[pathname];
  if (!moduleKey) {
    return null;
  }

  return getClientModuleAccess(user).find((module) => module.key === moduleKey) ?? null;
}
