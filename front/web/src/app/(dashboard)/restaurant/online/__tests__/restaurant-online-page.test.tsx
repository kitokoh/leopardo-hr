import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantOnlinePage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, ok = true, status = 200): Response {
  return { json: async () => payload, ok, status } as unknown as Response;
}

const branches = {
  data: [
    { id: 1, code: 'BR-001', name: 'Branche Centrale', city: 'Abidjan' },
    { id: 2, code: 'BR-002', name: 'Branche Nord', city: 'Bouaké' },
  ],
};

const profile = {
  data: {
    id: 1,
    name: 'Branche Centrale',
    is_public: true,
    public_slug: 'branche-centrale',
    establishment_type: 'brasserie',
    cuisine_types: ['ivoirienne', 'grillades'],
    public_description: 'Une brasserie au centre-ville.',
    cover_image_url: null,
    latitude: 5.34,
    longitude: -4.02,
  },
};

const products = {
  data: [
    { id: 101, code: 'PLAT-1', name: 'Poulet braisé', branch_id: 1, price_minor: 5000, is_available: true, is_published_online: false },
    { id: 102, code: 'PLAT-2', name: 'Salade maison', branch_id: null, price_minor: 3000, is_available: true, is_published_online: true },
    { id: 103, code: 'PLAT-3', name: 'Pizza Nord', branch_id: 2, price_minor: 7000, is_available: true, is_published_online: false },
  ],
};

