import {
  PACK_SIGNUP_SLUGS,
  isPackSignupSlug,
  packSolutionCode,
  packSignupHref,
} from '../pack-signup';
import { packDisplayName } from '../../data/pack-pages';

/**
 * #8462 (vague 4) — « pack-aware signup » : le métier choisi sur la vitrine
 * (`/signup?pack=<slug>`) est traduit en code du catalogue de solutions,
 * fail-closed — un slug inconnu ne produit JAMAIS de code (il ferait échouer
 * l'inscription en 422 côté catalogue).
 */
describe('pack-signup (#8462)', () => {
  it('expose les 6 packs portés par la vitrine', () => {
    expect(PACK_SIGNUP_SLUGS).toEqual([
      'restaurant',
      'station-service',
      'ecole',
      'agence-de-voyage',
      'cagnottes',
      'vtc',
    ]);
  });

  it('reconnaît les slugs valides et rejette les autres', () => {
    expect(isPackSignupSlug('restaurant')).toBe(true);
    expect(isPackSignupSlug('cagnottes')).toBe(true);
    expect(isPackSignupSlug('inconnu')).toBe(false);
    expect(isPackSignupSlug('')).toBe(false);
    expect(isPackSignupSlug(null)).toBe(false);
    expect(isPackSignupSlug(undefined)).toBe(false);
    // Sensible à la casse/au format : jamais de devinette.
    expect(isPackSignupSlug('Restaurant')).toBe(false);
    expect(isPackSignupSlug(' restaurant')).toBe(false);
  });

  it('traduit chaque slug en code du SolutionCatalogue backend', () => {
    expect(packSolutionCode('restaurant')).toBe('restaurant');
    expect(packSolutionCode('station-service')).toBe('fuel_station');
    expect(packSolutionCode('ecole')).toBe('edumanager');
    expect(packSolutionCode('agence-de-voyage')).toBe('travelagency');
    expect(packSolutionCode('cagnottes')).toBe('fundraising');
    expect(packSolutionCode('vtc')).toBe('vtc');
  });

  it('renvoie null pour un slug inconnu (fail-closed)', () => {
    expect(packSolutionCode('inconnu')).toBeNull();
    expect(packSolutionCode(null)).toBeNull();
    expect(packSolutionCode(undefined)).toBeNull();
  });

  it('construit /signup?pack=… en préservant la query courante (locale)', () => {
    expect(packSignupHref('restaurant')).toBe('/signup?pack=restaurant');
    expect(packSignupHref('ecole', 'lang=en')).toBe('/signup?lang=en&pack=ecole');
    expect(packSignupHref('cagnottes', '?lang=ar&utm_source=x')).toBe(
      '/signup?lang=ar&utm_source=x&pack=cagnottes',
    );
    // Un pack déjà présent est remplacé, jamais dupliqué.
    expect(packSignupHref('ecole', 'pack=restaurant')).toBe('/signup?pack=ecole');
  });
});

describe('packDisplayName (#8462)', () => {
  it('nomme les packs via les cartes du hub, cagnottes via le repli dédié', () => {
    expect(packDisplayName('restaurant', 'fr')).toBe('Restaurants');
    expect(packDisplayName('station-service', 'fr')).toBe('Stations-service');
    expect(packDisplayName('ecole', 'en')).toBe('Schools & training');
    expect(packDisplayName('agence-de-voyage', 'fr')).toBe('Agences de voyage');
    expect(packDisplayName('cagnottes', 'fr')).toBe('Cagnottes solidaires');
    expect(packDisplayName('cagnottes', 'en')).toBe('Solidarity fundraisers');
    expect(packDisplayName('vtc', 'fr')).toBe('VTC & Taxi');
  });

  it('retombe sur le français si la locale manque, null si slug inconnu', () => {
    expect(packDisplayName('inconnu', 'fr')).toBeNull();
  });
});
