/**
 * Issue #7479 — « /auth/me indisponible après un login réussi ».
 *
 * Constat prod : `POST /auth/login` répond 200 (la session est créée, cookie
 * httpOnly posé), puis `GET /auth/me` répond 500 par intermittence (pooler
 * Postgres / plan mis en cache). L'utilisateur voyait alors un message
 * d'échec de connexion — alors que ses identifiants étaient valides.
 *
 * Ces tests verrouillent le contrat :
 *   1. un échec de `/auth/me` APRÈS un login réussi n'affiche JAMAIS un message
 *      d'identifiants : il affiche l'état « session créée, espace indisponible » ;
 *   2. un bouton de reprise recharge le PROFIL (pas les identifiants) et termine
 *      la connexion (redirection) dès que `/auth/me` répond ;
 *   3. un vrai échec d'identifiants (401 sur `/auth/login`) garde son message.
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const pushMock = jest.fn();

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: pushMock, replace: jest.fn(), prefetch: jest.fn() }),
  useSearchParams: () => new URLSearchParams(''),
}));

jest.mock('next/link', () => {
  const Link = ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
  return Link;
});

const apiFetchMock = jest.fn();
class ApiErrorMock extends Error {
  status: number;
  code?: string;

  constructor(message: string, status: number, code?: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

jest.mock('@/lib/api-client', () => ({
  apiFetch: (...args: unknown[]) => apiFetchMock(...args),
  ApiError: ApiErrorMock,
}));

jest.mock('@/lib/client-analytics', () => ({ trackClientEvent: jest.fn() }));

const storeAuthSessionMock = jest.fn();
jest.mock('@/lib/i18n', () => {
  const actual = jest.requireActual('@/lib/i18n');
  return {
    ...actual,
    storeAuthSession: (...args: unknown[]) => storeAuthSessionMock(...args),
    applyDocumentLocale: jest.fn(),
    storePreferredLocale: jest.fn(),
  };
});

const LoginPage = require('../page').default;

const user = {
  id: 42,
  name: 'Amina',
  email: 'amina@exemple.com',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  company: { id: 7, name: 'Exemple SARL' },
};

const okResponse = (payload: unknown) => ({
  ok: true,
  status: 200,
  json: async () => payload,
});

/**
 * Route les appels de la page : `apiFetch` sert aussi `/demo-users` au montage,
 * donc une file de `mockResolvedValueOnce` se décalerait.
 */
function routeApi(handlers: {
  login: () => unknown;
  me: () => unknown;
}) {
  apiFetchMock.mockImplementation((url: unknown) => {
    const target = String(url);
    if (target.includes('/demo-users')) return Promise.resolve(okResponse({ data: [] }));
    if (target.includes('/auth/login')) return toResponse(handlers.login);
    if (target.includes('/auth/me')) return toResponse(handlers.me);
    return Promise.reject(new Error(`appel inattendu dans ce test : ${target}`));
  });
}

/**
 * `apiFetch` résout une `Response` (dont on lit `.json()`), et REJETTE en cas
 * d'erreur HTTP. Un handler qui renvoie une promesse rejetée doit donc faire
 * rejeter `apiFetch` lui-même — pas un `json()` qui rejette après coup.
 */
function toResponse(handler: () => unknown) {
  try {
    const value = handler();
    if (value instanceof Promise) return value.then((payload) => okResponse(payload));
    return Promise.resolve(okResponse(value));
  } catch (error) {
    return Promise.reject(error);
  }
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

async function submitCredentials() {
  const userEventApi = userEvent.setup();
  // Sélection par identifiant : les libellés/aria-labels du formulaire varient
  // (et « mot de passe » apparaît aussi sur le bouton afficher/masquer).
  const emailInput = document.querySelector('#email-address') as HTMLInputElement;
  const passwordInput = document.querySelector('#password') as HTMLInputElement;
  await userEventApi.type(emailInput, 'amina@exemple.com');
  await userEventApi.type(passwordInput, 'MotDePasse!2026');
  // #7490 : ancré en début de libellé — « Recevoir un code de connexion »
  // (toggle OTP) contient aussi « connexion » et rendait la requête ambiguë.
  await userEventApi.click(screen.getByRole('button', { name: /^(connexion|se connecter|sign in)/i }));
}

describe('#7479 — login réussi puis /auth/me indisponible', () => {
  it("affiche l'état « session créée » au lieu d'un message d'identifiants", async () => {
    routeApi({
      login: () => ({ data: { token: 'ignored-in-cookie' } }),
      me: () => Promise.reject(new ApiErrorMock('An error occurred. Please try again.', 500, 'INTERNAL_ERROR')),
    });

    render(<LoginPage />);
    await submitCredentials();

    const banner = await screen.findByTestId('session-unavailable');
    // Assertion volontairement indépendante de la locale résolue (fr/en/ar/tr) :
    // le contrat est « la session est créée », pas la langue du message.
    expect(banner.textContent).toMatch(/session (was created|a bien été créée)|تم إنشاء جلستك|Oturumunuz olusturuldu/i);
    // Le message dit explicitement que les identifiants ne sont PAS en cause,
    // et n'emprunte jamais la formulation d'un échec d'authentification.
    expect(banner.textContent).toMatch(/identifiants ne sont pas en cause|credentials are not the issue|بيانات الدخول ليست هي السبب|giris bilgilerinizde sorun yok/i);
    expect(banner.textContent).not.toMatch(/incorrect|invalide|ne correspond|invalid credentials/i);
    expect(screen.getByTestId('session-retry')).toBeTruthy();
    // Aucune session incomplète stockée, aucune redirection :
    expect(storeAuthSessionMock).not.toHaveBeenCalled();
    expect(pushMock).not.toHaveBeenCalled();
  });

  it('le bouton de reprise recharge le profil et termine la connexion', async () => {
    let meAttempts = 0;
    routeApi({
      login: () => ({ data: { token: 'ignored-in-cookie' } }),
      me: () => {
        meAttempts += 1;
        return meAttempts === 1
          ? Promise.reject(new ApiErrorMock('Service indisponible', 503, 'SERVICE_UNAVAILABLE'))
          : Promise.resolve({ data: user });
      },
    });

    render(<LoginPage />);
    await submitCredentials();
    await screen.findByTestId('session-unavailable');

    const userEventApi = userEvent.setup();
    await userEventApi.click(screen.getByTestId('session-retry'));

    await waitFor(() => expect(storeAuthSessionMock).toHaveBeenCalled());
    expect(storeAuthSessionMock.mock.calls[0][1]).toMatchObject({ email: 'amina@exemple.com' });
    await waitFor(() => expect(pushMock).toHaveBeenCalled());
    // La reprise n'a pas rappelé /auth/login (aucune re-authentification).
    const loginCalls = apiFetchMock.mock.calls.filter((call) => String(call[0]).includes('/auth/login'));
    expect(loginCalls).toHaveLength(1);
  });

  it("un vrai échec d'identifiants (401) garde son message", async () => {
    routeApi({
      login: () => Promise.reject(
        new ApiErrorMock('Ces identifiants ne correspondent à aucun compte.', 401, 'INVALID_CREDENTIALS'),
      ),
      me: () => ({ data: user }),
    });

    render(<LoginPage />);
    await submitCredentials();

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toMatch(/identifiants/i);
    expect(screen.queryByTestId('session-unavailable')).toBeNull();
    expect(storeAuthSessionMock).not.toHaveBeenCalled();
  });
});
