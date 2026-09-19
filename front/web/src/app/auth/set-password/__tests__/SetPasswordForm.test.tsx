import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SetPasswordForm } from '../SetPasswordForm';

/**
 * #7490 — définition du mot de passe via le lien magique de l'e-mail de
 * bienvenue (provisioning_token, usage unique, TTL 72 h).
 *
 * Verrouille : le POST vers le proxy same-origin `/api/forms/trial-password`
 * (le token ne part jamais vers l'API en query), la politique de mot de passe
 * partagée (12 caractères + chiffre), le repli localStorage quand le lien ne
 * porte pas de token, et la mise en mots des codes d'erreur (dont l'expiration
 * 72 h, TRIAL_PASSWORD_LINK_EXPIRED).
 *
 * NB : jsdom expose `navigator.language = en-US` → la copie rendue est celle
 * du catalogue EN (les 4 locales portent les mêmes clés, garde i18n du dépôt).
 */

const VALID_TOKEN = 'a'.repeat(64);

const mockFetch = jest.fn();

// jsdom n'expose pas `Response` : on renvoie la forme minimale consommée par
// le composant ({ ok, json }).
function jsonResponse(payload: unknown, status = 200) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
  } as unknown as Response;
}

beforeEach(() => {
  jest.clearAllMocks();
  global.fetch = mockFetch as unknown as typeof fetch;
  window.localStorage.clear();
});

async function fillAndSubmit(password: string, confirmation = password) {
  await userEvent.type(screen.getByLabelText(/new password/i), password);
  await userEvent.type(screen.getByLabelText(/confirm password/i), confirmation);
  await userEvent.click(screen.getByRole('button', { name: /set my password/i }));
}

describe('SetPasswordForm (#7490)', () => {
  it('affiche l’état « lien manquant » sans token (URL et localStorage vides)', () => {
    render(<SetPasswordForm tokenFromUrl="" />);

    expect(screen.getByText(/incomplete or expired link/i)).toBeInTheDocument();
    expect(screen.queryByLabelText(/new password/i)).not.toBeInTheDocument();
  });

  it('retombe sur le token du navigateur (localStorage) quand l’URL n’en porte pas', () => {
    window.localStorage.setItem('lp_trial_provisioning_token', VALID_TOKEN);

    render(<SetPasswordForm tokenFromUrl="" />);

    expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
  });

  it('poste vers le proxy same-origin et affiche le succès', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse({ success: true, data: { login_url: '/auth/login' } }, 200),
    );

    render(<SetPasswordForm tokenFromUrl={VALID_TOKEN} />);
    await fillAndSubmit('correct-horse-42-battery');

    await waitFor(() => expect(screen.getByText(/password saved/i)).toBeInTheDocument());

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/forms/trial-password',
      expect.objectContaining({ method: 'POST' }),
    );
    const body = JSON.parse((mockFetch.mock.calls[0][1] as RequestInit).body as string);
    expect(body.token).toBe(VALID_TOKEN);
    expect(body.password).toBe('correct-horse-42-battery');
  });

  it('refuse localement un mot de passe trop faible (aucun appel réseau)', async () => {
    render(<SetPasswordForm tokenFromUrl={VALID_TOKEN} />);
    await fillAndSubmit('court1');

    expect(await screen.findByRole('alert')).toHaveTextContent(/too weak/i);
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('refuse localement une confirmation différente (aucun appel réseau)', async () => {
    render(<SetPasswordForm tokenFromUrl={VALID_TOKEN} />);
    await fillAndSubmit('correct-horse-42-battery', 'autre-mot-de-passe-42');

    expect(await screen.findByRole('alert')).toHaveTextContent(/do not match/i);
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('met en mots l’expiration du lien (TRIAL_PASSWORD_LINK_EXPIRED, TTL 72 h)', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse({ success: false, error: 'TRIAL_PASSWORD_LINK_EXPIRED' }, 410),
    );

    render(<SetPasswordForm tokenFromUrl={VALID_TOKEN} />);
    await fillAndSubmit('correct-horse-42-battery');

    expect(await screen.findByRole('alert')).toHaveTextContent(/expired/i);
  });

  it('met en mots « mot de passe déjà défini » (usage unique du token)', async () => {
    mockFetch.mockResolvedValueOnce(
      jsonResponse({ success: false, error: 'TRIAL_PASSWORD_ALREADY_SET' }, 409),
    );

    render(<SetPasswordForm tokenFromUrl={VALID_TOKEN} />);
    await fillAndSubmit('correct-horse-42-battery');

    expect(await screen.findByRole('alert')).toHaveTextContent(/already been set/i);
  });
});
