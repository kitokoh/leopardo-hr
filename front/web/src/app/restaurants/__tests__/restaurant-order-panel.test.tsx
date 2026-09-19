import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import RestaurantOrderPanel from '../[slug]/restaurant-order-panel';
import type { PublicMenuCategory, PublicReview } from '@/lib/restaurants-public-api';

/**
 * RESTO-903 (#7748) — page publique /restaurants/{slug} : menu → panier →
 * commande idempotente (mockée) → paiement + formulaire d'avis post-commande
 * (order_ref pré-rempli, statut pending côté serveur).
 */

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return { json: async () => payload, ok: true, status } as unknown as Response;
}

const menu: PublicMenuCategory[] = [
  {
    name: 'Plats',
    products: [
      {
        code: 'BURGER-XL',
        name: 'Burger XL',
        description: 'Double steak',
        price_minor: 3500,
        currency: 'XAF',
      },
      {
        code: 'SALADE',
        name: 'Salade César',
        description: null,
        price_minor: 2500,
        currency: 'XAF',
      },
    ],
  },
  {
    name: 'Boissons',
    products: [
      { code: 'JUS', name: 'Jus de bissap', description: null, price_minor: 1000, currency: 'XAF' },
    ],
  },
];

const reviews: PublicReview[] = [
  { author_name: 'Awa', rating: 5, comment: 'Excellent !', date: '2026-09-18' },
];
const reviewsMeta = { current_page: 1, last_page: 1, per_page: 10, total: 1 };

function renderPanel() {
  return render(
    <RestaurantOrderPanel
      locale="fr"
      slug="chez-fatou"
      menu={menu}
      initialReviews={reviews}
      initialReviewsMeta={reviewsMeta}
    />,
  );
}

