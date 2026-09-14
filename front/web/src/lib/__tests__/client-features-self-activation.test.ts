import {
  CLIENT_MODULES,
  SELF_ACTIVATABLE_MODULE_KEYS,
  isSelfActivable,
} from '../client-features';

/**
 * #7322 — auto-activation des modules par le client.
 *
 * `SELF_ACTIVATABLE_MODULE_KEYS` est le miroir côté web de l'allowlist serveur
 * `Company::HORIZONTAL_TOOLS` (endpoint `POST /company/modules/{module}/activate`,
 * fail-closed 422). Ce test verrouille les deux propriétés qui comptent :
 *  1. chaque clé déclarée existe réellement dans le catalogue client ;
 *  2. les VERTICALES (et les modules de plateforme) restent HORS périmètre —
 *     elles exigent des seeders/dépendances de pack et passent par l'admin.
 */
describe('client-features — modules auto-activables (#7322)', () => {
  it('déclare exactement les outils horizontaux', () => {
    expect([...SELF_ACTIVATABLE_MODULE_KEYS].sort()).toEqual(
      [
        'absences',
        'accounting',
        'attendance',
        'contracts',
        'crm',
        'employees',
        'marketing',
        'payroll',
        'reports',
        'showcase',
        'training',
      ].sort(),
    );
  });

  it('ne référence que des modules réellement déclarés dans le catalogue', () => {
    const keys = new Set(CLIENT_MODULES.map((module) => module.key));
    for (const key of SELF_ACTIVATABLE_MODULE_KEYS) {
      expect(keys.has(key)).toBe(true);
    }
  });

  it('exclut les verticales métier et les modules de plateforme', () => {
    for (const key of ['restaurant', 'restaurant_kitchen', 'travel', 'fuel', 'edu_manager'] as const) {
      expect(isSelfActivable({ key })).toBe(false);
    }
    for (const key of ['dashboard', 'attendance_geo', 'billing', 'integrations', 'partner'] as const) {
      expect(isSelfActivable({ key })).toBe(false);
    }
  });

  it('reconnaît les outils horizontaux', () => {
    for (const key of SELF_ACTIVATABLE_MODULE_KEYS) {
      expect(isSelfActivable({ key })).toBe(true);
    }
  });
});
