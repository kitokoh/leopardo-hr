import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import LoginPage from '../page';

/**
 * Retour propriétaire — l'écran de connexion était servi à un utilisateur
 * POURVU d'une session valide : depuis le même navigateur, on pouvait se
 * reconnecter (« je viens de me connecter et je peux encore me connecter »).
 *
 * La garde ajoutée est volontairement ASYMÉTRIQUE :
 *  - session locale + confirmation `GET /auth/me` → retour au dashboard ;
 *  - session locale seule (cookie périmé, localStorage orphelin) → le
 *    formulaire reste accessible, sinon l'utilisateur serait enfermé dans une
 *    boucle /auth/login → /dashboard → /auth/login.
 */

const pushMock = jest.fn();
const replaceMock = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({
    push: pushMock,
    replace: replaceMock,
    prefetch: jest.fn(),
    back: jest.fn(),
    reload: jest.fn(),
  }),
  usePathname: () => '/auth/login',
  useSearchParams: () => new URLSearchParams(),
}));

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

const storedUser = {
  id: 101,
  first_name: 'Fatima',
  last_name: 'Meziane',
  email: 'fatima.meziane@techcorp-algerie.dz',
  role: 'manager',
  manager_role: 'rh',
  language: 'fr',
  is_rtl: false,
  company: { id: 'company-1', name: 'TechCorp Algerie SARL', metadata: { onboarding_completed: true } },
};

/** `/demo-users` (chargé au montage) et `/auth/me` selon le scénario. */
function mockApi({ me }: { me: 'valid' | 'unauthorized' | 'network' }) {
  mockedApiFetch.mockImplementation(async (endpoint: string) => {
    if (endpoint === '/auth/me') {
      if (me === 'network') throw new Error('Failed to fetch');
      if (me === 'unauthorized') return { ok: false, status: 401, json: async () => ({}) } as Response;
      return { ok: true, status: 200, json: async () => ({ data: storedUser }) } as Response;
    }

    // Mode démo désactivé (production) : silence attendu.
    return { ok: false, status: 404, json: async () => ({}) } as Response;
  });
}

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');
});

describe('/auth/login — un utilisateur déjà connecté', () => {
  it('est renvoyé au dashboard quand la session est confirmée par l’API', async () => {
    window.localStorage.setItem('auth_user', JSON.stringify(storedUser));
    mockApi({ me: 'valid' });

    render(<LoginPage />);

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith('/dashboard'));
  });

  it('n’est PAS redirigé si le cookie de session n’est plus valide (pas de boucle)', async () => {
    window.localStorage.setItem('auth_user', JSON.stringify(storedUser));
    mockApi({ me: 'unauthorized' });

    render(<LoginPage />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));
    expect(pushMock).not.toHaveBeenCalled();
    expect(await screen.findByRole('button', { name: /se connecter/i })).toBeInTheDocument();
  });

  it('n’est PAS redirigé si l’API est injoignable (échec silencieux)', async () => {
    window.localStorage.setItem('auth_user', JSON.stringify(storedUser));
    mockApi({ me: 'network' });

    render(<LoginPage />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));
    expect(pushMock).not.toHaveBeenCalled();
  });
});

describe('/auth/login — visiteur anonyme', () => {
  it('affiche le formulaire sans interroger /auth/me', async () => {
    mockApi({ me: 'valid' });

    render(<LoginPage />);

    expect(await screen.findByRole('button', { name: /se connecter/i })).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/auth/me');
    expect(pushMock).not.toHaveBeenCalled();
  });
});
