import {
  buildBusinessRail,
  buildDashboardNav,
  HR_SUBMENU_KEYS,
  isHrEntryActive,
  navGroupKeys,
  toNavModules,
  type NavModule,
} from '../dashboard-nav';
import { CLIENT_MODULES, type ClientModuleAccess, type ClientModuleKey } from '../client-features';

function pill(key: ClientModuleKey): NavModule {
  const catalogueEntry = CLIENT_MODULES.find((candidate) => candidate.key === key);
  return {
    key,
    href: catalogueEntry?.href ?? `/${key}`,
    label: catalogueEntry?.label ?? key,
    enabled: true,
    group: catalogueEntry?.group ?? 'general',
    parentKey: catalogueEntry?.parentKey,
  } as NavModule;
}

/**
 * #7328/#7724 — menu sur une seule ligne, groupes visuels dérivés du champ
 * `group` du catalogue (seule source de vérité) : Tableau de bord · Rapports ·
 * RH ▾ (Employés · Pointages · Sessions GPS · Absences · Contrats ·
 * Formations · Paie) · Finance ▾ (Comptabilité) · Clients & croissance ▾
 * (CRM · Marketing · Site vitrine · Programme Partenaire) · Opérations ▾
 * (Flotte · Caméras).
 */
describe('dashboard-nav — menu une ligne + sous-menus de groupe (#7328/#7724)', () => {
  it('replie chaque groupe dans un sous-menu, à la position du premier module', () => {
    const entries = buildDashboardNav([
      pill('dashboard'),
      pill('reports'),
      pill('employees'),
      pill('attendance'),
      pill('payroll'),
      pill('accounting'),
      pill('crm'),
      pill('marketing'),
      pill('fleet'),
      pill('cameras'),
    ]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'dashboard',
      'reports',
      'hr',
      'accounting', // Finance à un seul module activé → lien direct.
      'growth',
      'operations',
    ]);

    const menu = entries[2];
    expect(menu.kind).toBe('menu');
    if (menu.kind === 'menu') {
      expect(menu.modules.map((module) => module.key)).toEqual(['employees', 'attendance', 'payroll']);
    }

    const growth = entries[4];
    if (growth.kind === 'menu') {
      expect(growth.modules.map((module) => module.key)).toEqual(['crm', 'marketing']);
    }
  });

  it('la Paie rejoint le sous-menu RH (#7724 : plus de pill de premier niveau)', () => {
    const entries = buildDashboardNav([pill('employees'), pill('payroll'), pill('reports')]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'hr',
      'reports',
    ]);

    const menu = entries[0];
    if (menu.kind === 'menu') {
      expect(menu.modules.map((module) => module.key)).toEqual(['employees', 'payroll']);
    }
  });

  it('ne crée pas de menu quand un seul module du groupe est activé', () => {
    const entries = buildDashboardNav([pill('dashboard'), pill('employees')]);

    expect(entries.map((entry) => (entry.kind === 'link' ? entry.module.key : entry.id))).toEqual([
      'dashboard',
      'employees',
    ]);
  });

  it('laisse le menu inchangé sans aucun module groupé', () => {
    const entries = buildDashboardNav([pill('dashboard'), pill('reports')]);

    expect(entries.every((entry) => entry.kind === 'link')).toBe(true);
    expect(entries).toHaveLength(2);
  });

  it('HR_SUBMENU_KEYS est DÉRIVÉ du catalogue (plus de double source #7432)', () => {
    expect(HR_SUBMENU_KEYS).toEqual(
      CLIENT_MODULES.filter((module) => module.group === 'hr').map((module) => module.key),
    );
    // Le mapping validé : Employés · Pointages · Sessions GPS · Absences ·
    // Contrats · Formations · Paie (la Paie rejoint la RH, #7724).
    expect(HR_SUBMENU_KEYS).toEqual([
      'employees',
      'attendance',
      'attendance_geo',
      'absences',
      'contracts',
      'training',
      'payroll',
    ]);
  });

  it('groupes cibles du catalogue : Finance, Clients & croissance, Opérations', () => {
    expect(navGroupKeys('finance')).toEqual(['accounting']);
    expect(navGroupKeys('growth')).toEqual(['crm', 'marketing', 'showcase', 'partner']);
    expect(navGroupKeys('operations')).toEqual(['fleet', 'cameras']);
  });

  it('edu_manager n’est plus classé group hr (verticale éducation, #7724)', () => {
    const edu = CLIENT_MODULES.find((module) => module.key === 'edu_manager');
    expect(edu?.group).not.toBe('hr');
    expect(edu?.scope).toBe('business');
    expect(edu?.vertical).toBe('education');
  });

  it('le catalogue commence par le tableau de bord (anomalie d’ordre corrigée)', () => {
    expect(CLIENT_MODULES[0]?.key).toBe('dashboard');
  });

  it('les labels de compat du catalogue sont uniques (i18n = source des libellés)', () => {
    const labels = CLIENT_MODULES.map((module) => module.label);
    expect(new Set(labels).size).toBe(labels.length);
  });

  it('chaque module navigable porte une icône (#7724)', () => {
    for (const entry of CLIENT_MODULES) {
      expect(entry.icon).toBeDefined();
    }
  });

  it('détecte l’état actif du sous-menu (chemin exact et sous-route)', () => {
    const entries = buildDashboardNav([pill('employees'), pill('attendance_geo'), pill('reports')]);
    const menu = entries[0];
    expect(isHrEntryActive(menu, '/employees')).toBe(true);
    expect(isHrEntryActive(menu, '/attendance/geo/sessions')).toBe(true);
    expect(isHrEntryActive(menu, '/reports')).toBe(false);
  });

  it('écarte les modules sans href (toNavModules)', () => {
    const modules = toNavModules([
      { key: 'dashboard', href: '/dashboard', label: 'Tableau de bord', enabled: true, group: 'general' },
      { key: 'billing', href: undefined, label: 'Facturation', enabled: true, group: 'platform' },
    ] as never);

    expect(modules.map((entry) => entry.key)).toEqual(['dashboard']);
  });
});

/**
 * #7724 — rail « Mon métier » hiérarchisé : Cuisine sous Restaurant, Portail
 * voyageur sous Agence de voyage. Un sous-écran orphelin (parent non activé)
 * redevient une carte de premier niveau.
 */
describe('dashboard-nav — rail métier hiérarchisé (#7724)', () => {
  const access = (key: ClientModuleKey): ClientModuleAccess =>
    ({ ...CLIENT_MODULES.find((m) => m.key === key)!, state: 'available', enabled: true, reason: 'available' });

  it('rattache Cuisine à Restaurant et Portail voyageur à Agence de voyage', () => {
    const rail = buildBusinessRail([
      access('restaurant'),
      access('restaurant_kitchen'),
      access('travel'),
      access('travel_portal'),
    ]);

    expect(rail.map((entry) => entry.module.key)).toEqual(['restaurant', 'travel']);
    expect(rail[0].children.map((child) => child.key)).toEqual(['restaurant_kitchen']);
    expect(rail[1].children.map((child) => child.key)).toEqual(['travel_portal']);
  });

  it('un sous-écran sans parent activé reste accessible au premier niveau', () => {
    const rail = buildBusinessRail([access('restaurant_kitchen')]);
    expect(rail.map((entry) => entry.module.key)).toEqual(['restaurant_kitchen']);
    expect(rail[0].children).toEqual([]);
  });
});
