import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import OrderTrackingPage from '../page';

/**
 * RESTO-903 (#7748) — dédup /order : la page ne porte PLUS le parcours
 * menu/panier/commande (doublon de /shop, supprimé) — uniquement le suivi
 * d'une commande par référence, avec le jeton boutique existant (?token=).
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

// Le setup global mocke useSearchParams à vide — ici on relit l'URL du test
// (deep-links ?token= / ?ref= posés par window.history.pushState).
jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useSearchParams: () => new URLSearchParams(window.location.search),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown): Response {
  return { json: async () => payload, ok: true, status: 200 } as unknown as Response;
}

const track = {
  data: {
    reference: 'RST-TRACK1',
    status: 'preparing',
    subtotal_minor: 3500,
    tax_minor: 0,
    total_minor: 3500,
    currency: 'XAF',
    items: [{ product_code: 'BURGER-XL', name: 'Burger XL', quantity: 1, line_total_minor: 3500 }],
    updated_at: '2026-09-20T10:00:00Z',
  },
};

describe('OrderTrackingPage (RESTO-903 #7748 — dédup /order, suivi seul)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.localStorage.setItem('preferred_locale', 'fr');
    window.history.pushState({}, '', '/order?token=rshop_test');
  });

  it('ne rend PLUS le parcours menu/panier (suivi uniquement)', async () => {
    render(<OrderTrackingPage />);

    expect(screen.getByText('Suivi de commande')).toBeInTheDocument();
    expect(screen.getByText('Référence de commande')).toBeInTheDocument();
    // Le doublon du parcours de commande a disparu.
    expect(screen.queryByText('Panier')).not.toBeInTheDocument();
    expect(screen.queryByText('Valider la commande')).not.toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('suit une commande par référence saisie (jeton du deep-link conservé)', async () => {
    mockedApiFetch.mockImplementation(async (endpoint: string, options?: RequestInit) => {
      if (endpoint === '/public/restaurant/shop/orders/RST-TRACK1') {
        expect(new Headers(options?.headers).get('X-Restaurant-Shop-Token')).toBe('rshop_test');
        return jsonResponse(track);
      }
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });

    render(<OrderTrackingPage />);

    await userEvent.type(screen.getByLabelText('Référence de commande'), 'RST-TRACK1');
    await userEvent.click(screen.getByRole('button', { name: /Suivre ma commande/ }));

    await waitFor(() => {
      expect(screen.getByText('RST-TRACK1')).toBeInTheDocument();
    });
    expect(screen.getByText('preparing')).toBeInTheDocument();
    expect(screen.getByText('1 × Burger XL')).toBeInTheDocument();
  });

  it('déclenche le suivi immédiat via le deep-link ?ref= (compat)', async () => {
    window.history.pushState({}, '', '/order?token=rshop_test&ref=RST-TRACK1');
    mockedApiFetch.mockResolvedValue(jsonResponse(track));

    render(<OrderTrackingPage />);

    await waitFor(() => {
      expect(screen.getByText('RST-TRACK1')).toBeInTheDocument();
    });
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/public/restaurant/shop/orders/RST-TRACK1',
      expect.objectContaining({ _cacheBust: true }),
    );
  });

  it('affiche une erreur explicite si le jeton est absent', async () => {
    window.history.pushState({}, '', '/order?ref=RST-TRACK1');

    render(<OrderTrackingPage />);

    await waitFor(() => {
      expect(screen.getByText('Jeton de boutique invalide ou absent.')).toBeInTheDocument();
    });
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('affiche « commande introuvable » sur 404', async () => {
    window.history.pushState({}, '', '/order?token=rshop_test');
    mockedApiFetch.mockRejectedValue(Object.assign(new Error('404'), { status: 404 }));

    render(<OrderTrackingPage />);

    await userEvent.type(screen.getByLabelText('Référence de commande'), 'RST-NOPE');
    await userEvent.click(screen.getByRole('button', { name: /Suivre ma commande/ }));

    await waitFor(() => {
      expect(screen.getByText('Commande introuvable. Vérifiez la référence.')).toBeInTheDocument();
    });
  });
});
