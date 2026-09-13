import {
  CLIENT_MODULES,
  getClientModuleAccess,
  getModuleAccessForPath,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * BC-27 SHOWCASE (#6862) — contrat de gating du module « Site vitrine » côté
 * espace client.
 *
 * 1. Le module `showcase` est déclaré (menu + route `/showcase`).
 * 2. L'accès est soumis au RBAC manager principal/rh (miroir de
 *    `api.manager:principal,rh`) ET au feature flag tenant `company_showcase`
 *    (fail-closed quand les données de gate sont présentes).
 * 3. La sélection d'inscription (`company.modules.showcase`) fait autorité
 *    sur les replis historiques, comme pour les autres outils horizontaux.
 */
describe('client-features showcase (#6862)', () => {
  const showcaseModule = CLIENT_MODULES.find((m) => m.key === 'showcase');

  it('declares the showcase module with the tenant entry point', () => {
    expect(showcaseModule).toBeDefined();
    expect(showcaseModule?.href).toBe('/showcase');
    expect(showcaseModule?.featureKeys).toContain('company_showcase');
    expect(showcaseModule?.featureKeys).toContain('showcase');
    expect(showcaseModule?.allowedRoles).toContain('manager');
  });

  it('maps the showcase route to the showcase module', () => {
    expect(getModuleAccessForPath('/showcase', managerWithShowcase())?.key).toBe('showcase');
  });

  it('enables showcase for a principal manager with the tenant flag', () => {
    const access = getModuleAccessForPath('/showcase', managerWithShowcase());
    expect(access?.enabled).toBe(true);
    expect(access?.reason).toBe('available');
  });

  it('locks showcase for a manager without the principal/rh role', () => {
    const comptable: StoredAuthUser = {
      role: 'manager',
      manager_role: 'comptable',
      company: { features: { company_showcase: true } },
    };
    const access = getModuleAccessForPath('/showcase', comptable);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('locks showcase for an employee even with the tenant flag', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      features: { company_showcase: true },
    };
    const access = getModuleAccessForPath('/showcase', employee);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('fails closed when gate data is present but company_showcase is absent', () => {
    const noFeature: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
      company: { features: { payroll: true } },
    };
    const access = getModuleAccessForPath('/showcase', noFeature);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('feature_locked');
  });

  it('honours the explicit signup selection (company.modules.showcase)', () => {
    const explicit: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      company: { type: 'company', modules: { showcase: true } },
    };
    const access = getClientModuleAccess(explicit).find((m) => m.key === 'showcase');
    expect(access?.enabled).toBe(true);
  });
});

function managerWithShowcase(): StoredAuthUser {
  return {
    role: 'manager',
    manager_role: 'principal',
    company: { features: { company_showcase: true } },
  };
}
