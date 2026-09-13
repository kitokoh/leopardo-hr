import { buildDashboardNav, HR_SUBMENU_KEYS, isHrEntryActive, toNavModules, type NavModule } from '../dashboard-nav';
import { CLIENT_MODULES, type ClientModuleKey } from '../client-features';

function pill(key: ClientModuleKey): NavModule {
  const catalogueEntry = CLIENT_MODULES.find((candidate) => candidate.key === key);
  return {
    key,
    href: catalogueEntry?.href ?? `/${key}`,
    label: catalogueEntry?.label ?? key,
    enabled: true,
    group: catalogueEntry?.group ?? 'general',
  } as NavModule;
}

/**
 * #7328 — menu sur une seule ligne : RH replié en sous-menu.
 * Le propriétaire a validé le mapping : Tableau de bord · RH ▾ (Employés ·
 * Pointages · Sessions GPS · Absences · Contrats · Formations) · Paie ·
 * Rapports · Comptabilité · CRM · Marketing · Site vitrine.
 */
describe('dashboard-nav — menu une ligne + sous-menu RH (#7328)', () => {
  it('replie les modules RH dans un seul sous-menu, à la position du premier', () => {
    const entries = buildDashboardNav([
      pill('dashboard'),
      pill('employees'),
      pill('attendance'),
      pill('payroll'),
      pill('reports'),
    ]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'dashboard',
      'hr',
      'payroll',
      'reports',
    ]);

    const menu = entries[1];
    expect(menu.kind).toBe('menu');
    if (menu.kind === 'menu') {
      expect(menu.modules.map((module) => module.key)).toEqual(['employees', 'attendance']);
    }
  });

  it('conserve les liens directs (Paie, Rapports, Comptabilité, CRM, Marketing, Vitrine)', () => {
    const entries = buildDashboardNav([
      pill('employees'),
      pill('contracts'),
      pill('payroll'),
      pill('reports'),
      pill('accounting'),
      pill('crm'),
      pill('marketing'),
      pill('showcase'),
    ]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'hr',
      'payroll',
      'reports',
      'accounting',
      'crm',
      'marketing',
      'showcase',
    ]);
  });

  it('ne crée pas de menu quand un seul module RH est activé', () => {
    const entries = buildDashboardNav([pill('dashboard'), pill('employees')]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'dashboard',
      'employees',
    ]);
  });

  it('laisse le menu inchangé sans aucun module RH', () => {
    const entries = buildDashboardNav([pill('dashboard'), pill('payroll')]);

    expect(entries.every((entry) => entry.kind === 'link')).toBe(true);
    expect(entries).toHaveLength(2);
  });

  it('couvre les 6 sous-menus RH validés', () => {
    expect(HR_SUBMENU_KEYS).toEqual([
      'employees',
      'attendance',
      'attendance_geo',
      'absences',
      'contracts',
      'training',
    ]);
  });

  it('détecte l’état actif du sous-menu (chemin exact et sous-route)', () => {
    const entries = buildDashboardNav([pill('employees'), pill('attendance_geo'), pill('payroll')]);
    const menu = entries[0];
    expect(isHrEntryActive(menu, '/employees')).toBe(true);
    expect(isHrEntryActive(menu, '/attendance/geo/sessions')).toBe(true);
    expect(isHrEntryActive(menu, '/payroll')).toBe(false);
  });

  it('écarte les modules sans href (toNavModules)', () => {
    const modules = toNavModules([
      { key: 'dashboard', href: '/dashboard', label: 'Tableau de bord', enabled: true, group: 'general' },
      { key: 'billing', href: undefined, label: 'Facturation', enabled: true, group: 'platform' },
    ] as never);

    expect(modules.map((entry) => entry.key)).toEqual(['dashboard']);
  });
});
