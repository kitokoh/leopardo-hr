import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import BillingPage from '../page';

/**
 * #7764 — section « Crédits IA » de l'espace Facturation.
 *
 * Ce que ce fichier verrouille :
 *  1. la section affiche le solde, les packs achetables et l'historique
 *     retournés par GET /billing/ai-credits ;
 *  2. l'achat d'un pack déclenche POST /billing/ai-credits/checkout et
 *     redirige vers l'URL de paiement retournée ;
 *  3. la section est STRICTEMENT FACULTATIVE : si l'endpoint crédits échoue,
 *     l'abonnement et les factures restent affichés (aucun blocage).
 */

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

const subscription = {
  data: {
    id: 1,
    plan: 'pilot',
    status: 'active',
    current_period_start: '2026-09-01T00:00:00Z',
    current_period_end: '2026-10-01T00:00:00Z',
  },
};

const invoices = { data: [] };

const aiCredits = {
  data: {
    balance: 100_000,
    packs: [
      { code: 's', tokens: 100_000, price_eur_cents: 900 },
      { code: 'm', tokens: 500_000, price_eur_cents: 3_900 },
      { code: 'l', tokens: 2_000_000, price_eur_cents: 12_900 },
    ],
    packs_version: '2026-09-19.1',
    history: [
      { id: 2, delta: -1_000, reason: 'consumption', reference: null, created_at: '2026-09-19T10:00:00Z' },
      { id: 1, delta: 100_000, reason: 'purchase', reference: 'cs_test_1', created_at: '2026-09-18T09:00:00Z' },
    ],
  },
};

function mockBillingCalls({ aiCreditsFails = false } = {}) {
  mockedApiFetch.mockImplementation(async (path: string) => {
    if (path === '/billing/subscription') return jsonResponse(subscription);
    if (path === '/billing/invoices') return jsonResponse(invoices);
    if (path === '/billing/ai-credits') {
      if (aiCreditsFails) throw new Error('ai credits unavailable');
      return jsonResponse(aiCredits);
    }
    if (path === '/billing/ai-credits/checkout') {
      return jsonResponse({ data: { checkout_url: 'https://pay.example/session', session_id: 'cs_test_2' } });
    }
    throw new Error(`Unexpected apiFetch call: ${path}`);
  });
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Section Crédits IA (#7764)', () => {
  it('affiche le solde, les packs et l’historique', async () => {
    mockBillingCalls();

    render(<BillingPage />);

    expect(await screen.findByTestId('ai-credits-section')).toBeInTheDocument();
    expect(screen.getByText('Crédits IA')).toBeInTheDocument();

    // Solde formaté (fr → espace insécable des milliers).
    const balance = screen.getByTestId('ai-credits-balance');
    expect(balance.textContent).toContain('100');
    expect(balance.textContent).toContain('tokens');

    // 3 packs achetables.
    expect(screen.getByRole('button', { name: /Pack S/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Pack M/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Pack L/ })).toBeInTheDocument();

    // Historique : achat + consommation, libellés traduits.
    expect(screen.getByText((content) => content.startsWith('Achat ·'))).toBeInTheDocument();
    expect(screen.getByText((content) => content.startsWith('Consommation ·'))).toBeInTheDocument();
  });

  it('achète un pack via POST /billing/ai-credits/checkout', async () => {
    mockBillingCalls();

    render(<BillingPage />);
    const buyButton = await screen.findByRole('button', { name: /Pack S/ });
    await userEvent.click(buyButton);

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/billing/ai-credits/checkout',
        expect.objectContaining({ method: 'POST' }),
      );
    });

    const checkoutCall = mockedApiFetch.mock.calls.find(([path]) => path === '/billing/ai-credits/checkout');
    expect(checkoutCall).toBeDefined();
    const body = JSON.parse(String((checkoutCall?.[1] as RequestInit).body));
    expect(body.pack).toBe('s');
    expect(body.success_url).toContain('/billing?ai_credits=success');
  });

  it('reste facultative : un échec crédits IA ne bloque ni abonnement ni factures', async () => {
    mockBillingCalls({ aiCreditsFails: true });

    render(<BillingPage />);

    // L'abonnement s'affiche normalement.
    expect(await screen.findByText('Pilot')).toBeInTheDocument();
    // La section est présente avec son message d'erreur localisé, sans casser la page.
    expect(screen.getByTestId('ai-credits-section')).toBeInTheDocument();
    expect(screen.getByText('Impossible de charger les crédits IA.')).toBeInTheDocument();
    // Aucun solde affiché (pas de données inventées).
    expect(screen.queryByTestId('ai-credits-balance')).not.toBeInTheDocument();
  });
});
