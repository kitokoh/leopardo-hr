import { mergeActivationSurface, sessionModuleSignature } from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7245 — Une activation faite côté plateforme doit atteindre le client déjà
 * connecté.
 *
 * Défaut constaté (vérification end-to-end du 2026-09-13) : après activation de
 * la verticale « Agence de voyage » sur le tenant démo via
 * `PATCH /platform/companies/{id}/features`, le rail « Mon métier » de la
 * session ouverte restait inchangé — `auth_user` (donc `features` et
 * `capabilities`) est figé au login. Il fallait se déconnecter/reconnecter.
 *
 * Le layout recharge désormais `/auth/me` et ne réécrit la session que si cette
 * signature a bougé. Contrat :
 * 1. signature identique → aucune réécriture (pas de rendu inutile) ;
 * 2. activation d'un module ou d'une verticale → signature différente ;
 * 3. l'ordre des clés JSON ne doit pas créer de faux positif ;
 * 4. un champ hors surface modules (nom, langue…) ne déclenche rien.
 */

function user(overrides: Partial<StoredAuthUser> = {}): StoredAuthUser {
  return {
    id: 101,
    first_name: 'Ahmed',
    last_name: 'Benali',
    role: 'manager',
    features: { rh: true, finance: true, fuel_station: true, travelagency: false },
    capabilities: { can_view_dashboard: true, can_view_payroll: true },
    company: {
      id: 'company-1',
      name: 'TechCorp Algerie SARL',
      features: { rh: true, fuel_station: true, travelagency: false },
      modules: { employees: true, attendance: true },
    },
    plan: { name: 'Starter', features: { payroll: true } },
    ...overrides,
  };
}

describe('#7245 — signature de la surface modules d’une session', () => {
  it('renvoie une chaîne vide sans utilisateur', () => {
    expect(sessionModuleSignature(null)).toBe('');
    expect(sessionModuleSignature(undefined)).toBe('');
  });

  it('est stable pour deux lectures du même payload', () => {
    expect(sessionModuleSignature(user())).toBe(sessionModuleSignature(user()));
  });

  it("ignore l'ordre des clés JSON", () => {
    const reordered: StoredAuthUser = {
      ...user(),
      features: { travelagency: false, fuel_station: true, finance: true, rh: true },
      capabilities: { can_view_payroll: true, can_view_dashboard: true },
    };

    expect(sessionModuleSignature(reordered)).toBe(sessionModuleSignature(user()));
  });

  it('change quand une verticale métier est activée (cas agence de voyage)', () => {
    const before = user();
    const after = user({
      features: { rh: true, finance: true, fuel_station: true, travelagency: true },
      capabilities: { can_view_dashboard: true, can_view_payroll: true, travelagency: true },
      company: {
        ...user().company,
        features: { rh: true, fuel_station: true, travelagency: true },
      },
    });

    expect(sessionModuleSignature(after)).not.toBe(sessionModuleSignature(before));
  });

  it('change quand un outil horizontal déclaré change', () => {
    const after = user({
      company: {
        ...user().company,
        modules: { employees: true, attendance: false },
      },
    });

    expect(sessionModuleSignature(after)).not.toBe(sessionModuleSignature(user()));
  });

  it('change quand le plan expose une feature différente', () => {
    const after = user({ plan: { name: 'Starter', features: { payroll: false } } });

    expect(sessionModuleSignature(after)).not.toBe(sessionModuleSignature(user()));
  });

  it('change quand une capacité manager est retirée', () => {
    const after = user({ capabilities: { can_view_dashboard: true } });

    expect(sessionModuleSignature(after)).not.toBe(sessionModuleSignature(user()));
  });

  it('ne change pas pour un champ hors surface modules', () => {
    const after = user({ first_name: 'Fatima', language: 'ar' });

    expect(sessionModuleSignature(after)).toBe(sessionModuleSignature(user()));
  });
});

describe('#7245 — fusion de la surface d’activation', () => {
  it('applique features, capabilities et company.features', () => {
    const current = user();
    const fresh = user({
      features: { rh: true, finance: true, fuel_station: true, travelagency: true },
      capabilities: { can_view_dashboard: true, can_view_payroll: true, travelagency: true },
      company: {
        ...user().company,
        features: { rh: true, fuel_station: true, travelagency: true },
      },
    });

    const merged = mergeActivationSurface(current, fresh);

    expect(merged.features).toEqual(fresh.features);
    expect(merged.capabilities).toEqual(fresh.capabilities);
    expect(merged.company?.features).toEqual(fresh.company?.features);
  });

  it("ne touche pas aux outils déclarés à l'inscription ni aux metadata d'onboarding", () => {
    const current = user({
      company: {
        ...user().company,
        modules: { employees: true, attendance: false },
        metadata: { onboarding_completed: false },
      },
    });
    // Le serveur, lui, ne connaît pas encore la sélection en cours d'édition.
    const fresh = user({
      company: {
        ...user().company,
        modules: null,
        metadata: { onboarding_completed: true },
      },
    });

    const merged = mergeActivationSurface(current, fresh);

    expect(merged.company?.modules).toEqual({ employees: true, attendance: false });
    expect(merged.company?.metadata).toEqual({ onboarding_completed: false });
  });

  it('ne change pas la signature quand seul un champ hors activation diffère', () => {
    const current = user();
    const fresh = user({
      first_name: 'Fatima',
      company: { ...user().company, modules: { employees: false } },
    });

    expect(sessionModuleSignature(mergeActivationSurface(current, fresh)))
      .toBe(sessionModuleSignature(current));
  });

  it('change la signature quand la verticale est activée côté plateforme', () => {
    const current = user();
    const fresh = user({
      features: { rh: true, finance: true, fuel_station: true, travelagency: true },
    });

    expect(sessionModuleSignature(mergeActivationSurface(current, fresh)))
      .not.toBe(sessionModuleSignature(current));
  });
});
