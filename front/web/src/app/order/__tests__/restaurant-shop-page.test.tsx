import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantShopPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown): Response {
  return { json: async () => payload, ok: true, status: 200 } as unknown as Response;
}

const menu = {
  data: {
    categories: [{ id: 1, name: 'Plats' }],
    products: [
      {
        id: 101,
        code: 'BURGER-XL',
        name: 'Burger XL',
        description: 'Double steak',
        price_minor: 3500,
        currency: 'XAF',
        category_id: 1,
        available: true,
      },
      {
        id: 102,
        code: 'SALADE',
        name: 'Salade César',
        description: null,
        price_minor: 2500,
        currency: 'XAF',
        category_id: 1,
        available: true,
      },
    ],
    pagination: { per_page: 50, total: 2 },
  },
};

describe('RestaurantShopPage (RESTO-805-front #6404)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.localStorage.setItem('preferred_locale', 'fr');
    window.history.pushState({}, '', '/order?token=rshop_test');
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      if (endpoint === '/public/restaurant/shop/menu') return jsonResponse(menu);
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });
  });

  it('affiche le menu par catégories', async () => {
    render(<RestaurantShopPage />);

    await waitFor(() => {
      expect(screen.getByText('Plats')).toBeInTheDocument();
    });
    expect(screen.getByText('Burger XL')).toBeInTheDocument();
    expect(screen.getByText('Salade César')).toBeInTheDocument();
    expect(screen.getByText('Commander en ligne')).toBeInTheDocument();
  });

  it('passe une commande complète puis la suit', async () => {
    const order = {
      data: {
        reference: 'RST-SHOP1',
        status: 'draft',
        total_minor: 3500,
        currency: 'XAF',
        created: true,
        track_url: '/api/v1/public/restaurant/shop/orders/RST-SHOP1',
      },
    };
    const track = {
      data: {
        reference: 'RST-SHOP1',
        status: 'open',
        subtotal_minor: 3500,
        tax_minor: 0,
        total_minor: 3500,
        currency: 'XAF',
        items: [{ product_code: 'BURGER-XL', name: 'Burger XL', quantity: 1, line_total_minor: 3500 }],
        updated_at: '2026-08-30T10:00:00Z',
      },
    };

    mockedApiFetch.mockImplementation(async (endpoint: string, options?: RequestInit) => {
      if (endpoint === '/public/restaurant/shop/menu') return jsonResponse(menu);
      if (endpoint === '/public/restaurant/shop/orders' && options?.method === 'POST') {
        return jsonResponse(order);
      }
      if (endpoint === '/public/restaurant/shop/orders/RST-SHOP1' && (!options?.method || options.method === 'GET')) {
        return jsonResponse(track);
      }
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });

    render(<RestaurantShopPage />);

    await screen.findByText('Burger XL');
    await userEvent.click(screen.getAllByLabelText('Ajouter')[0]);
    await userEvent.click(screen.getByText('Valider la commande'));

    await waitFor(() => {
      expect(screen.getByText('Commande enregistrée !')).toBeInTheDocument();
      expect(screen.getByText('RST-SHOP1')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByText('Suivre ma commande'));

    await waitFor(() => {
      expect(screen.getByText('Suivi de commande')).toBeInTheDocument();
      expect(screen.getByText('open')).toBeInTheDocument();
    });
  });

  it('affiche une erreur explicite si le jeton est absent', async () => {
    window.history.pushState({}, '', '/order');

    render(<RestaurantShopPage />);

    await waitFor(() => {
      expect(screen.getByText('Jeton de boutique invalide ou absent.')).toBeInTheDocument();
    });
  });
});
