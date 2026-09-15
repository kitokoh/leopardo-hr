import { CLIENT_MODULES, getClientModuleAccess, getModuleAccessForPath } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7400 — la flotte (véhicules de service, positions, itinéraires) n'avait
 * AUCUNE surface dans le portail client : le suivi n'existait que côté admin
 * plateforme. Ce test verrouille l'entrée de navigation ajoutée.
 */
describe('client-features — module Flotte (#7400)', () => {
  it('déclare un module flotte horizontal pointant sur /fleet', () => {
    const fleet = CLIENT_MODULES.find((m) => m.key === 'fleet');
    expect(fleet).toBeDefined();
    expect(fleet?.href).toBe('/fleet');
    expect(fleet?.scope).toBe('core');
    expect(fleet?.vertical).toBeUndefined();
    expect(fleet?.capabilityKeys).toContain('can_view_fleet');
    expect(fleet?.allowedRoles).toContain('manager');
    expect(fleet?.allowedRoles).not.toContain('employee');
  });

  it('expose /fleet au manager qui a la capacité flotte', () => {
    const user: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      capabilities: { can_view_fleet: true },
    };

    const access = getClientModuleAccess(user).find((m) => m.key === 'fleet');
    expect(access?.state).toBe('available');
    expect(access?.enabled).toBe(true);
    expect(getModuleAccessForPath('/fleet', user)?.key).toBe('fleet');
  });

  it('ne donne pas la flotte à un employé (rôle hors périmètre)', () => {
    const employee: StoredAuthUser = { role: 'employee' };
    const access = getModuleAccessForPath('/fleet', employee);
    expect(access?.enabled).toBe(false);
    expect(access?.reason).toBe('role_locked');
  });
});
