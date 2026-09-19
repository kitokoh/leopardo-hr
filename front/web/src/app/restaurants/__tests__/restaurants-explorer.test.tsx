import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantsExplorer from '../restaurants-explorer';
import type { PublicRestaurantSummary } from '@/lib/restaurants-public-api';

/**
 * RESTO-903 (#7748) — annuaire public /restaurants : rendu SSR initial
 * (props serveur) + filtres client + « Autour de moi » (géolocalisation).
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown): Response {
  return { json: async () => payload, ok: true, status: 200 } as unknown as Response;
}

const items: PublicRestaurantSummary[] = [
  {
    slug: 'chez-fatou',
    name: 'Chez Fatou',
    establishment_type: 'restaurant',
    cuisine_types: ['africaine'],
    city: 'Douala',
    description: 'Cuisine maison au feu de bois.',
    cover_image_url: null,
    latitude: 4.05,
    longitude: 9.76,
    rating_avg: 4.5,
    reviews_count: 12,
  },
  {
    slug: 'pizza-bella',
    name: 'Pizza Bella',
    establishment_type: 'pizzeria',
    cuisine_types: ['italienne'],
    city: 'Yaoundé',
    description: null,
    cover_image_url: null,
    latitude: null,
    longitude: null,
    rating_avg: null,
    reviews_count: 0,
  },
];

const meta = { current_page: 1, last_page: 1, per_page: 20, total: 2 };
const emptyQuery = { q: '', city: '', type: '', cuisine: '', page: 1 };

describe('RestaurantsExplorer (RESTO-903 #7748)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('rend les cartes SSR initiales (nom, type, ville, note ★ + volume)', () => {
    render(
      <RestaurantsExplorer
        locale="fr"
        initialItems={items}
        initialMeta={meta}
        initialError={false}
        initialQuery={emptyQuery}
      />,
    );

    expect(screen.getByText('Chez Fatou')).toBeInTheDocument();
    expect(screen.getByText('Pizza Bella')).toBeInTheDocument();
    expect(screen.getByText('Douala')).toBeInTheDocument();
    const results = within(screen.getByTestId('restaurant-results'));
    expect(results.getByText('Restaurant')).toBeInTheDocument();
    expect(screen.getByText('4,5 ★')).toBeInTheDocument();
    expect(screen.getByText('(12 avis)')).toBeInTheDocument();
    expect(screen.getByText('Chez Fatou').closest('a')).toHaveAttribute(
      'href',
      '/restaurants/chez-fatou',
    );
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('filtre côté client à la soumission de la recherche', async () => {
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      expect(endpoint).toBe('/public/restaurants?q=pizza');
      return jsonResponse({ data: [items[1]], meta: { ...meta, total: 1 } });
    });

    render(
      <RestaurantsExplorer
        locale="fr"
        initialItems={items}
        initialMeta={meta}
        initialError={false}
        initialQuery={emptyQuery}
      />,
    );

    await userEvent.type(screen.getByPlaceholderText('Nom, spécialité…'), 'pizza');
    await userEvent.click(screen.getByRole('button', { name: 'Rechercher' }));

    await waitFor(() => {
      expect(screen.queryByText('Chez Fatou')).not.toBeInTheDocument();
    });
    expect(screen.getByText('Pizza Bella')).toBeInTheDocument();
  });

  it('« Autour de moi » raffine avec near + radius via la géolocalisation', async () => {
    const getCurrentPosition = jest.fn((success: PositionCallback) =>
      success({
        coords: { latitude: 4.0511, longitude: 9.7679 },
      } as unknown as GeolocationPosition),
    );
    Object.defineProperty(global.navigator, 'geolocation', {
      configurable: true,
      value: { getCurrentPosition },
    });

    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      expect(endpoint).toContain('near=4.05110%2C9.76790');
      expect(endpoint).toContain('radius_km=10');
      return jsonResponse({ data: [{ ...items[0], distance_km: 1.2 }], meta: { ...meta, total: 1 } });
    });

    render(
      <RestaurantsExplorer
        locale="fr"
        initialItems={items}
        initialMeta={meta}
        initialError={false}
        initialQuery={emptyQuery}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: 'Autour de moi' }));

    await waitFor(() => {
      expect(screen.getByText('Résultats autour de votre position')).toBeInTheDocument();
    });
    expect(screen.getByText('1,2 km')).toBeInTheDocument();
  });

  it('affiche un état vide propre sans résultats', () => {
    render(
      <RestaurantsExplorer
        locale="fr"
        initialItems={[]}
        initialMeta={{ ...meta, total: 0 }}
        initialError={false}
        initialQuery={emptyQuery}
      />,
    );

    expect(screen.getByText('Aucun restaurant ne correspond à votre recherche.')).toBeInTheDocument();
  });

  it('affiche une erreur avec bouton réessayer quand le SSR a échoué', () => {
    render(
      <RestaurantsExplorer
        locale="fr"
        initialItems={[]}
        initialMeta={null}
        initialError
        initialQuery={emptyQuery}
      />,
    );

    expect(screen.getByText('Impossible de charger les restaurants. Réessayez.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });
});
