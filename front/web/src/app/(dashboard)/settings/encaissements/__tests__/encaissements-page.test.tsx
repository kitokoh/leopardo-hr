import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { apiFetch } from '@/lib/api-client';
import { AUTH_USER_KEY } from '@/lib/i18n';
import PaymentProfilesPage from '../page';

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

const profilesPayload = {
  data: {
    items: [
      {
        id: 1,
        type: 'stripe_keys',
        label: 'Compte Stripe restaurant',
        status: 'active',
        is_default: true,
        details: {},
        secrets: { secret_key: { configured: true, mask: 'sk_live_••••4242' } },
      },
      {
        id: 2,
        type: 'cash',
        label: 'Caisse comptoir',
        status: 'draft',
        is_default: false,
        details: { location: 'Comptoir principal' },
        secrets: {},
      },
    ],
  },
};

const collectionsPayload = {
  data: {
    items: [
      {
        id: 11,
        amount: 42.5,
        currency: 'EUR',
        method: 'cash',
        note: 'Table 4',
        collected_at: '2026-09-20T12:30:00Z',
      },
      {
        id: 12,
        amount: 18,
        currency: 'EUR',
        method: 'card_terminal',
        note: null,
        collected_at: '2026-09-20T13:00:00Z',
      },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 2 },
  },
};

function mockRoutes() {
  mockedApiFetch.mockImplementation(async (path: string, init?: RequestInit) => {
    if (path.startsWith('/billing/payment-profiles')) {
      return jsonResponse(profilesPayload);
    }
    if (path.startsWith('/billing/collections')) {
      if (init?.method === 'POST') {
        return jsonResponse({ data: { id: 13 } }, 201);
      }
      return jsonResponse(collectionsPayload);
    }
    return jsonResponse({ data: {} });
  });
}

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.setItem('preferred_locale', 'fr');
  window.localStorage.setItem(
    AUTH_USER_KEY,
    JSON.stringify({
      id: 1,
      name: 'Gérant Principal',
      manager_role: 'principal',
      company: { currency: 'EUR' },
    })
  );
});

afterEach(() => {
  window.localStorage.clear();
});

describe('Encaissements — familles + encaissement local (#7863)', () => {
  it('affiche les quatre familles d’encaissement et les profils groupés (dont cash)', async () => {
    mockRoutes();

    render(<PaymentProfilesPage />);

    expect(await screen.findByRole('heading', { name: 'En ligne (Stripe)' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Virement bancaire' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Mobile money' })).toBeInTheDocument();
    expect(
      screen.getByRole('heading', { name: 'Au local (espèces / comptoir)' })
    ).toBeInTheDocument();

    // Profil cash groupé dans sa famille, sans secret.
    expect(screen.getByText('Caisse comptoir')).toBeInTheDocument();
    expect(screen.getByText('Compte Stripe restaurant')).toBeInTheDocument();

    // Familles sans profil : message dédié (bank_account + mobile_money).
    expect(screen.getAllByText('Aucun profil dans cette famille.')).toHaveLength(2);
  });

  it('propose le formulaire de profil cash sans champ secret', async () => {
    mockRoutes();
    const user = userEvent.setup();

    render(<PaymentProfilesPage />);
    await screen.findByRole('heading', { name: 'Au local (espèces / comptoir)' });

    // Bouton « Ajouter » de la famille cash (dernier de la liste).
    const addButtons = screen.getAllByRole('button', { name: 'Ajouter' });
    await user.click(addButtons[addButtons.length - 1]);

    expect(
      await screen.findByText('Aucune clé ni coordonnée à saisir pour ce mode.')
    ).toBeInTheDocument();
    expect(screen.getByText('Point d’encaissement (optionnel)')).toBeInTheDocument();
    // Aucun input de type password (secrets) pour le type cash.
    expect(document.querySelectorAll('input[type="password"]')).toHaveLength(0);
  });

  it('liste les derniers encaissements enregistrés', async () => {
    mockRoutes();

    render(<PaymentProfilesPage />);

    expect(await screen.findByText('Encaissements enregistrés')).toBeInTheDocument();
    expect(await screen.findByText('42.50 EUR')).toBeInTheDocument();
    expect(screen.getByText('18.00 EUR')).toBeInTheDocument();
    expect(screen.getByText('Table 4')).toBeInTheDocument();
    // Une fois dans le badge de la ligne + une fois dans le <select> du mode.
    expect(screen.getAllByText('TPE au comptoir')).toHaveLength(2);
  });

  it('enregistre un encaissement (POST /billing/collections) puis recharge la liste', async () => {
    mockRoutes();
    const user = userEvent.setup();

    render(<PaymentProfilesPage />);
    await screen.findByText('Encaissements enregistrés');

    await user.type(screen.getByLabelText('Montant'), '25.90');
    await user.type(screen.getByLabelText('Note (optionnelle)'), 'Service du midi');
    await user.click(screen.getByRole('button', { name: 'Enregistrer l’encaissement' }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/billing/collections',
        expect.objectContaining({ method: 'POST' })
      );
    });

    const postCall = mockedApiFetch.mock.calls.find(
      ([path, init]) => path === '/billing/collections' && init?.method === 'POST'
    );
    expect(postCall).toBeDefined();
    expect(JSON.parse(String(postCall?.[1]?.body))).toEqual({
      amount: 25.9,
      currency: 'EUR',
      method: 'cash',
      note: 'Service du midi',
    });

    expect(await screen.findByText('Encaissement enregistré.')).toBeInTheDocument();
  });

  it('refuse un montant nul sans appeler l’API', async () => {
    mockRoutes();
    const user = userEvent.setup();

    render(<PaymentProfilesPage />);
    await screen.findByText('Encaissements enregistrés');
    mockedApiFetch.mockClear();

    await user.click(screen.getByRole('button', { name: 'Enregistrer l’encaissement' }));

    expect(await screen.findByText('Saisissez un montant supérieur à zéro.')).toBeInTheDocument();
    expect(
      mockedApiFetch.mock.calls.some(([, init]) => init?.method === 'POST')
    ).toBe(false);
  });

  it('réserve la page au manager principal', () => {
    window.localStorage.setItem(
      AUTH_USER_KEY,
      JSON.stringify({ id: 2, name: 'Employé', manager_role: null })
    );

    render(<PaymentProfilesPage />);

    expect(screen.getByText('Accès réservé')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });
});
