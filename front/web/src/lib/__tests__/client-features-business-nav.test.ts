import {
  CLIENT_MODULES,
  getClientModuleAccess,
  getModuleAccessForPath,
  getSidebarSections,
} from '../client-features';
import type { StoredAuthUser } from '@/lib/i18n';

/**
 * #7218 — le menu du portail client doit s'adapter au MÉTIER du tenant.
 *
 * Défaut constaté (audit 2026-09-10) : le menu listait « Restaurant » à une
 * agence de voyage, car les modules métier (restaurant, edu, fuel, travel…)
 * étaient rangés dans les groupes transverses et rendus même non activés.
 *
 * Contrat :
 * 1. chaque module métier est déclaré `scope: 'business'` + `vertical` ;
 * 2. `getSidebarSections` ne met dans `business` que les verticales
 *    réellement ACTIVÉES pour l'utilisateur ; les autres vont dans
 *    `lockedBusiness` (découvrables, hors menu) ;
 * 3. le menu transverse (`core`) ne contient aucun module métier ;
 * 4. une agence de voyage a bien un point d'entrée métier (le module `travel`
 *    manquait complètement — `/travel/portal` existait sans entrée de menu).
 */
describe('client-features — navigation par métier (#7218)', () => {
  const BUSINESS_KEYS = ['restaurant', 'restaurant_kitchen', 'edu_manager', 'travel', 'fuel'];

  it('déclare la verticale Agence de voyage (absente avant #7218)', () => {
    const travel = CLIENT_MODULES.find((m) => m.key === 'travel');
    expect(travel).toBeDefined();
    expect(travel?.href).toBe('/travel/portal');
    expect(travel?.featureKeys).toContain('travelagency');
    expect(travel?.scope).toBe('business');
    expect(travel?.vertical).toBe('travel');
  });

  it('marque tous les modules métier comme business + verticale', () => {
    for (const key of BUSINESS_KEYS) {
      const entry = CLIENT_MODULES.find((m) => m.key === key);
      expect(entry?.scope).toBe('business');
      expect(entry?.vertical).toBeTruthy();
    }
  });

  it('route /travel/portal vers le module travel', () => {
    const user: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { travelagency: true },
    };
    expect(getModuleAccessForPath('/travel/portal', user)?.key).toBe('travel');
  });

  it('une agence de voyage voit SON métier, pas celui des autres', () => {
    const travelAgency: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true, travelagency: true },
    };
    const { core, business, lockedBusiness, verticals } = getSidebarSections(
      getClientModuleAccess(travelAgency),
    );

    const businessKeys = business.map((m) => m.key);
    expect(businessKeys).toContain('travel');
    expect(businessKeys).not.toContain('restaurant');
    expect(businessKeys).not.toContain('edu_manager');
    expect(verticals).toEqual(['travel']);

    // Les verticales non activées ne sont PAS dans le menu, mais restent
    // découvrables (carte « Plan & Modules »).
    const lockedKeys = lockedBusiness.map((m) => m.key);
    expect(lockedKeys).toContain('restaurant');
    expect(lockedKeys).not.toContain('travel');

    // Le menu transverse ne contient aucun module métier.
    expect(core.map((m) => m.key).some((k) => BUSINESS_KEYS.includes(k))).toBe(false);
  });

  it('un restaurant voit Restaurant, jamais Agence de voyage', () => {
    const restaurant: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true, restaurant: true },
    };
    const { business, verticals } = getSidebarSections(getClientModuleAccess(restaurant));
    expect(business.map((m) => m.key)).toContain('restaurant');
    expect(business.map((m) => m.key)).not.toContain('travel');
    expect(verticals).toContain('restaurant');
  });

  it('un tenant sans verticale n’a aucune section métier (menu non pollué)', () => {
    const rhOnly: StoredAuthUser = {
      role: 'manager',
      manager_role: 'principal',
      features: { rh: true },
    };
    const { business, lockedBusiness, verticals } = getSidebarSections(
      getClientModuleAccess(rhOnly),
    );
    expect(business).toEqual([]);
    expect(verticals).toEqual([]);
    expect(lockedBusiness.length).toBeGreaterThan(0);
  });

  it('les clés restent uniques (garde #6450 préservée)', () => {
    const access = getClientModuleAccess(null);
    const keys = access.map((m) => m.key);
    expect(new Set(keys).size).toBe(keys.length);
  });
});
