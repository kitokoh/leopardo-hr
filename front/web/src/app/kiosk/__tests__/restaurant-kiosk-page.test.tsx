import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantKioskPage from '../page';

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
    products: [
      { id: 101, code: 'BURGER-XL', name: 'Burger XL', price_minor: 3500, currency: 'XAF', category_id: 1 },
      { id: 102, code: 'SALADE', name: 'Salade César', price_minor: 2500, currency: 'XAF', category_id: 1 },
    ],
    pagination: { per_page: 50, total: 2 },
  },
};

describe('RestaurantKioskPage (RESTO-807-front #6405)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.localStorage.setItem('preferred_locale', 'fr');
    window.history.pushState({}, '', '/kiosk?token=rshop_test');
    mockedApiFetch.mockImplementation(async (endpoint: string) => {
      if (endpoint === '/public/restaurant/kiosk/menu') return jsonResponse(menu);
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });
  });

  it('affiche le menu public après chargement', async () => {
    render(<RestaurantKioskPage />);

    await waitFor(() => {
      expect(screen.getByText('Burger XL')).toBeInTheDocument();
    });
    expect(screen.getByText('Salade César')).toBeInTheDocument();
    expect(screen.getByText('Borne de commande')).toBeInTheDocument();
  });

  it('ajoute un article au panier et met à jour le total', async () => {
    render(<RestaurantKioskPage />);

    await screen.findByText('Burger XL');

    await userEvent.click(screen.getAllByLabelText('Ajouter')[0]);

    expect(screen.getByText('1 articles')).toBeInTheDocument();
    expect(screen.getAllByText('35.00 XAF').length).toBeGreaterThanOrEqual(2);
  });

  it('passe une commande complète et affiche le ticket', async () => {
    const order = {
      data: {
        reference: 'RST-KIOSK1',
        ticket_number: '42',
        status: 'draft',
        total_minor: 3500,
        currency: 'XAF',
        created: true,
      },
    };

    mockedApiFetch.mockImplementation(async (endpoint: string, options?: RequestInit) => {
      if (endpoint === '/public/restaurant/kiosk/menu') return jsonResponse(menu);
      if (endpoint === '/public/restaurant/kiosk/orders' && options?.method === 'POST') {
        return jsonResponse(order);
      }
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });

    render(<RestaurantKioskPage />);

    await screen.findByText('Burger XL');
    await userEvent.click(screen.getAllByLabelText('Ajouter')[0]);
    await userEvent.click(screen.getByText('Valider la commande'));

    await waitFor(() => {
      expect(screen.getByText('Commande envoyée en cuisine !')).toBeInTheDocument();
    });
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('RST-KIOSK1')).toBeInTheDocument();
    expect(screen.getByText(/Réglez à l'encaissement/)).toBeInTheDocument();
  });

  it('affiche une erreur explicite si le jeton est absent', async () => {
    window.history.pushState({}, '', '/kiosk');

    render(<RestaurantKioskPage />);

    await waitFor(() => {
      expect(screen.getByText('Jeton de boutique invalide ou absent.')).toBeInTheDocument();
    });
  });
});
