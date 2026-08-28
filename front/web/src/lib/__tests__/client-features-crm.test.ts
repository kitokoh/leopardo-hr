import {
  CLIENT_MODULES,
  getClientModuleAccess,
  getModuleAccessForPath,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #5715 — CRM Client : contrat de gating du module côté web.
 *
 * 1. Le module `crm` est déclaré (menu + routes) et ne pointe que vers
 *    l'API tenant `/crm/*`.
 * 2. L'accès est soumis au RBAC (manager principal/rh) ET à la feature
 *    tenant `crm` (fail-closed quand les données de gate sont présentes).
 * 3. Non-fuite cross-tenant : l'accès ne dépend que des features de
 *    l'utilisateur courant — aucun chemin ne lit les données d'un autre
 *    tenant (contrat pur de client-features, vérifié par construction).
 */
describe('client-features CRM (#5715)', () => {
  const crmModule = CLIENT_MODULES.find((m) => m.key === 'crm');

  it('declares the crm module with the tenant-scoped entry point', () => {
    expect(crmModule).toBeDefined();
    expect(crmModule?.href).toBe('/crm');
    expect(crmModule?.featureKeys).toContain('crm');
    expect(crmModule?.allowedRoles).toEqual(['manager']);
  });

  it('maps every CRM route to the crm module', () => {
    expect(getModuleAccessForPath('/crm', managerWithCrm())?.key).toBe('crm');
    expect(getModuleAccessForPath('/crm/accounts', managerWithCrm())?.key).toBe('crm');
    expect(getModuleAccessForPath('/crm/contacts', managerWithCrm())?.key).toBe('crm');
    expect(getModuleAccessForPath('/crm/leads', managerWithCrm())?.key).toBe('crm');
    expect(getModuleAccessForPath('/crm/pipeline', managerWithCrm())?.key).toBe('crm');
  });

  it('enables crm for a principal manager with the tenant feature', () => {
    const access = getModuleAccessForPath('/crm', managerWithCrm());
    expect(access?.enabled).toBe(true);
    expect(access?.reason).toBe('available');
  });

  it('locks crm for a manager without the principal/rh role', () => {
    const comptable: StoredAuthUser = {
      role: 'manager',
      manager_role: 'comptable',
      company: { features: { crm: true } },
    };
    const access = getModuleAccessForPath('/crm', comptable);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('locks crm for an employee role even with the feature', () => {
    const employee: StoredAuthUser = {
      role: 'employee',
      features: { crm: true },
    };
    const access = getModuleAccessForPath('/crm', employee);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });

  it('fails closed when gate data is present but the crm feature is absent', () => {
    const noFeature: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
      company: { features: { payroll: true } },
    };
    const access = getModuleAccessForPath('/crm', noFeature);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('feature_locked');
  });

  it('never exposes another tenant in the access decision (no cross-tenant leak)', () => {
    // Le calcul d'accès ne lit QUE les features/capabilities du user passé.
    // Deux users de deux entreprises différentes ne peuvent pas s'influencer :
    // chaque décision est dérivée uniquement de son propre payload.
    const userA = managerWithCrm();
    const userB: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      company: { features: {} },
    };
    const accessA = getClientModuleAccess(userA).find((m) => m.key === 'crm');
    const accessB = getClientModuleAccess(userB).find((m) => m.key === 'crm');
    expect(accessA?.enabled).toBe(true);
    expect(accessB?.enabled).toBe(false);
    // Le payload de B (sans feature crm) ne peut pas être influencé par A :
    // la décision est dérivée uniquement des données de l'utilisateur courant.
    expect(accessB?.reason).toBe('feature_locked');
  });
});

function managerWithCrm(): StoredAuthUser {
  return {
    role: 'manager',
    manager_role: 'principal',
    company: { features: { crm: true } },
  };
}
