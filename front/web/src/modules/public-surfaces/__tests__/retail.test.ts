import { displayShopRating, formatCatalogSize, hasCategories, type Shop } from '../retail/domain/shop';
import { toShop } from '../retail/infrastructure/shop-gateway';
import { RETAIL_SURFACE } from '../retail/identity/retail-identity';
import { getSurfaceCopy } from '../copy/surfaces-copy';
import { surfaceRegistry } from '../registry';

const words = getSurfaceCopy('fr').shared;

function shop(overrides: Partial<Shop> = {}): Shop {
  return {
    slug: 'boulangerie',
    name: 'Boulangerie Martin',
    city: 'Nantes',
    description: null,
    currency: 'EUR',
    productsCount: 12,
    rating: 4.6,
    ratingCount: 8,
    categories: [],
    ...overrides,
  };
}

describe('domaine retail — note', () => {
  it('arrondit au dixième', () => {
    expect(displayShopRating(4.64, 3)).toBe(4.6);
  });

  it('renvoie null sans avis', () => {
    expect(displayShopRating(4.6, 0)).toBeNull();
    expect(displayShopRating(null, 4)).toBeNull();
  });
});

describe('domaine retail — catalogue', () => {
  it('accorde le pluriel', () => {
    expect(formatCatalogSize(1, words)).toBe('1 produit');
    expect(formatCatalogSize(12, words)).toBe('12 produits');
  });

  it('nomme le catalogue vide', () => {
    expect(formatCatalogSize(0, words)).toBe('Catalogue vide');
  });

  it('détecte la présence de catégories', () => {
    expect(hasCategories(shop())).toBe(false);
    expect(hasCategories(shop({ categories: [{ name: 'Pains', productsCount: 4 }] }))).toBe(true);
  });
});

describe('adaptateur retail', () => {
  it('traduit la fiche publique vers le domaine', () => {
    const mapped = toShop({
      name: 'Boulangerie Martin',
      slug: 'boulangerie',
      city: 'Nantes',
      description: 'Pain au levain.',
      contact_phone: null,
      contact_email: null,
      currency: 'EUR',
      products_count: 12,
      rating_avg: 4.6,
      rating_count: 8,
      categories: [{ id: 3, name: 'Pains', products_count: 4 }],
    });

    expect(mapped.categories).toEqual([{ name: 'Pains', productsCount: 4 }]);
    expect(mapped.productsCount).toBe(12);
  });
});

describe('identité retail', () => {
  it('est déclarée avec son propre domaine', () => {
    expect(surfaceRegistry.get('retail')).toBe(RETAIL_SURFACE);
    expect(RETAIL_SURFACE.canonicalDomain).toBe('marche.leopardo.app');
    expect(RETAIL_SURFACE.basePath).toBe('/boutiques');
  });
});
