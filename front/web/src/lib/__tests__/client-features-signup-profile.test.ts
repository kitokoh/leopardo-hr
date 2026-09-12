import { getClientModuleAccess } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7235 — Le menu s'adapte au PROFIL déclaré à l'inscription.
 *
 * Contrat :
 * 1. profil `solo` (indépendant) → aucun outil d'équipe (employés, pointage,
 *    congés, contrats, paie, formations) : ni dans le menu, ni activable par
 *    les replis historiques ;
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

  it('un indépendant ne voit aucun outil d’équipe', () => {
    const solo: StoredAuthUser = {
      ...legacyUser,
      company: { type: 'solo', modules: { accounting: true, reports: true } },
    };

    for (const key of ['employees', 'attendance', 'attendance_geo', 'absences', 'contracts', 'payroll', 'training']) {
      const module = stateOf(solo, key);
      expect(module?.state).toBe('locked');
      expect(module?.enabled).toBe(false);
    }

    // …mais il garde bien ce qu’il a choisi, et le socle.
    expect(stateOf(solo, 'dashboard')?.enabled).toBe(true);
    expect(stateOf(solo, 'accounting')?.state).toBe('available');
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
