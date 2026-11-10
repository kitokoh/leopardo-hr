import { CLIENT_MODULES, getClientModuleAccess } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #9328 — garde anti-régression du miroir `MANAGER_MODULE_ROLES`.
 *
 * Le refus par défaut (#7762) protège bien des accès indus, mais il a un
 * revers : un module métier ABSENT de la matrice n'est pas « refusé », il est
 * INVISIBLE — y compris pour le manager qui exploite la verticale tous les
 * jours. C'était le cas de `commerce`, `vtc`, `trade`, `hospitality`,
 * `communication`, `mon_logiciel` et `fundraising`.
 *
 * Ces tests ne figent pas la matrice : ils vérifient le CONTRAT « un module
 * métier activé pour le tenant est visible par le manager qui l'exploite ».
 * Ajouter une verticale au catalogue sans l'ouvrir fera donc échouer la suite
 * — c'est le but.
 */

/** Sous-rôles managers réellement proposés par le web (`team-roles.ts`). */
const ASSIGNABLE_MANAGER_ROLES = ['rh', 'dept', 'comptable', 'superviseur', 'marketing'] as const;

/**
 * Verticales dont le RBAC serveur réserve RÉELLEMENT l'accès à la direction
 * (`principal`/`rh`, parfois `comptable`). Elles doivent rester fermées à un
 * `dept` : ce n'est pas de la dérive, c'est du moindre privilège.
 */
const DIRECTION_ONLY_BUSINESS = [
  'edu_manager',
  'health',
  'health_patients',
  'health_appointments',
  'health_admissions',
  'health_billing',
  'health_referential',
];

function manager(managerRole: string, features: Record<string, boolean> = {}): StoredAuthUser {
  return { role: 'manager', manager_role: managerRole, features };
}

function accessOf(user: StoredAuthUser, key: string) {
  return getClientModuleAccess(user).find((module) => module.key === key);
}

describe('client-features — miroir des rôles managers (#9328)', () => {
  it('aucune verticale opérationnelle ne reste role_locked pour un manager `dept`', () => {
    const dept = manager('dept');
    const locked = CLIENT_MODULES.filter((module) => module.scope === 'business')
      .filter((module) => accessOf(dept, module.key)?.reason === 'role_locked')
      .map((module) => module.key);

    expect(locked.sort()).toEqual([...DIRECTION_ONLY_BUSINESS].sort());
  });

  it.each(['commerce', 'vtc', 'trade', 'hospitality', 'communication', 'mon_logiciel'])(
    'un manager `dept` voit le module métier %s quand la verticale est activée',
    (key) => {
      const entry = CLIENT_MODULES.find((candidate) => candidate.key === key);
      expect(entry).toBeDefined();

      // On active la/les feature(s) déclarée(s) par l'entrée elle-même : le
      // test ne connaît pas les clés de flag, il suit le catalogue.
      const features = Object.fromEntries(entry!.featureKeys.map((flag) => [flag, true]));
      const access = accessOf(manager('dept', features), key);

      expect(access?.reason).not.toBe('role_locked');
      // `enabled` combine rôle ET flag : la porte doit être ouverte.
      expect(access?.enabled).toBe(true);
    },
  );

  it('le flag tenant reste la seconde barrière : verticale non activée = pas de porte', () => {
    // Même sans restriction de rôle, un module `business` non activé pour le
    // tenant ne doit pas apparaître dans la navigation.
    const access = accessOf(manager('dept'), 'commerce');
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('feature_locked');
  });

  it('chaque sous-rôle manager proposé par le web ouvre au moins un module métier', () => {
    for (const role of ASSIGNABLE_MANAGER_ROLES) {
      const usable = getClientModuleAccess(manager(role, { retail: true, vtc: true, trade: true }))
        .filter((module) => module.enabled)
        .map((module) => module.key);

      expect(usable.length).toBeGreaterThan(0);
    }
  });

  it('le principal conserve l’accès total (aucun module role_locked)', () => {
    const principal = manager('principal');
    const locked = CLIENT_MODULES.filter((module) => accessOf(principal, module.key)?.reason === 'role_locked');
    expect(locked).toHaveLength(0);
  });
});
