import { SOLO_FLOOR_MODULE_KEYS, getClientModuleAccess } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7235 — Le menu s'adapte au PROFIL déclaré à l'inscription.
 * #7423 — …mais un PLANCHER d'accès est garanti à tout tenant : un indépendant
 * garde le socle RH (pointage, congés, paie) — ses propres outils.
 *
 * Contrat :
 * 1. profil `solo` (indépendant) → les outils d'ÉQUIPE sans objet restent
 *    verrouillés (employés, sessions GPS, contrats, formations), mais le
 *    plancher `attendance` / `absences` / `payroll` et `dashboard` sont
 *    disponibles — y compris quand la sélection les décoche (plancher garanti) ;
 * 2. profil `company` avec sélection explicite (`company.modules`) → seuls les
 *    outils cochés sont actifs. C'est le point clé : le repli `rh` de
 *    `featureKeys` rendait pointage/employés disponibles pour TOUT LE MONDE ;
 * 3. aucune sélection déclarée (tenant historique, inscription rapide) →
 *    comportement antérieur strictement préservé (aucune régression).
 */
describe('client-features — profil d’inscription (#7235)', () => {
  const stateOf = (user: StoredAuthUser, key: string) =>
    getClientModuleAccess(user).find((m) => m.key === key);

  const legacyUser: StoredAuthUser = {
    role: 'manager',
    manager_role: 'principal',
    // /auth/me : carte des flags plateforme, `rh` actif par défaut.
    features: { rh: true, finance: false, cameras: false, accounting: false },
  };

  it('un indépendant ne voit aucun outil d’équipe sans objet, mais garde le plancher RH', () => {
    const solo: StoredAuthUser = {
      ...legacyUser,
      company: { type: 'solo', modules: { accounting: true, reports: true } },
    };

    for (const key of ['employees', 'attendance_geo', 'contracts', 'training']) {
      // `entry` et non `module` : la règle Next `no-assign-module-variable`
      // interdit d'affecter une variable de ce nom (lint CI).
      const entry = stateOf(solo, key);
      expect(entry?.state).toBe('locked');
      expect(entry?.enabled).toBe(false);
    }

    // #7423 — le plancher : il se pointe, pose ses congés, lit ses bulletins.
    for (const key of SOLO_FLOOR_MODULE_KEYS) {
      const entry = stateOf(solo, key);
      expect(entry?.state).toBe('available');
      expect(entry?.enabled).toBe(true);
    }

    // …mais il garde aussi bien ce qu’il a choisi, et le socle.
    expect(stateOf(solo, 'dashboard')?.enabled).toBe(true);
    expect(stateOf(solo, 'accounting')?.state).toBe('available');
  });

  it('le plancher d’un indépendant résiste à une sélection hostile', () => {
    const hostile: StoredAuthUser = {
      ...legacyUser,
      // Sélection explicite qui décoche le plancher — et capabilities muettes
      // sur pointage/congés/paie. Le plancher reste disponible.
      capabilities: { attendance: false, can_view_attendance: false },
      company: {
        type: 'solo',
        modules: { attendance: false, absences: false, payroll: false, employees: false },
      },
    };

    for (const key of SOLO_FLOOR_MODULE_KEYS) {
      const entry = stateOf(hostile, key);
      expect(entry?.state).toBe('available');
      expect(entry?.enabled).toBe(true);
    }

    // Les outils d’équipe, eux, restent verrouillés.
    expect(stateOf(hostile, 'employees')?.state).toBe('locked');
    expect(stateOf(hostile, 'employees')?.enabled).toBe(false);
  });

  it('une entreprise ne voit que les outils qu’elle a cochés (la sélection prime sur le repli `rh`)', () => {
    const company: StoredAuthUser = {
      ...legacyUser,
      company: {
        type: 'company',
        modules: {
          employees: true,
          attendance: false,
          absences: true,
          contracts: true,
          payroll: false,
          accounting: true,
          crm: false,
        },
      },
    };

    expect(stateOf(company, 'employees')?.state).toBe('available');
    expect(stateOf(company, 'absences')?.state).toBe('available');
    expect(stateOf(company, 'contracts')?.state).toBe('available');
    expect(stateOf(company, 'accounting')?.state).toBe('available');
    // Décochés : verrouillés MALGRÉ `rh: true` dans la carte racine.
    expect(stateOf(company, 'attendance')?.state).toBe('locked');
    expect(stateOf(company, 'payroll')?.state).toBe('locked');
    expect(stateOf(company, 'crm')?.state).toBe('locked');
  });

  it('sans sélection déclarée, le comportement historique est préservé', () => {
    expect(stateOf(legacyUser, 'employees')?.state).toBe('available');
    expect(stateOf(legacyUser, 'attendance')?.state).toBe('available');
    expect(stateOf(legacyUser, 'payroll')?.state).toBe('locked');
  });

  it('un payload sans `company` ne fait pas planter la résolution', () => {
    const anonymous: StoredAuthUser = { role: 'manager' };
    expect(stateOf(anonymous, 'employees')?.state).toBe('available');
  });
});
