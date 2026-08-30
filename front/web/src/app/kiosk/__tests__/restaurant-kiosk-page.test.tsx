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
  data: [
    {
      id: 1,
      name: 'Plats',
      sort_order: 1,
      products: [
        {
          id: 101,
          code: 'BURGER-XL',
          name: 'Burger XL',
          description: 'Double steak',
          price_minor: 3500,
          currency: 'XAF',
          image_asset_id: null,
        },
        {
          id: 102,
          code: 'SALADE',
          name: 'Salade César',
          description: null,
          price_minor: 2500,
          currency: 'XAF',
          image_asset_id: null,
        },
      ],
    },
  ],
};

const branches = { data: [{ id: 1, code: 'MAIN', name: 'Branche Centrale' }] };

describe('RestaurantKioskPage (RESTO-807)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...window.location, search: '?token=rshop_test' },
    });
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(menu)).mockResolvedValueOnce(jsonResponse(branches));
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

    const addButtons = screen.getAllByLabelText('Ajouter');
    await userEvent.click(addButtons[0]);

    expect(screen.getByText('1 articles')).toBeInTheDocument();
    expect(screen.getByText('35.00 XAF')).toBeInTheDocument();
  });

  it('passe une commande complète et paie en espèces', async () => {
    const order = {
      data: {
        reference: 'RST-KIOSK1',
        status: 'draft',
        total_minor: 3500,
        currency: 'XAF',
        subtotal_minor: 3500,
        tax_minor: 0,
      },
    };
    const payment = { data: { id: 1, status: 'confirmed' } };

    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse(menu))
      .mockResolvedValueOnce(jsonResponse(branches))
      .mockResolvedValueOnce(jsonResponse(order))
      .mockResolvedValueOnce(jsonResponse(payment));

    render(<RestaurantKioskPage />);

    await screen.findByText('Burger XL');
    await userEvent.click(screen.getAllByLabelText('Ajouter')[0]);
    await userEvent.click(screen.getByText('Valider la commande'));

    await waitFor(() => {
      expect(screen.getByText('Commande envoyée en cuisine !')).toBeInTheDocument();
      expect(screen.getByText('RST-KIOSK1')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByText('Payer en espèces'));

    await waitFor(() => {
      expect(screen.getByText('Paiement confirmé. Bon appétit !')).toBeInTheDocument();
    });
  });

  it('affiche une erreur explicite si le jeton est absent', async () => {
    Object.defineProperty(window, 'location', {
      writable: true,
      value: { ...window.location, search: '' },
    });

    render(<RestaurantKioskPage />);

    await waitFor(() => {
      expect(screen.getByText('Jeton de boutique invalide ou absent.')).toBeInTheDocument();
    });
  });
});
