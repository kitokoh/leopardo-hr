import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import ShopPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useSearchParams: () => new URLSearchParams(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

const menu = {
  data: {
    categories: [{ id: 1, name: 'Plats' }, { id: 2, name: 'Boissons' }],
    products: [
      { id: 1, code: 'P-01', name: 'Poulet braisé', description: 'Demi poulet', price_minor: 3500, currency: 'XOF', category_id: 1, available: true },
      { id: 2, code: 'D-01', name: 'Jus de bissap', description: null, price_minor: 1000, currency: 'XOF', category_id: 2, available: true },
    ],
  },
};

const TOKEN = 'x'.repeat(48);

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.sessionStorage.clear();
});

describe('Boutique publique (RESTO-805-front)', () => {
  it('charge le menu, ajoute au panier et commande avec idempotency_key', async () => {
    window.sessionStorage.setItem('restaurant_shop_token', TOKEN);
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(menu));

    render(<ShopPage />);

    expect(await screen.findByText('Poulet braisé')).toBeInTheDocument();
    expect(screen.getByText('Jus de bissap')).toBeInTheDocument();

    // Ajout au panier + ouverture panier.
    await userEvent.click(screen.getByLabelText('Ajouter Poulet braisé'));
    await userEvent.click(screen.getByLabelText('Panier (1)'));

    expect(await screen.findByText('Panier')).toBeInTheDocument();
    // Total du panier affiché sur le bouton de commande (XOF sans décimales en CLDR).
    expect(screen.getByText(/Commander/)).toBeInTheDocument();

    // Commande : le header X-Restaurant-Shop-Token est injecté.
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({
      data: { reference: 'RST-ABC', status: 'open', total_minor: 3500, currency: 'XOF', created: true, track_url: '/api/v1/public/restaurant/shop/orders/RST-ABC' },
    }, 201));
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({
      data: { reference: 'RST-ABC', status: 'open', subtotal_minor: 3500, tax_minor: 0, total_minor: 3500, currency: 'XOF', items: [{ product_code: 'P-01', name: 'Poulet braisé', quantity: 1, line_total_minor: 3500 }] },
    }));

    await userEvent.type(screen.getByLabelText('Téléphone (optionnel)'), '+22507000000');
    await userEvent.click(screen.getByText(/Commander/));

    await waitFor(() => {
      const postCall = mockedApiFetch.mock.calls.find(([url]) => String(url).includes('/public/restaurant/shop/orders'));
      expect(postCall).toBeDefined();
      const [endpoint, options] = postCall as [string, RequestInit];
      expect(endpoint).toBe('/public/restaurant/shop/orders');
      expect((options.headers as Headers).get('X-Restaurant-Shop-Token')).toBe(TOKEN);
      const body = JSON.parse(String(options.body));
      expect(body.items).toEqual([{ product_code: 'P-01', quantity: 1 }]);
      expect(body.customer_phone).toBe('+22507000000');
      expect(body.idempotency_key).toMatch(/^.{8,}$/);
    });

    expect(await screen.findByText('Commande confirmée')).toBeInTheDocument();
    expect(screen.getByText('RST-ABC')).toBeInTheDocument();
  });

  it('affiche le jeton manquant sans lien valide', async () => {
    render(<ShopPage />);

    expect(await screen.findByText('Lien de boutique invalide ou manquant. Utilisez le lien fourni par le restaurant.')).toBeInTheDocument();
  });
});
