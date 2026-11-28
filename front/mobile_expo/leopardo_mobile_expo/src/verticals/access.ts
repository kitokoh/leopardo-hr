import type { Employee } from '../models/employee';
import {
  CLIENT_MODULES,
  GRANT_TO_MODULE_KEYS,
  MANAGER_MODULE_ROLES,
  MODULE_ROUTE_ALIASES,
  SOLO_FLOOR_MODULE_KEYS,
  SOLO_HIDDEN_MODULE_KEYS,
  type ModuleGrantKey,
} from './catalog';
import type {
  BusinessRailEntry,
  ClientModule,
  ClientModuleAccess,
  ClientModuleKey,
  FeatureState,
} from './types';

/**
 * RESOLUTION D'ACCES AUX MODULES — portage fidele de
 * `front/web/src/lib/client-features.ts` (`getClientModuleAccess`).
 *
 * Le mobile n'invente aucune regle : il rejoue la meme cascade que le web,
 * dans le meme ordre, avec les memes donnees de session (`/auth/me`).
 * Consequence : un utilisateur voit EXACTEMENT les memes modules sur mobile
 * et sur le web, et un module verrouille cote web l'est aussi cote mobile.
 */

/** Role normalise de l'utilisateur. */
function normalizedRole(employee?: Employee | null): string {
  if (!employee?.role) return 'guest';
  return employee.role.toLowerCase();
}

/** Grants de session normalises (`/auth/me` → `module_grants`). */
function sessionGrants(employee?: Employee | null): string[] {
  const raw = employee?.module_grants;
  if (!Array.isArray(raw)) return [];
  return raw.map((key) => String(key).toLowerCase());
}

/** Un grant explicite ouvre-t-il ce module ? */
function grantOpensModule(moduleKey: ClientModuleKey, grants: string[]): boolean {
  return grants.some((grant) =>
    (GRANT_TO_MODULE_KEYS[grant as ModuleGrantKey] ?? []).includes(moduleKey),
  );
}

/**
 * L'utilisateur a-t-il le ROLE requis pour ce module ?
 * Miroir de `hasRoleAccess` : grant explicite > principal > matrice manager >
 * `allowedRoles`.
 */
function hasRoleAccess(module: ClientModule, employee?: Employee | null): boolean {
  // Un grant explicite du principal ouvre le module quel que soit le role.
  if (grantOpensModule(module.key, sessionGrants(employee))) {
    return true;
  }

  const role = normalizedRole(employee);

  if (role === 'manager') {
    const managerRole = (employee?.manager_role ?? '').toLowerCase();

    // Le principal voit tout (il a implicitement tous les grants).
    if (managerRole === 'principal') {
      return true;
    }

    const allowed = MANAGER_MODULE_ROLES[module.key];
    if (allowed === 'any') {
      return true;
    }
    if (Array.isArray(allowed)) {
      return allowed.includes(managerRole);
    }

    // REFUS PAR DEFAUT : module non liste → principal + grant seulement.
    return false;
  }

  return module.allowedRoles.includes(role);
}

/**
 * Lit la valeur d'un module dans un dictionnaire de gates.
 * Accepte un objet (`{ rh: true }`) ou une liste de cles (`['rh']`).
 */
function valueFor(keys: string[], values?: Record<string, unknown> | string[] | null): unknown {
  if (!values) return undefined;

  if (Array.isArray(values)) {
    return keys.some((key) => values.includes(key)) ? true : undefined;
  }

  const matchedKey = keys.find((key) => Object.prototype.hasOwnProperty.call(values, key));
  return matchedKey ? (values as Record<string, unknown>)[matchedKey] : undefined;
}

/** Convertit une valeur de gate en etat de module. */
function stateFromValue(value: unknown): FeatureState | null {
  if (value === undefined) return null;
  if (value === true || value === 'enabled' || value === 'available') return 'available';
  if (value === 'trial') return 'trial';
  return 'locked';
}

/**
 * Valeur du module dans la selection explicite faite a l'inscription
 * (`company.modules`). La premiere `featureKey` presente fait foi.
 */
function explicitToolValue(module: ClientModule, employee?: Employee | null): unknown {
  const selection = employee?.company?.modules;
  if (!selection || typeof selection !== 'object' || Array.isArray(selection)) {
    return undefined;
  }
  const matchedKey = module.featureKeys.find((key) =>
    Object.prototype.hasOwnProperty.call(selection, key),
  );
  return matchedKey ? (selection as Record<string, unknown>)[matchedKey] : undefined;
}

/**
 * Etat d'un module — cascade identique a `resolveModuleState` du web.
 */
function resolveModuleState(module: ClientModule, employee?: Employee | null): FeatureState {
  if (!employee) return 'locked';

  if (module.key === 'dashboard') return 'available';

  // PLANCHER D'ACCES garanti pour un tenant `solo` (pointage, absences, paie).
  if (employee.company?.type === 'solo' && SOLO_FLOOR_MODULE_KEYS.includes(module.key)) {
    return 'available';
  }

  // Un profil Independant ne voit pas les outils de PILOTAGE D'EQUIPE.
  if (employee.company?.type === 'solo' && SOLO_HIDDEN_MODULE_KEYS.includes(module.key)) {
    return 'locked';
  }

  const capabilityState = stateFromValue(valueFor(module.capabilityKeys, employee.capabilities));
  if (capabilityState) return capabilityState;

  // La selection explicite de l'inscription fait AUTORITE sur les replis.
  const explicitState = stateFromValue(explicitToolValue(module, employee));
  if (explicitState) return explicitState;

  // Features tenant au niveau racine (`/auth/me` → `features`).
  const rootFeatureState = stateFromValue(valueFor(module.featureKeys, employee.features));
  if (rootFeatureState) return rootFeatureState;

  const companyFeatureState = stateFromValue(
    valueFor(module.featureKeys, employee.company?.features),
  );
  if (companyFeatureState) return companyFeatureState;

  const planFeatureState = stateFromValue(valueFor(module.featureKeys, employee.plan?.features));
  if (planFeatureState) return planFeatureState;

  // Fail-closed quand le backend fournit des gates mais que la cle manque.
  // Sans aucune donnee de gate (ancienne session), on retombe sur le role.
  const hasGateData =
    !!employee.capabilities ||
    !!employee.features ||
    !!employee.company?.features ||
    !!employee.plan?.features;

  return hasGateData ? 'locked' : 'available';
}