describe('RestaurantOrderPanel (RESTO-903 #7748)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('rend le menu par catégories et les avis initiaux (SSR props)', () => {
    renderPanel();

    expect(screen.getByRole('button', { name: 'Plats' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Boissons' })).toBeInTheDocument();
    expect(screen.getByText('Burger XL')).toBeInTheDocument();
    expect(screen.getByText('Jus de bissap')).toBeInTheDocument();
    expect(screen.getByText('Awa')).toBeInTheDocument();
    expect(screen.getByText('Excellent !')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('passe une commande complète : panier → coordonnées → POST idempotent → confirmation', async () => {
    let orderBody: Record<string, unknown> | null = null;
    mockedApiFetch.mockImplementation(async (endpoint: string, options?: RequestInit) => {
      if (endpoint === '/public/restaurants/chez-fatou/orders' && options?.method === 'POST') {
        orderBody = JSON.parse(String(options.body)) as Record<string, unknown>;
        return jsonResponse({
          data: {
            reference: 'RST-PUB1',
            status: 'draft',
            order_type: 'takeaway',
            subtotal_minor: 7000,
            tax_minor: 0,
            total_minor: 7000,
            currency: 'XAF',
            items_count: 1,
            created: true,
          },
        });
      }
      if (endpoint === '/public/restaurants/chez-fatou/orders/RST-PUB1') {
        return jsonResponse({
          data: {
            reference: 'RST-PUB1',
            status: 'open',
            order_type: 'takeaway',
            subtotal_minor: 7000,
            tax_minor: 0,
            total_minor: 7000,
            currency: 'XAF',
            items: [
              { name: 'Burger XL', quantity: 2, unit_price_minor: 3500, line_total_minor: 7000 },
            ],
            updated_at: '2026-09-20T10:00:00Z',
          },
        });
      }
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });

    renderPanel();

    await userEvent.click(screen.getByLabelText('Ajouter Burger XL'));
    // Quantité +1 depuis le panier.
    await userEvent.click(screen.getAllByLabelText('Ajouter Burger XL')[1]);
    await userEvent.type(screen.getByLabelText('Note pour cet article (optionnel) — Burger XL'), 'sans oignons');

    await userEvent.type(screen.getByLabelText('Nom'), 'Awa Diop');
    await userEvent.type(screen.getByLabelText('Téléphone'), '+237600000000');
    await userEvent.click(screen.getByRole('button', { name: /Valider la commande/ }));

    await waitFor(() => {
      expect(screen.getByText('RST-PUB1')).toBeInTheDocument();
    });
    expect(screen.getByText('Commande confirmée')).toBeInTheDocument();

    expect(orderBody).not.toBeNull();
    const body = orderBody as unknown as {
      customer_name: string;
      customer_phone: string;
      order_type: string;
      idempotency_key: string;
      items: { product_code: string; quantity: number; note?: string }[];
    };
    expect(body.customer_name).toBe('Awa Diop');
    expect(body.customer_phone).toBe('+237600000000');
    expect(body.order_type).toBe('pickup');
    expect(typeof body.idempotency_key).toBe('string');
    expect(body.idempotency_key.length).toBeGreaterThan(0);
    expect(body.items).toEqual([{ product_code: 'BURGER-XL', quantity: 2, note: 'sans oignons' }]);

    // Suivi chargé après la commande.
    await waitFor(() => {
      expect(screen.getByText('2 × Burger XL')).toBeInTheDocument();
    });

    // Paiement — cash / mobile money uniquement (jamais de carte).
    expect(screen.getByRole('button', { name: 'Espèces à la réception' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mobile money' })).toBeInTheDocument();
    expect(screen.queryByText(/carte/i)).not.toBeInTheDocument();

    // Formulaire d'avis : order_ref pré-rempli avec la référence.
    expect(screen.getByLabelText(/Référence de commande/)).toHaveValue('RST-PUB1');
  });

  it('exige nom et téléphone avant de commander', async () => {
    renderPanel();

    await userEvent.click(screen.getByLabelText('Ajouter Burger XL'));
    await userEvent.click(screen.getByRole('button', { name: /Valider la commande/ }));

    expect(await screen.findByText('Indiquez votre nom et votre numéro de téléphone.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('soumet un avis et affiche la confirmation « en attente de modération »', async () => {
    let reviewBody: Record<string, unknown> | null = null;
    mockedApiFetch.mockImplementation(async (endpoint: string, options?: RequestInit) => {
      if (endpoint === '/public/restaurants/chez-fatou/reviews' && options?.method === 'POST') {
        reviewBody = JSON.parse(String(options.body)) as Record<string, unknown>;
        return jsonResponse({ data: { status: 'pending' } }, 201);
      }
      throw new Error(`Unexpected endpoint: ${endpoint}`);
    });

    renderPanel();

    await userEvent.type(screen.getByLabelText(/Référence de commande/), 'RST-PUB1');
    await userEvent.type(screen.getByLabelText('Votre nom'), 'Awa');
    await userEvent.click(screen.getByRole('radio', { name: 'Note 4' }));
    await userEvent.type(screen.getByLabelText('Commentaire (optionnel)'), 'Très bon accueil');
    await userEvent.click(screen.getByRole('button', { name: "Envoyer l'avis" }));

    await waitFor(() => {
      expect(screen.getByText('Avis soumis, en attente de modération.')).toBeInTheDocument();
    });
    expect(reviewBody).toEqual({
      order_ref: 'RST-PUB1',
      rating: 4,
      comment: 'Très bon accueil',
      author_name: 'Awa',
    });
  });

  it('affiche le message doublon (409) du formulaire d’avis', async () => {
    mockedApiFetch.mockRejectedValue(Object.assign(new Error('409'), { status: 409 }));

    renderPanel();

    await userEvent.type(screen.getByLabelText(/Référence de commande/), 'RST-PUB1');
    await userEvent.type(screen.getByLabelText('Votre nom'), 'Awa');
    await userEvent.click(screen.getByRole('button', { name: "Envoyer l'avis" }));

    await waitFor(() => {
      expect(screen.getByText('Un avis a déjà été déposé pour cette commande.')).toBeInTheDocument();
    });
  });
});
