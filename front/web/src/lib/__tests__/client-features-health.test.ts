import {
  CLIENT_MODULES,
  getClientModuleAccess,
  getModuleAccessForPath,
  getSidebarSections,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * BC-30 HEALTH — HC-008 (#7792) : espace web clinique.
 *
 * Critères d'acceptation :
 * 1. les modules HealthManager sont INVISIBLES sans le feature flag tenant
 *    `healthmanager`, et visibles après activation ;
 * 2. la verticale est déclarée `scope: 'business'` + `vertical: 'health'`,
 *    NON auto-activable (les verticales passent par l'admin plateforme) ;
 * 3. la nav est dérivée du catalogue : la carte `/health` porte 5
 *    sous-écrans (`parentKey`) — patients, rendez-vous, hospitalisations,
 *    facturation, référentiel ;
 * 4. le RBAC de la nav reflète les policies serveur : la réception ne voit
 *    pas la facturation, la facturation (comptable) la voit.
 */
describe('client-features — verticale HealthManager (HC-008, #7792)', () => {
  const HEALTH_KEYS = [
    'health_manager',
    'health_patients',
    'health_appointments',
    'health_admissions',
    'health_billing',
    'health_referential',
  ] as const;

  it('déclare la verticale santé (business + vertical health, flag healthmanager)', () => {
    for (const key of HEALTH_KEYS) {
      const entry = CLIENT_MODULES.find((m) => m.key === key);
      expect(entry).toBeDefined();
      expect(entry?.scope).toBe('business');
      expect(entry?.vertical).toBe('health');
      expect(entry?.featureKeys).toContain('healthmanager');
    }

    const main = CLIENT_MODULES.find((m) => m.key === 'health_manager');
    expect(main?.href).toBe('/health');
    expect(main?.parentKey).toBeUndefined();

    // 5 sous-écrans rattachés à la carte principale (nav dérivée, #7724).
    const children = CLIENT_MODULES.filter((m) => m.parentKey === 'health_manager');
    expect(children.map((m) => m.key).sort()).toEqual(
      ['health_admissions', 'health_appointments', 'health_billing', 'health_patients', 'health_referential'],
    );
  });

  it('reste invisible sans le flag healthmanager, visible après activation', () => {
    const withoutFlag: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
    };
    const sectionsWithout = getSidebarSections(getClientModuleAccess(withoutFlag));
    expect(sectionsWithout.business.map((m) => m.key)).not.toContain('health_manager');
    expect(sectionsWithout.verticals).not.toContain('health');

    const withFlag: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true, healthmanager: true },
    };
    const sections = getSidebarSections(getClientModuleAccess(withFlag));
    expect(sections.business.map((m) => m.key)).toContain('health_manager');
    expect(sections.verticals).toContain('health');
  });

  it('résout les routes /health/* vers les modules dédiés', () => {
    const user: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { healthmanager: true },
    };
    expect(getModuleAccessForPath('/health', user)?.key).toBe('health_manager');
    expect(getModuleAccessForPath('/health/patients', user)?.key).toBe('health_patients');
    expect(getModuleAccessForPath('/health/appointments', user)?.key).toBe('health_appointments');
    expect(getModuleAccessForPath('/health/admissions', user)?.key).toBe('health_admissions');
    expect(getModuleAccessForPath('/health/billing', user)?.key).toBe('health_billing');
    expect(getModuleAccessForPath('/health/referential', user)?.key).toBe('health_referential');
  });

  it('miroir RBAC serveur : la réception ne voit pas la facturation, le comptable oui', () => {
    const reception: StoredAuthUser = {
      role: 'manager',
      manager_role: 'superviseur',
      features: { healthmanager: true },
    };
    const receptionKeys = getClientModuleAccess(reception)
      .filter((m) => m.enabled)
      .map((m) => m.key);
    expect(receptionKeys).toContain('health_manager');
    expect(receptionKeys).toContain('health_patients');
    expect(receptionKeys).not.toContain('health_billing');
    expect(receptionKeys).not.toContain('health_referential');

    const billing: StoredAuthUser = {
      role: 'manager',
      manager_role: 'comptable',
      features: { healthmanager: true },
    };
    const billingKeys = getClientModuleAccess(billing)
      .filter((m) => m.enabled)
      .map((m) => m.key);
    expect(billingKeys).toContain('health_billing');
    expect(billingKeys).not.toContain('health_appointments');
  });
});
