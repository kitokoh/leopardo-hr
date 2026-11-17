import {
  displayRating,
  formatDistance,
  isEmptyPage,
  sortRestaurants,
  type Restaurant,
} from '../restaurants/domain/restaurant';
import { toRestaurant } from '../restaurants/infrastructure/restaurant-gateway';
import { RESTAURANT_SURFACE } from '../restaurants/identity/restaurant-identity';
import { surfaceRegistry } from '../registry';

function restaurant(overrides: Partial<Restaurant> = {}): Restaurant {
  return {
    slug: 'chez-nous',
    name: 'Chez Nous',
    kind: 'restaurant',
    cuisines: ['française'],
    city: 'Lyon',
    description: null,
    coverImageUrl: null,
    rating: 4.4,
    reviewsCount: 12,
    distanceKm: null,
    ...overrides,
  };
}

describe('domaine restaurant — note', () => {
  it('arrondit au dixième', () => {
    expect(displayRating(4.44, 3)).toBe(4.4);
  });

  it('renvoie null sans avis, jamais 0', () => {
    expect(displayRating(4.4, 0)).toBeNull();
    expect(displayRating(null, 5)).toBeNull();
  });
});

describe('domaine restaurant — distance', () => {
  it('affiche un dixième sous 10 km', () => {
    expect(formatDistance(1.24)).toBe('1.2 km');
  });

  it('arrondit à l’entier au-delà', () => {
    expect(formatDistance(12.6)).toBe('13 km');
  });

  it('renvoie null si inconnue ou invalide', () => {
    expect(formatDistance(null)).toBeNull();
    expect(formatDistance(-1)).toBeNull();
    expect(formatDistance(Number.NaN)).toBeNull();
  });
});

describe('domaine restaurant — tri', () => {
  it('place la proximité connue en premier', () => {
    const sorted = sortRestaurants([
      restaurant({ slug: 'loin', distanceKm: 8 }),
      restaurant({ slug: 'proche', distanceKm: 0.4 }),
    ]);

    expect(sorted.map((r) => r.slug)).toEqual(['proche', 'loin']);
  });

  it('départage par note puis par nom', () => {
    const sorted = sortRestaurants([
      restaurant({ slug: 'b', name: 'Bravo', rating: 4.9 }),
      restaurant({ slug: 'a', name: 'Alpha', rating: 4.9 }),
      restaurant({ slug: 'c', name: 'Charlie', rating: 3.1 }),
    ]);

    expect(sorted.map((r) => r.slug)).toEqual(['a', 'b', 'c']);
  });

  it('ne mute pas l’entrée', () => {
    const input = [restaurant({ slug: 'x' }), restaurant({ slug: 'y' })];
    const before = input.map((r) => r.slug);

    sortRestaurants(input);

    expect(input.map((r) => r.slug)).toEqual(before);
  });
});

describe('domaine restaurant — page vide', () => {
  it('détecte une page sans résultat', () => {
    expect(isEmptyPage({ items: [], page: 1, lastPage: 1, total: 0 })).toBe(true);
    expect(isEmptyPage({ items: [restaurant()], page: 1, lastPage: 1, total: 1 })).toBe(false);
  });
});

describe('adaptateur restaurant', () => {
  it('traduit le DTO public vers le domaine', () => {
    const mapped = toRestaurant({
      slug: 'chez-nous',
      name: 'Chez Nous',
      establishment_type: 'brasserie',
      cuisine_types: ['française', 'bistrot'],
      city: 'Lyon',
      description: 'Une table de quartier.',
      cover_image_url: 'https://cdn/x.jpg',
      latitude: 45.7,
      longitude: 4.8,
      rating_avg: 4.4,
      reviews_count: 12,
    });

    expect(mapped).toEqual({
      slug: 'chez-nous',
      name: 'Chez Nous',
      kind: 'brasserie',
      cuisines: ['française', 'bistrot'],
      city: 'Lyon',
      description: 'Une table de quartier.',
      coverImageUrl: 'https://cdn/x.jpg',
      rating: 4.4,
      reviewsCount: 12,
      distanceKm: null,
    });
  });

  it('normalise les listes absentes', () => {
    const mapped = toRestaurant({
      slug: 's',
      name: 'S',
      establishment_type: null,
      cuisine_types: null,
      city: null,
      description: null,
      cover_image_url: null,
      latitude: null,
      longitude: null,
      rating_avg: null,
      reviews_count: 0,
    });

    expect(mapped.cuisines).toEqual([]);
    expect(mapped.distanceKm).toBeNull();
  });
});

describe('identité restaurant', () => {
  it('est déclarée dans le registre', () => {
    expect(surfaceRegistry.get('restaurant')).toBe(RESTAURANT_SURFACE);
  });

  it('porte son propre domaine et son propre chemin', () => {
    expect(RESTAURANT_SURFACE.canonicalDomain).toBe('restaurants.leopardo.app');
    expect(RESTAURANT_SURFACE.basePath).toBe('/restaurants');
  });

  it('a un CTA lisible (contraste calculé)', () => {
    expect(RESTAURANT_SURFACE.palette.onPrimary).toBe('#FFFFFF');
  });
});