/** Dedoublonne par cle (premiere declaration gagnante). */
function dedupeModulesByKey(modules: ClientModule[]): ClientModule[] {
  const seen = new Set<ClientModuleKey>();
  const unique: ClientModule[] = [];
  for (const entry of modules) {
    if (seen.has(entry.key)) continue;
    seen.add(entry.key);
    unique.push(entry);
  }
  return unique;
}

/**
 * Acces complet de l'utilisateur a tous les modules du catalogue.
 * Miroir de `getClientModuleAccess`.
 */
export function getClientModuleAccess(employee?: Employee | null): ClientModuleAccess[] {
  return dedupeModulesByKey(CLIENT_MODULES).map((module) => {
    const roleAllowed = hasRoleAccess(module, employee);
    const state = resolveModuleState(module, employee);
    const enabled = roleAllowed && state !== 'locked';

    let reason: ClientModuleAccess['reason'];
    if (!roleAllowed) reason = 'role_locked';
    else if (state === 'trial') reason = 'trial';
    else if (state === 'locked') reason = 'feature_locked';
    else reason = 'available';

    return { ...module, state, enabled, reason };
  });
}

/** Modules reellement accessibles (actives ET autorises). */
export function getEnabledModules(employee?: Employee | null): ClientModuleAccess[] {
  return getClientModuleAccess(employee).filter((module) => module.enabled);
}

/** Modules d'une verticale accessibles a l'utilisateur. */
export function getVerticalModules(
  employee: Employee | null | undefined,
  vertical: string,
): ClientModuleAccess[] {
  return getEnabledModules(employee).filter((module) => module.vertical === vertical);
}

/**
 * Rail « Mon metier » : hierarchise les sous-ecrans metier sous leur carte
 * parente. Un sous-ecran dont le parent n'est pas active redevient une carte
 * de premier niveau (aucun ecran accessible ne doit disparaitre).
 * Miroir de `buildBusinessRail`.
 */
export function buildBusinessRail(business: ClientModuleAccess[]): BusinessRailEntry[] {
  const byKey = new Map(business.map((module) => [module.key, module]));
  const entries: BusinessRailEntry[] = [];

  for (const entry of business) {
    if (entry.parentKey && byKey.has(entry.parentKey)) {
      continue;
    }
    entries.push({
      module: entry,
      children: business.filter((candidate) => candidate.parentKey === entry.key),
    });
  }

  return entries;
}

/** Modules metier accessibles (rail « Mon metier »). */
export function getBusinessModules(employee?: Employee | null): ClientModuleAccess[] {
  return getEnabledModules(employee).filter((module) => module.scope === 'business');
}

/** Modules transverses accessibles (socle RH, finance, croissance, operations). */
export function getCoreModules(employee?: Employee | null): ClientModuleAccess[] {
  return getEnabledModules(employee).filter((module) => module.scope !== 'business');
}

/** Modules d'un groupe visuel (RH, Finance, Clients & croissance, Operations). */
export function getModulesByGroup(
  employee: Employee | null | undefined,
  group: string,
): ClientModuleAccess[] {
  return getEnabledModules(employee).filter((module) => module.group === group);
}

/**
 * Table route → module, derivee du catalogue puis completee par les alias.
 * Miroir de `ROUTE_TO_MODULE`.
 */
const ROUTE_TO_MODULE: Record<string, ClientModuleKey> = {
  ...Object.fromEntries(
    dedupeModulesByKey(CLIENT_MODULES)
      .filter((module): module is ClientModule & { href: string } => !!module.href)
      .map((module) => [module.href, module.key]),
  ),
  ...MODULE_ROUTE_ALIASES,
};

/**
 * Module correspondant a un chemin web (match exact puis par prefixe).
 * Utile pour ouvrir un ecran mobile depuis un lien profond web.
 */
export function getModuleKeyForPath(pathname: string): ClientModuleKey | null {
  if (ROUTE_TO_MODULE[pathname]) {
    return ROUTE_TO_MODULE[pathname];
  }

  // Match de prefixe : `/cameras/12` → `cameras`.
  const candidates = Object.keys(ROUTE_TO_MODULE)
    .filter((route) => pathname.startsWith(`${route}/`))
    .sort((a, b) => b.length - a.length);

  return candidates.length > 0 ? ROUTE_TO_MODULE[candidates[0]] : null;
}

/** L'utilisateur peut-il ouvrir ce module ? */
export function canAccessModule(
  employee: Employee | null | undefined,
  key: ClientModuleKey,
): boolean {
  return getClientModuleAccess(employee).some((module) => module.key === key && module.enabled);
}

/** Nombre de modules metier actifs (badge du rail). */
export function countActiveVerticals(employee?: Employee | null): number {
  const verticals = new Set(
    getBusinessModules(employee)
      .map((module) => module.vertical)
      .filter((vertical): vertical is NonNullable<typeof vertical> => Boolean(vertical)),
  );
  return verticals.size;
}
