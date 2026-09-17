import { getClientModuleAccess } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7235 — Le menu s'adapte au PROFIL déclaré à l'inscription.
 * #7423 — …et un profil `solo` conserve un PLANCHER D'ACCÈS RH.
 *
 * Contrat :
 * 1. profil `solo` (indépendant) → aucun outil d'ÉQUIPE (employés, contrats,
 *    formations, pointage géolocalisé) : ni dans le menu, ni activable par les
 *    replis historiques ; MAIS le socle RH INDIVIDUEL est garanti — se
 *    pointer, poser ses congés, lire ses bulletins — y compris quand la
 *    sélection d'inscription les refuse (c'est le cas normal : un solo ne
 *    coche aucun outil d'équipe). Cf. `SOLO_FLOOR_MODULE_KEYS` / #7423 ;
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

  it('un indépendant garde le socle RH individuel (#7423) et perd les outils d’équipe', () => {
    const solo: StoredAuthUser = {
      ...legacyUser,
      // Sélection volontairement INCOMPLÈTE — et explicitement `false` sur le
      // socle RH : c'est exactement ce que le provisioning écrivait avant
      // #7423, et ce que le plancher doit rattraper.
      company: {
        type: 'solo',
        modules: {
          accounting: true,
          reports: true,
          attendance: false,
          absences: false,
          payroll: false,
        },
      },
    };

    // #7423 — Plancher d'accès : ces clés restent OUVERTES pour un solo, même
    // quand la sélection les refuse. Le plancher est évalué AVANT l'autorité de
    // la sélection (#7235), sinon un solo perdrait précisément ce qu'il garde.
    for (const key of ['attendance', 'absences', 'payroll']) {
      const entry = stateOf(solo, key);
      expect(entry?.state).toBe('available');
      expect(entry?.enabled).toBe(true);
    }

    // Outils d'ÉQUIPE : toujours coupés pour un indépendant.
    for (const key of ['employees', 'attendance_geo', 'contracts', 'training']) {
      // `entry` et non `module` : la règle Next `no-assign-module-variable`
      // interdit d'affecter une variable de ce nom (lint CI).
      const entry = stateOf(solo, key);
      expect(entry?.state).toBe('locked');
      expect(entry?.enabled).toBe(false);
    }

    // …il garde bien ce qu’il a choisi, et le socle.
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
