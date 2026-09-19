import {
  CLIENT_MODULES,
  getModuleAccessForPath,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * BC-29 COMMUNICATION — R6 (#7691) : contrat de gating du module côté web.
 *
 * 1. Le module `communication` est déclaré (menu + routes) et ne pointe que
 *    vers l'API tenant `/communication/*` (R1→R5).
 * 2. La boîte est PERSONNELLE (contrat R1 #7686) : tout rôle authentifié y a
 *    accès — la garde réelle est le feature flag tenant `communication`
 *    (`module.communication` côté API), rejouée fail-closed ici.
 * 3. Le module n'est PAS auto-activable : `communication` est absent de
 *    `Company::HORIZONTAL_TOOLS` (activation via la console plateforme).
 */
describe('client-features Communication (#7691)', () => {
  const communicationModule = CLIENT_MODULES.find((m) => m.key === 'communication');

  function employeeWithCommunication(): StoredAuthUser {
    return {
      role: 'employee',
      company: { features: { communication: true } },
    } as StoredAuthUser;
  }

  it('declares the communication module with the tenant-scoped entry point', () => {
    expect(communicationModule).toBeDefined();
    expect(communicationModule?.href).toBe('/communication');
    expect(communicationModule?.featureKeys).toContain('communication');
    expect(communicationModule?.allowedRoles).toContain('employee');
  });

  it('maps every communication route to the communication module', () => {
    const user = employeeWithCommunication();
    expect(getModuleAccessForPath('/communication', user)?.key).toBe('communication');
    expect(getModuleAccessForPath('/communication/replies', user)?.key).toBe('communication');
    expect(getModuleAccessForPath('/communication/settings', user)?.key).toBe('communication');
  });

  it('enables communication for an employee with the tenant feature (personal mailbox)', () => {
    const access = getModuleAccessForPath('/communication', employeeWithCommunication());
    expect(access?.enabled).toBe(true);
    expect(access?.reason).toBe('available');
  });

  it('enables communication for a manager with the tenant feature', () => {
    const manager: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      company: { features: { communication: true } },
    } as StoredAuthUser;
    const access = getModuleAccessForPath('/communication', manager);
    expect(access?.enabled).toBe(true);
  });

  it('fails closed when gate data is present but the communication feature is absent', () => {
    const noFeature: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
      company: { features: { payroll: true } },
    } as StoredAuthUser;
    const access = getModuleAccessForPath('/communication', noFeature);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('feature_locked');
  });

  it('is not self-activable (absent from the HORIZONTAL_TOOLS mirror)', async () => {
    const { SELF_ACTIVATABLE_MODULE_KEYS } = await import('../client-features');
    expect(SELF_ACTIVATABLE_MODULE_KEYS).not.toContain('communication');
  });
});