function mockRoutes(overrides: Record<string, (init?: RequestInit) => Response | Promise<Response>> = {}) {
  mockedApiFetch.mockImplementation((endpoint: string, init?: RequestInit) => {
    for (const [prefix, handler] of Object.entries(overrides)) {
      if (endpoint.startsWith(prefix)) return Promise.resolve(handler(init));
    }
    if (endpoint.startsWith('/restaurant/branches?')) return Promise.resolve(jsonResponse(branches));
    if (endpoint.includes('/public-profile')) return Promise.resolve(jsonResponse(profile));
    if (endpoint.startsWith('/restaurant/products?')) return Promise.resolve(jsonResponse(products));
    if (endpoint.startsWith('/restaurant/reviews')) return Promise.reject(new Error('network'));
    return Promise.resolve(jsonResponse({ data: [] }));
  });
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

it('affiche le profil public de la première branche et ses plats', async () => {
  mockRoutes();
  render(<RestaurantOnlinePage />);

  await waitFor(() => {
    expect(screen.getByDisplayValue('branche-centrale')).toBeInTheDocument();
  });

  // Sélecteur de branche (plus de saisie d'ID brute)
  expect(screen.getByRole('option', { name: /Branche Centrale/ })).toBeInTheDocument();
  expect(screen.getByRole('option', { name: /Branche Nord/ })).toBeInTheDocument();

  // URL publique dérivée du slug
  expect(screen.getByText('/restaurants/branche-centrale')).toBeInTheDocument();

  // Plats de la branche 1 + plats toutes-branches (branch_id null), pas ceux de la branche 2
  expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  expect(screen.getByText('Salade maison')).toBeInTheDocument();
  expect(screen.queryByText('Pizza Nord')).not.toBeInTheDocument();

  // Avis : endpoints RESTO-902 absents → état « module à venir »
  expect(screen.getByText(/RESTO-902/)).toBeInTheDocument();
});

it('bascule la publication d\'un plat (PATCH) avec état optimiste', async () => {
  const patchCalls: { endpoint: string; body: unknown }[] = [];
  mockedApiFetch.mockImplementation((endpoint: string, init?: RequestInit) => {
    if (endpoint.startsWith('/restaurant/branches?')) return Promise.resolve(jsonResponse(branches));
    if (endpoint.includes('/public-profile')) return Promise.resolve(jsonResponse(profile));
    if (endpoint.startsWith('/restaurant/products?')) return Promise.resolve(jsonResponse(products));
    if (endpoint.includes('/publication')) {
      patchCalls.push({ endpoint, body: JSON.parse(String(init?.body)) });
      return Promise.resolve(jsonResponse({ data: {} }));
    }
    return Promise.reject(new Error('network'));
  });

  render(<RestaurantOnlinePage />);
  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  const toggles = screen.getAllByRole('switch');
  expect(toggles[0]).toHaveTextContent('Non publié');

  await userEvent.click(toggles[0]);

  // État optimiste immédiat + appel PATCH sur le bon produit
  expect(toggles[0]).toHaveTextContent('Publié');
  await waitFor(() => {
    expect(patchCalls).toHaveLength(1);
  });
  expect(patchCalls[0].endpoint).toBe('/restaurant/products/101/publication');
  expect(patchCalls[0].body).toEqual({ is_published_online: true });
});

it('rétablit l\'état du plat si le PATCH échoue (rollback)', async () => {
  mockRoutes({
    '/restaurant/products/101/publication': () => jsonResponse({}, false, 500),
  });

  render(<RestaurantOnlinePage />);
  await waitFor(() => {
    expect(screen.getByText('Poulet braisé')).toBeInTheDocument();
  });

  const toggles = screen.getAllByRole('switch');
  await userEvent.click(toggles[0]);

  await waitFor(() => {
    expect(toggles[0]).toHaveTextContent('Non publié');
  });
  expect(screen.getByText(/état rétabli/)).toBeInTheDocument();
});

it('enregistre le profil public (PUT) avec les champs du contrat', async () => {
  const putCalls: { endpoint: string; body: Record<string, unknown> }[] = [];
  mockRoutes({
    '/restaurant/branches/1/public-profile': (init?: RequestInit) => {
      if (init?.method === 'PUT') {
        putCalls.push({ endpoint: '/restaurant/branches/1/public-profile', body: JSON.parse(String(init.body)) });
        return jsonResponse({ data: { ...profile.data, public_slug: 'chez-leo' } });
      }
      return jsonResponse(profile);
    },
  });

  render(<RestaurantOnlinePage />);
  await waitFor(() => {
    expect(screen.getByDisplayValue('branche-centrale')).toBeInTheDocument();
  });

  const slugInput = screen.getByLabelText('Slug public');
  await userEvent.clear(slugInput);
  await userEvent.type(slugInput, 'chez-leo');

  await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

  await waitFor(() => {
    expect(putCalls).toHaveLength(1);
  });
  expect(putCalls[0].body).toMatchObject({
    is_public: true,
    public_slug: 'chez-leo',
    establishment_type: 'brasserie',
    cuisine_types: ['ivoirienne', 'grillades'],
    latitude: 5.34,
    longitude: -4.02,
  });
  expect(screen.getByText('Profil enregistré.')).toBeInTheDocument();
  // Le formulaire reflète la réponse du PUT
  expect(screen.getByText('/restaurants/chez-leo')).toBeInTheDocument();
});

it('remplit latitude/longitude via « Utiliser ma position »', async () => {
  mockRoutes();
  const getCurrentPosition = jest.fn((success: PositionCallback) =>
    success({ coords: { latitude: 6.8276228, longitude: -5.2893433 } } as GeolocationPosition),
  );
  Object.defineProperty(global.navigator, 'geolocation', {
    value: { getCurrentPosition },
    configurable: true,
  });

  render(<RestaurantOnlinePage />);
  await waitFor(() => {
    expect(screen.getByDisplayValue('branche-centrale')).toBeInTheDocument();
  });

  await userEvent.click(screen.getByRole('button', { name: /Utiliser ma position/ }));

  expect(getCurrentPosition).toHaveBeenCalled();
  expect(screen.getByLabelText('Latitude')).toHaveValue(6.827623);
  expect(screen.getByLabelText('Longitude')).toHaveValue(-5.289343);
});
