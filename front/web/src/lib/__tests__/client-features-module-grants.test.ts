import {
  getClientModuleAccess,
  getModuleAccessForPath,
  MODULE_GRANT_KEYS,
  mergeActivationSurface,
  sessionModuleSignature,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7762 — refus par défaut + grants de modules composables (#7761).
 *
 * Contrat :
 * 1. le fallback historique « tout manager voit tout module » disparaît :
 *    un module à RBAC restreint côté API (accounting, billing, crm, showcase,
 *    cameras, marketing, integrations) n'est ouvert qu'aux rôles du miroir
 *    API, au principal, ou aux porteurs d'un grant ;
 * 2. chaque manager_role historique garde EXACTEMENT ses accès légitimes
 *    (les modules réellement ouverts à tout manager côté API le restent) ;
 * 3. un grant renvoyé par la session (`module_grants` de /auth/me) ouvre le
 *    module quel que soit le rôle — y compris un simple employé ;
 * 4. la surface de session (signature + merge) transporte les grants pour que
 *    le menu se rafraîchisse quand le principal pose/révoque une délégation.
 */
describe('client-features — grants de modules & refus par défaut (#7762)', () => {
  const accessOf = (user: StoredAuthUser, key: string) =>
    getClientModuleAccess(user).find((m) => m.key === key);

  const manager = (managerRole: string, extra: Partial<StoredAuthUser> = {}): StoredAuthUser => ({
    role: 'manager',
    manager_role: managerRole,
    features: {
      marketing: true,
      accounting: true,
      billing: true,
      crm: true,
      showcase: true,
      rh: true,
      cameras: true,
      fleet: true,
    },
    ...extra,
  });

  it('le registre des grants est le miroir fermé de ModuleKey (API #7761)', () => {
    expect([...MODULE_GRANT_KEYS]).toEqual([
      'marketing',
      'accounting',
      'support',
      'crm',
      'showcase',
      'hr',
      'billing_view',
    ]);
  });

  it('le principal voit tout (il a implicitement tous les grants)', () => {
    for (const key of ['marketing', 'accounting', 'billing', 'crm', 'showcase', 'employees']) {
      expect(accessOf(manager('principal'), key)?.enabled).toBe(true);
    }
  });

  it('chaque manager_role historique garde exactement ses accès', () => {
    // Miroir API : comptable → accounting ; marketing → marketing ; rh → crm/showcase.
    expect(accessOf(manager('comptable'), 'accounting')?.enabled).toBe(true);
    expect(accessOf(manager('marketing'), 'marketing')?.enabled).toBe(true);
    expect(accessOf(manager('rh'), 'crm')?.enabled).toBe(true);
    expect(accessOf(manager('rh'), 'showcase')?.enabled).toBe(true);
    // Les modules réellement ouverts à tout manager côté API le restent.
    for (const role of ['rh', 'dept', 'comptable', 'superviseur', 'marketing']) {
      expect(accessOf(manager(role), 'employees')?.enabled).toBe(true);
      expect(accessOf(manager(role), 'attendance')?.enabled).toBe(true);
      expect(accessOf(manager(role), 'fleet')?.enabled).toBe(true);
      expect(accessOf(manager(role), 'dashboard')?.enabled).toBe(true);
    }
  });

  it('refus par défaut : un manager hors miroir API est role_locked', () => {
    expect(accessOf(manager('dept'), 'accounting')?.reason).toBe('role_locked');
    expect(accessOf(manager('dept'), 'billing')?.reason).toBe('role_locked');
    expect(accessOf(manager('rh'), 'marketing')?.reason).toBe('role_locked');
    expect(accessOf(manager('comptable'), 'crm')?.reason).toBe('role_locked');
    expect(accessOf(manager('comptable'), 'cameras')?.reason).toBe('role_locked');
  });

  it('un grant ouvre le module au manager hors rôle historique', () => {
    const dept = manager('dept', { module_grants: ['accounting', 'billing_view'] });
    expect(accessOf(dept, 'accounting')?.enabled).toBe(true);
    expect(accessOf(dept, 'billing')?.enabled).toBe(true);
    // …et rien d'autre : la délégation est composable, pas globale.
    expect(accessOf(dept, 'marketing')?.reason).toBe('role_locked');
  });

  it('un grant ouvre le module même à un simple employé (délégation)', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      module_grants: ['marketing'],
      features: { marketing: true },
    };
    expect(accessOf(employee, 'marketing')?.enabled).toBe(true);
    expect(getModuleAccessForPath('/social-marketing', employee)?.enabled).toBe(true);
  });

  it('le grant hr ouvre le socle RH à un employé délégué', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      module_grants: ['hr'],
      features: { rh: true, reports: true },
    };
    for (const key of ['employees', 'contracts', 'payroll', 'reports']) {
      const entry = accessOf(employee, key);
      expect(entry?.reason).not.toBe('role_locked');
    }
  });

  it('sans grant, un employé reste role_locked sur les modules manager', () => {
    const employee: StoredAuthUser = { role: 'employee', features: { marketing: true } };
    expect(accessOf(employee, 'marketing')?.reason).toBe('role_locked');
  });

  it('le grant ne contourne jamais le gate de FEATURE du tenant', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      module_grants: ['marketing'],
      features: { rh: true },
    };
    expect(accessOf(employee, 'marketing')?.reason).toBe('feature_locked');
  });

  it('la signature de session change quand les grants changent (#7245/#7762)', () => {
    const before = manager('dept');
    const after = manager('dept', { module_grants: ['accounting'] });
    expect(sessionModuleSignature(before)).not.toBe(sessionModuleSignature(after));
  });

  it('mergeActivationSurface transporte les grants frais', () => {
    const current = manager('dept');
    const fresh = manager('dept', { module_grants: ['crm'] });
    expect(mergeActivationSurface(current, fresh).module_grants).toEqual(['crm']);
  });
});
