import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch, ApiError } from '@/lib/api-client';
import LoginPage from '../page';

/**
 * Issue #7479 — « GET /auth/me répond 500 par intermittence en production ».
 *
 * Constat mesuré : `POST /api/v1/auth/login` répond 200 (la session est créée),
 * puis `GET /api/v1/auth/me` répond 500. L'UI présentait alors l'échec générique
 * « réessayez », c'est-à-dire un ÉCHEC D'IDENTIFIANTS, alors que les identifiants
 * étaient valides : un blocage d'accès sans indication utile.
 *
 * Ce test verrouille le critère 3 de l'issue : une connexion valide ne se solde
 * JAMAIS par un message d'échec d'identifiants, même quand le profil est
 * indisponible.
 */

jest.mock('@/lib/api-client', () => {
  const actual = jest.requireActual('@/lib/api-client');
  return { ...actual, apiFetch: jest.fn() };
});

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn(), replace: jest.fn(), refresh: jest.fn() }),
  useSearchParams: () => new URLSearchParams(''),
}));

jest.mock('@/modules/vitrine/components/GoogleAuthButton', () => ({
  GoogleAuthButton: () => null,
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

type FakeResponse = { ok: boolean; status: number; json: () => Promise<unknown> };

const jsonResponse = (status: number, body: unknown): FakeResponse => ({
  ok: status >= 200 && status < 300,
  status,
  json: async () => body,
});

/**
 * `POST /auth/login` réussit (200), `GET /auth/me` échoue (500).
 * Tout autre appel (ex. `/demo-users` au montage) reste neutre.
 */
function mockLoginSucceedsThenProfileFails(profileStatus: number, profileCode?: string) {
  mockedApiFetch.mockImplementation((async (path: string) => {
    if (path === '/auth/login') {
      return jsonResponse(200, { data: { token: 'cookie-httpOnly' } });
    }
    if (path === '/auth/me') {
      throw new ApiError('An error occurred. Please try again.', profileStatus, profileCode);
    }
    return jsonResponse(200, { data: { companies: [] } });
  }) as typeof apiFetch);
}

/** Remplit les deux champs par leur `id` (les libellés matchent plusieurs
 * éléments — le bouton œil porte « password » dans son aria-label). */
async function submitCredentials(container: HTMLElement) {
  await userEvent.type(
    screen.getByPlaceholderText('manager@company.com'),
    'manager@company.com',
  );
  await userEvent.type(
    container.querySelector('#password') as HTMLInputElement,
    'MotDePasse123!',
  );
  await userEvent.click(container.querySelector('button[type="submit"]') as HTMLButtonElement);
}

describe('connexion réussie puis /auth/me indisponible (#7479)', () => {
  beforeEach(() => {
    mockedApiFetch.mockReset();
    window.localStorage.clear();
    // Les événements de mesure survivent d'un test à l'autre (le store est
    // global à la fenêtre) : on repart d'un journal vide pour pouvoir asserter
    // sur les seuls événements du scénario courant.
    window.__LEOPARDO_ANALYTICS_EVENTS__ = [];
  });

  it("affiche un incident de SERVICE, jamais un échec d'identifiants", async () => {
    mockLoginSucceedsThenProfileFails(500);

    const { container } = render(<LoginPage />);
    await submitCredentials(container);

    const alert = await screen.findByRole('alert');
    const message = alert.textContent ?? '';

    // Le message dit que la connexion a réussi et que le service est indisponible.
    expect(message).toMatch(/réussi|succeeded|نجح|basarili/i);
    expect(message).toMatch(/indisponible|unavailable|kullanilamiyor|مؤقتا/i);

    // Il ne présente PAS l'échec comme un problème d'identifiants.
    expect(message).not.toMatch(/identifiants (incorrects|invalides)/i);
    expect(message).not.toMatch(/invalid credentials/i);

    // Le formulaire n'est pas resté bloqué en état « connexion en cours ».
    await waitFor(() => {
      const submit = container.querySelector('button[type="submit"]') as HTMLButtonElement;
      expect(submit).toBeEnabled();
    });
  });

  it('distingue le motif réel : le statut HTTP de /auth/me est journalisé', async () => {
    mockLoginSucceedsThenProfileFails(503, 'SERVICE_UNAVAILABLE');

    const { container } = render(<LoginPage />);
    await submitCredentials(container);

    await screen.findByRole('alert');

    // L'événement d'échec d'identifiants n'est PAS émis : la mesure ne doit pas
    // compter un incident de service comme une connexion refusée.
    const events = window.__LEOPARDO_ANALYTICS_EVENTS__ ?? [];
    const names = events.map((event) => event.name);
    expect(names).toContain('login_profile_unavailable');
    expect(names).not.toContain('login_failed');

    const unavailable = events.find((event) => event.name === 'login_profile_unavailable');
    expect(unavailable?.properties.status).toBe(503);
    expect(unavailable?.properties.code).toBe('SERVICE_UNAVAILABLE');
  });
});
