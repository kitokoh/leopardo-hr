import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ApiError, apiFetch } from '@/lib/api-client';
import AccountPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;

    constructor(message: string, status = 400, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/** Session de l'utilisateur connecté (fallback local avant GET /auth/me). */
const sessionUser = {
  id: 1,
  first_name: 'Nadia',
  last_name: 'Bensalem',
  email: 'nadia@acme.dz',
  phone: '+213555000111',
  role: 'manager',
  language: 'fr',
  company: { id: 7, name: 'Acme' },
};

/** Payload frais renvoyé par GET /auth/me (source de vérité du formulaire). */
const mePayload = {
  data: {
    ...sessionUser,
    first_name: 'Nadia',
    last_name: 'Bensalem',
    phone: '+213555000111',
  },
};

const subscriptionPayload = {
  data: { id: 1, plan: 'pilot', status: 'active' },
};

type RouteOverrides = {
  subscriptionError?: InstanceType<typeof ApiError>;
};

/** Mock apiFetch par route (profil, 2FA, abonnement, mutations). */
function mockApiRoutes(overrides: RouteOverrides = {}) {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url === '/auth/me' && method === 'GET') {
      return { json: async () => mePayload } as Response;
    }

    if (url === '/auth/2fa/status' && method === 'GET') {
      return { json: async () => ({ data: { enabled: false } }) } as Response;
    }

    if (url === '/billing/subscription' && method === 'GET') {
      if (overrides.subscriptionError) {
        throw overrides.subscriptionError;
      }
      return { json: async () => subscriptionPayload } as Response;
    }

    if (url === '/auth/profile' && method === 'PATCH') {
      const body = JSON.parse(String(options?.body)) as Record<string, unknown>;
      return {
        json: async () => ({ data: { ...mePayload.data, ...body } }),
      } as Response;
    }

    if (url === '/auth/change-password' && method === 'POST') {
      return { json: async () => ({ data: {} }) } as Response;
    }

    return { json: async () => ({ data: null }) } as Response;
  });
}

/** Corps JSON d'un appel de mutation capturé par le mock. */
function bodyOf(url: string, method: string): Record<string, unknown> {
  const call = mockedApiFetch.mock.calls.find(
    ([calledUrl, calledOptions]) =>
      calledUrl === url && (calledOptions as RequestInit | undefined)?.method === method,
  );

  expect(call).toBeDefined();

  return JSON.parse(String((call?.[1] as RequestInit).body)) as Record<string, unknown>;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.setItem('auth_user', JSON.stringify(sessionUser));
  mockApiRoutes();
});

describe('AccountPage (#7861) — profil éditable', () => {
  it('pré-remplit le formulaire depuis GET /auth/me (prénom, nom, téléphone, email verrouillé)', async () => {
    render(<AccountPage />);

    await waitFor(() =>
      expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'),
    );
    expect(screen.getByTestId('account-last-name')).toHaveValue('Bensalem');
    expect(screen.getByTestId('account-phone')).toHaveValue('+213555000111');
    expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me');

    // L'email est affiché mais NON modifiable (identifiant de connexion).
    const email = screen.getByDisplayValue('nadia@acme.dz');
    expect(email).toBeDisabled();
  });

  it('sauvegarde via PATCH /auth/profile et resynchronise la session locale', async () => {
    render(<AccountPage />);
    await waitFor(() => expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'));

    const firstName = screen.getByTestId('account-first-name');
    await userEvent.clear(firstName);
    await userEvent.type(firstName, 'Nadia-Amel');
    await userEvent.click(screen.getByTestId('account-save-profile'));

    await waitFor(() => expect(screen.getByText(/Profil mis à jour/)).toBeInTheDocument());

    expect(bodyOf('/auth/profile', 'PATCH')).toEqual({
      first_name: 'Nadia-Amel',
      last_name: 'Bensalem',
      phone: '+213555000111',
    });

    // storeAuthSession → le nom affiché dans la barre se rafraîchit.
    const stored = JSON.parse(window.localStorage.getItem('auth_user') ?? '{}') as {
      first_name?: string;
    };
    expect(stored.first_name).toBe('Nadia-Amel');
  });

  it('affiche une erreur accessible si le PATCH échoue', async () => {
    render(<AccountPage />);
    await waitFor(() => expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'));

    mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
      if (url === '/auth/profile' && (options?.method ?? 'GET') === 'PATCH') {
        throw new ApiError('Validation échouée', 422);
      }
      return { json: async () => ({ data: null }) } as Response;
    });

    await userEvent.click(screen.getByTestId('account-save-profile'));

    expect(await screen.findByRole('alert')).toHaveTextContent('Validation échouée');
  });
});

describe('AccountPage (#7861) — sécurité', () => {
  it('signale un mismatch de confirmation sans appeler l’API', async () => {
    render(<AccountPage />);
    await waitFor(() => expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'));
    mockedApiFetch.mockClear();

    await userEvent.type(screen.getByLabelText('Mot de passe actuel'), 'ancien-mdp-123');
    await userEvent.type(screen.getByLabelText(/Nouveau mot de passe/), 'nouveau-mdp-123');
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'autre-mdp-456');
    await userEvent.click(screen.getByTestId('account-change-password'));

    expect(await screen.findByRole('alert')).toHaveTextContent('ne correspondent pas');
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/auth/change-password', expect.anything());
  });

  it('change le mot de passe via POST /auth/change-password', async () => {
    render(<AccountPage />);
    await waitFor(() => expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'));

    await userEvent.type(screen.getByLabelText('Mot de passe actuel'), 'ancien-mdp-123');
    await userEvent.type(screen.getByLabelText(/Nouveau mot de passe/), 'nouveau-mdp-123');
    await userEvent.type(screen.getByLabelText('Confirmer le mot de passe'), 'nouveau-mdp-123');
    await userEvent.click(screen.getByTestId('account-change-password'));

    await waitFor(() =>
      expect(screen.getByText(/Mot de passe mis à jour/)).toBeInTheDocument(),
    );

    expect(bodyOf('/auth/change-password', 'POST')).toEqual({
      current_password: 'ancien-mdp-123',
      new_password: 'nouveau-mdp-123',
      new_password_confirmation: 'nouveau-mdp-123',
    });
  });

  it('intègre le panneau 2FA partagé (statut chargé via GET /auth/2fa/status)', async () => {
    render(<AccountPage />);

    expect(await screen.findByTestId('two-factor-panel')).toBeInTheDocument();
    await waitFor(() =>
      expect(screen.getByTestId('two-factor-status-badge')).toHaveTextContent('Désactivé'),
    );
    expect(mockedApiFetch).toHaveBeenCalledWith('/auth/2fa/status');
    expect(screen.getByTestId('two-factor-enroll')).toBeInTheDocument();
  });
});

describe('AccountPage (#7861) — abonnement & facturation', () => {
  it('résume le plan courant (GET /billing/subscription) avec lien vers /billing', async () => {
    render(<AccountPage />);

    expect(await screen.findByTestId('account-billing-card')).toBeInTheDocument();
    expect(screen.getByTestId('account-billing-plan')).toHaveTextContent('Pilot');
    expect(screen.getByTestId('account-billing-link')).toHaveAttribute('href', '/billing');
  });

  it('reste silencieux si /billing/subscription renvoie 403 (accès réservé)', async () => {
    mockApiRoutes({ subscriptionError: new ApiError('Interdit', 403) });

    render(<AccountPage />);
    await waitFor(() => expect(screen.getByTestId('account-first-name')).toHaveValue('Nadia'));

    expect(screen.queryByTestId('account-billing-card')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });
});
