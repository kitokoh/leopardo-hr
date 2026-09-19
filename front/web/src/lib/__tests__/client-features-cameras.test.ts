import {
  CLIENT_MODULES,
  SELF_ACTIVATABLE_MODULE_KEYS,
  getClientModuleAccess,
  getModuleAccessForPath,
  getSidebarSections,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * BC-19 (#7425) — contrat de gating du module « Caméras » côté espace client.
 *
 * 1. Le module `cameras` est déclaré (route `/cameras` + sous-routes détail).
 * 2. Il est porté par le seul feature flag tenant `cameras` : sans lui
 *    (données de gate présentes), la résolution est `feature_locked` — et la
 *    barre de navigation n'affiche AUCUNE pastille pour un module core
 *    verrouillé (`navPills` = `enabled`).
 * 3. Le détail est réservé au responsable du tenant (miroir de
 *    `api.manager:principal,rh`) ; un sous-rôle « sécurité » n'existe pas.
 * 4. Il EST auto-activable depuis #7476 (retour propriétaire : « chaque
 *    entrepreneur aura besoin d'avoir des caméras »). Cette propriété
 *    **supersède** le point 4 d'origine (#7425), qui réservait l'activation à
 *    la plateforme au nom de la vie privée : la décision est explicitement
 *    inversée, pas contournée — le garde-fou de vie privée qui subsiste est le
 *    **flag plateforme `cameras`**, qui reste un kill switch (la console peut
 *    toujours refuser, et le module reste verrouillé par rôle, cf. point 3).
 */
describe('client-features cameras (#7425)', () => {
  const camerasModule = CLIENT_MODULES.find((m) => m.key === 'cameras');

  it('déclare le module avec le mur de caméras comme point d’entrée', () => {
    expect(camerasModule).toBeDefined();
    expect(camerasModule?.href).toBe('/cameras');
    // #7724 — le module rejoint le groupe visuel « Opérations » de la barre.
    expect(camerasModule?.group).toBe('operations');
    expect(camerasModule?.featureKeys).toEqual(['cameras']);
    expect(camerasModule?.allowedRoles).toContain('manager');
  });

  it('résout la route du mur et la sous-route de détail vers le module', () => {
    expect(getModuleAccessForPath('/cameras', managerWithCameras())?.key).toBe('cameras');
    expect(getModuleAccessForPath('/cameras/12', managerWithCameras())?.key).toBe('cameras');
  });

  it('active le module pour un manager principal dont le tenant a le flag', () => {
    const access = getModuleAccessForPath('/cameras', managerWithCameras());
    expect(access?.enabled).toBe(true);
    expect(access?.reason).toBe('available');
  });

  it('verrouille (feature_locked) quand le flag cameras est absent', () => {
    const withoutFeature: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
      company: { features: { payroll: true } },
    };

    const access = getModuleAccessForPath('/cameras', withoutFeature);
    expect(access?.enabled).toBe(false);
    expect(access?.state).toBe('locked');
    expect(access?.reason).toBe('feature_locked');

    // `navPills` ne retient que les modules activés : un tenant sans le flag
    // n'obtient donc aucune entrée de navigation (elle reste découvrable dans
    // le panneau « Modules & plan »).
    const { core } = getSidebarSections(getClientModuleAccess(withoutFeature));
    const pills = core.filter((module) => module.group !== 'platform' && module.href && module.enabled);
    expect(pills.some((module) => module.key === 'cameras')).toBe(false);
  });

  it('verrouille par rôle un manager sans sous-rôle autorisé, même avec le flag', () => {
    const comptable: StoredAuthUser = {
      role: 'manager',
      manager_role: 'comptable',
      company: { features: { cameras: true } },
    };

    const access = getModuleAccessForPath('/cameras', comptable);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('verrouille par rôle un employé, même avec le flag tenant', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      features: { cameras: true },
    };

    const access = getModuleAccessForPath('/cameras', employee);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('est auto-activable par le client (#7476 — supersède le point 4 de #7425)', () => {
    // Décision inversée explicitement : l'activation par le client est demandée
    // par le propriétaire (#7476) ; le flag plateforme reste le veto.
    expect(SELF_ACTIVATABLE_MODULE_KEYS).toContain('cameras');
  });

  it('ne casse pas l’unicité des clés de navigation (#6450)', () => {
    const keys = getClientModuleAccess(managerWithCameras()).map((module) => module.key);
    expect(new Set(keys).size).toBe(keys.length);
  });
});

function managerWithCameras(): StoredAuthUser {
  return {
    role: 'manager',
    manager_role: 'principal',
    company: { features: { cameras: true } },
  };
}
