import { companyLocation, hasProductCategories, tradeDirections, type Company } from '../trade/domain/company';
import { toCompany } from '../trade/infrastructure/company-gateway';
import { TRADE_SURFACE } from '../trade/identity/trade-identity';
import { surfaceRegistry } from '../registry';

function company(overrides: Partial<Company> = {}): Company {
  return {
    slug: 'acme',
    name: 'ACME Négoce',
    description: null,
    city: 'Lyon',
    country: 'France',
    productCategories: [],
    currency: 'EUR',
    imports: false,
    exports: false,
    domestic: false,
    ...overrides,
  };
}

describe('domaine B2B — directions', () => {
  it('renvoie une liste vide si aucune direction', () => {
    expect(tradeDirections(company())).toEqual([]);
  });

  it('ordonne les directions de façon stable', () => {
    expect(tradeDirections(company({ imports: true, exports: true, domestic: true }))).toEqual([
      'Import',
      'Export',
      'Marché domestique',
    ]);
  });
});

describe('domaine B2B — localisation', () => {
  it('joint ville et pays', () => {
    expect(companyLocation(company())).toBe('Lyon, France');
  });

  it('tolère une partie manquante', () => {
    expect(companyLocation(company({ city: null }))).toBe('France');
  });

  it('renvoie null si tout est inconnu', () => {
    expect(companyLocation(company({ city: null, country: null }))).toBeNull();
  });
});

describe('domaine B2B — catégories', () => {
  it('détecte la présence de catégories', () => {
    expect(hasProductCategories(company())).toBe(false);
    expect(hasProductCategories(company({ productCategories: ['Café'] }))).toBe(true);
  });
});

describe('adaptateur B2B', () => {
  it('traduit la fiche publique vers le domaine', () => {
    const mapped = toCompany({
      slug: 'acme',
      name: 'ACME Négoce',
      description: 'Négoce de café.',
      city: 'Lyon',
      country: 'France',
      product_categories: ['Café'],
      currency: 'EUR',
      import_enabled: true,
      export_enabled: false,
      domestic_scope: true,
    });

    expect(mapped.imports).toBe(true);
    expect(mapped.exports).toBe(false);
    expect(mapped.productCategories).toEqual(['Café']);
  });
});

describe('identité B2B', () => {
  it('est déclarée avec son propre domaine', () => {
    expect(surfaceRegistry.get('trade')).toBe(TRADE_SURFACE);
    expect(TRADE_SURFACE.canonicalDomain).toBe('entreprises.leopardo.app');
    expect(TRADE_SURFACE.basePath).toBe('/entreprises');
  });
});
