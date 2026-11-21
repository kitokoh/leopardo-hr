import {
  CLIENT_MODULES,
  getClientModuleAccess,
  getModuleAccessForPath,
  getSidebarSections,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #9719 — Guards RBAC du portail web (`front/web`).
 *
 * `front/web` n'a pas de `router.beforeEach` : c'est une app Next.js. Le
 * contrôle d'accès y est réparti sur deux étages, et ce fichier couvre le
 * second — celui qui décide, écran par écran, ce qu'un utilisateur voit :
 *
 *  1. `src/proxy.ts` (middleware) — gate de SESSION : « y a-t-il un cookie ? ».
 *     Couvert par `proxy-responsibilities.test.ts` / `proxy-session-token.test.ts`.
 *  2. `lib/client-features.ts` — gate de RÔLE et de MODULE : « cet utilisateur
 *     a-t-il le droit d'ouvrir CET écran ? ». C'était le trou : les tests
 *     existants couvraient des modules isolés (cameras, crm, fleet…), aucun ne
 *     verrouillait la résolution route → module ni la matrice de rôles.
 *
 * Aucun de ces contrôles n'est une frontière de sécurité : l'API reste
 * autoritaire et répond 403. Ils évitent seulement de monter un écran qu'on
 * sait déjà interdit.
 */

function manager(managerRole: string, overrides: Partial<StoredAuthUser> = {}): StoredAuthUser {
  return {
    id: 7,
    role: 'manager',
    manager_role: managerRole,
    features: { rh: true, healthmanager: true, travelagency: true, restaurantmanager: true },
    company: { id: 'c1', features: { rh: true, healthmanager: true, travelagency: true } },
    ...overrides,
  };
}

function accessOf(user: StoredAuthUser | null, key: string) {
  return getClientModuleAccess(user).find((module) => module.key === key);
}

describe('guards RBAC — résolution route → module (#9719)', () => {
  it('résout une route exacte vers son module', () => {
    expect(getModuleAccessForPath('/health', manager('principal'))?.key).toBe('health');
    expect(getModuleAccessForPath('/cameras', manager('principal'))?.key).toBe('cameras');
  });

  it('résout une sous-route vers le module PARENT quand aucun sous-écran n’est déclaré', () => {
    // `/cameras/12` : la fiche détail n'a pas d'entrée propre, elle appartient
    // au mur de caméras.
    expect(getModuleAccessForPath('/cameras/12', manager('principal'))?.key).toBe('cameras');
  });

  it('résout une sous-route vers le module le PLUS SPÉCIFIQUE, pas vers son parent', () => {
    // Régression #9719 : `ROUTE_TO_MODULE` est construit dans l'ordre du
    // catalogue, où `/health` précède `/health/patients`. Un `.find()` sur
    // l'ordre d'insertion renvoyait le parent, et le panneau « verrouillé »
    // affichait « HealthManager » au lieu de « registre patients ».
    expect(getModuleAccessForPath('/health/patients', manager('principal'))?.key)
      .toBe('health_patients');
    expect(getModuleAccessForPath('/health/appointments', manager('principal'))?.key)
      .toBe('health_appointments');
    expect(getModuleAccessForPath('/travel/portal', manager('principal'))?.key)
      .toBe('travel_portal');
  });

  it('résout une sous-route profonde vers le sous-écran le plus proche', () => {
    // `/health/patients/42` : ni `/health/patients/42` ni `/health/patients/42/x`
    // ne sont déclarés ; le préfixe le plus long qui matche est `/health/patients`.
    expect(getModuleAccessForPath('/health/patients/42', manager('principal'))?.key)
      .toBe('health_patients');
  });

  it('ne bloque pas une sous-route profonde dont le parent est plus restrictif', () => {
    // Régression #9719, la plus grave : `/health/billing` autorise le
    // sous-rôle `comptable`, `/health` non. Avant le correctif,
    // `/health/billing/42` était résolu vers le module `health` → un comptable
    // qui ouvre une facture de soins depuis la liste se voyait servir le
    // panneau « verrouillé » alors qu'il a le droit d'être là.
    const comptable = manager('comptable', {
      features: { rh: true, healthmanager: true },
      company: { features: { rh: true, healthmanager: true } },
    });

    expect(getModuleAccessForPath('/health/billing', comptable)?.reason).toBe('available');
    expect(getModuleAccessForPath('/health/billing/42', comptable)?.key).toBe('health_billing');
    expect(getModuleAccessForPath('/health/billing/42', comptable)?.reason).toBe('available');
  });

  it('ne confond pas une route publique avec un préfixe protégé', () => {
    // `/restaurants` (annuaire public) ne doit pas être résolu vers le module
    // `restaurant` (portail client) : c'est le même piège que celui corrigé
    // dans le proxy (#8762).
    expect(getModuleAccessForPath('/restaurants', manager('principal'))).toBeNull();
    expect(getModuleAccessForPath('/restaurants/le-petit-bistrot', manager('principal'))).toBeNull();
  });

  it('renvoie null pour une route hors catalogue', () => {
    expect(getModuleAccessForPath('/une-route-inconnue', manager('principal'))).toBeNull();
    expect(getModuleAccessForPath('/', manager('principal'))).toBeNull();
  });
});

describe('guards RBAC — matrice de rôles (#7762, #9328)', () => {
  it('le manager principal voit tout', () => {
    const locked = CLIENT_MODULES.filter(
      (module) => accessOf(manager('principal'), module.key)?.reason === 'role_locked',
    );
    expect(locked).toEqual([]);
  });

  it('refuse par défaut un module absent de la matrice pour un sous-rôle', () => {
    // #7762 : un module non listé dans `MANAGER_MODULE_ROLES` n'est PAS ouvert
    // à un manager `dept`. Le refus par défaut est la règle ; c'est la matrice
    // qu'on complète, jamais le défaut qu'on assouplit.
    expect(accessOf(manager('dept'), 'accounting')?.reason).toBe('role_locked');
    expect(accessOf(manager('dept'), 'billing')?.reason).toBe('role_locked');
    expect(accessOf(manager('dept'), 'cameras')?.reason).toBe('role_locked');
  });

  it('ouvre le module au sous-rôle explicitement autorisé', () => {
    expect(accessOf(manager('comptable'), 'accounting')?.reason).not.toBe('role_locked');
    expect(accessOf(manager('rh'), 'crm')?.reason).not.toBe('role_locked');
    expect(accessOf(manager('rh'), 'cameras')?.reason).not.toBe('role_locked');
  });

  it('un grant explicite ouvre le module quel que soit le sous-rôle', () => {
    // #7762 — la délégation : le principal accorde `accounting` à un manager
    // `dept`, qui n'y aurait pas droit par son rôle.
    const delegated = manager('dept', { module_grants: ['accounting'] });

    expect(accessOf(delegated, 'accounting')?.reason).not.toBe('role_locked');
  });

  it('un grant ne franchit pas la frontière du rôle racine', () => {
    // Un `employee` n'a pas de `manager_role` : la matrice manager ne
    // s'applique pas à lui, seuls `allowedRoles` et les grants comptent.
    const employee: StoredAuthUser = {
      id: 9,
      role: 'employee',
      features: { rh: true },
      company: { features: { rh: true } },
    };

    expect(accessOf(employee, 'billing')?.reason).toBe('role_locked');
    expect(accessOf(employee, 'payroll')?.reason).toBe('role_locked');
  });

  it('distingue role_locked de feature_locked', () => {
    // #9329 — la distinction pilote le CTA du panneau verrouillé : un module
    // fermé par le RÔLE renvoie vers Équipe, un module non acheté vers le
    // commercial. Les confondre propose un achat qui ne débloquera rien.
    const noHealthFeature = manager('principal', {
      features: { rh: true },
      company: { features: { rh: true } },
    });

    expect(accessOf(noHealthFeature, 'health')?.reason).toBe('feature_locked');
    expect(accessOf(manager('dept'), 'health')?.reason).toBe('role_locked');
  });

  it('verrouille tout pour un visiteur sans session', () => {
    const access = getClientModuleAccess(null);

    expect(access.every((module) => !module.enabled)).toBe(true);
    expect(access.every((module) => module.reason === 'role_locked')).toBe(true);
  });
});

describe('guards RBAC — navigation dérivée de l’accès (#7225)', () => {
  it('ne place aucun module verrouillé dans le rail métier', () => {
    const { business } = getSidebarSections(getClientModuleAccess(manager('principal')));

    expect(business.every((module) => module.enabled)).toBe(true);
  });

  it('sort les verticales non activées du menu mais les garde découvrables', () => {
    const withoutTravel = manager('principal', {
      features: { rh: true, healthmanager: true },
      company: { features: { rh: true, healthmanager: true } },
    });

    const { business, lockedBusiness } = getSidebarSections(getClientModuleAccess(withoutTravel));

    expect(business.some((module) => module.vertical === 'travel')).toBe(false);
    expect(lockedBusiness.some((module) => module.vertical === 'travel')).toBe(true);
  });

  it('déduplique les modules partageant la même clé (#6450)', () => {
    const keys = getClientModuleAccess(manager('principal')).map((module) => module.key);

    expect(new Set(keys).size).toBe(keys.length);
  });
});
