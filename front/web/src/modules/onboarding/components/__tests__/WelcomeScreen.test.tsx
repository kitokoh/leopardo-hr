import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import type { StoredAuthUser } from '@/lib/i18n';
import { WelcomeScreen, shouldShowFirstLoginWelcome } from '../WelcomeScreen';

/**
 * #7604 — écran de bienvenue de première connexion (tranche du critère 2 de
 * #7490 : « affiché UNE fois, persisté côté serveur, pas en localStorage »).
 *
 * Verrouille : la garde d'affichage (miroir exact du RBAC serveur, sinon écran
 * qui revient à chaque connexion après un 403), l'acquittement par les DEUX
 * issues (non bloquant), un seul POST, et la direction d'échec sûre.
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const principal: StoredAuthUser = {
  role: 'manager',
  manager_role: 'principal',
  company: { id: 'company-1', metadata: {} },
};

function acknowledgeResponse(seenAt = '2026-09-16T12:00:00+00:00') {
  return {
    ok: true,
    json: async () => ({ data: { welcome_seen_at: seenAt, already_acknowledged: false } }),
  } as unknown as Response;
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('shouldShowFirstLoginWelcome (#7604)', () => {
  it('affiche l’écran quand la date d’acquittement est absente ou vide', () => {
    expect(shouldShowFirstLoginWelcome(principal)).toBe(true);
    expect(
      shouldShowFirstLoginWelcome({ ...principal, company: { metadata: { welcome_seen_at: '' } } }),
    ).toBe(true);
  });

  it('ne réaffiche plus rien une fois acquitté côté serveur (autre appareil, rechargement)', () => {
    expect(
      shouldShowFirstLoginWelcome({
        ...principal,
        company: { metadata: { welcome_seen_at: '2026-09-16T10:00:00+00:00' } },
      }),
    ).toBe(false);
  });

  it('miroir de la garde serveur : ni employé, ni comptable, ni session sans société', () => {
    expect(shouldShowFirstLoginWelcome(null)).toBe(false);
    expect(shouldShowFirstLoginWelcome(undefined)).toBe(false);
    // Un employé n'acquitte pas l'écran de son entreprise (403 côté API).
    expect(
      shouldShowFirstLoginWelcome({ ...principal, role: 'employee', manager_role: null }),
    ).toBe(false);
    // Un comptable recevrait 403 : on ne montre pas un écran inacquittable.
    expect(shouldShowFirstLoginWelcome({ ...principal, manager_role: 'comptable' })).toBe(false);
    expect(
      shouldShowFirstLoginWelcome({ role: 'manager', manager_role: 'principal' }),
    ).toBe(false);
  });

  it('ne dépend PAS du localStorage (la source de vérité est company.metadata)', () => {
    // Même utilisateur, même stockage local, deux états serveur différents.
    window.localStorage.clear();
    expect(shouldShowFirstLoginWelcome(principal)).toBe(true);
    expect(
      shouldShowFirstLoginWelcome({
        ...principal,
        company: { metadata: { welcome_seen_at: '2026-09-16T10:00:00+00:00' } },
      }),
    ).toBe(false);
  });
});

describe('WelcomeScreen', () => {
  it('rend un dialogue accessible avec la copie localisée', () => {
    render(<WelcomeScreen locale="fr" onAcknowledged={jest.fn()} />);

    expect(screen.getByRole('dialog')).toHaveAttribute('aria-modal', 'true');
    expect(screen.getByText('Votre espace est prêt 🎉')).toBeInTheDocument();
    expect(
      screen.getByText(/un lien pour définir votre mot de passe/i),
    ).toBeInTheDocument();
  });

  it('acquitte via POST et remonte la date SERVEUR (CTA principal → définir le mot de passe)', async () => {
    mockedApiFetch.mockResolvedValue(acknowledgeResponse());
    const onAcknowledged = jest.fn();

    render(<WelcomeScreen locale="fr" onAcknowledged={onAcknowledged} />);
    await userEvent.click(screen.getByRole('button', { name: 'Définir mon mot de passe maintenant' }));

    await waitFor(() =>
      expect(onAcknowledged).toHaveBeenCalledWith('2026-09-16T12:00:00+00:00', 'set_password'),
    );
    expect(mockedApiFetch).toHaveBeenCalledTimes(1);
    expect(mockedApiFetch).toHaveBeenCalledWith('/onboarding/welcome-ack', { method: 'POST' });
  });

  it('« Plus tard » acquitte AUSSI l’écran (non bloquant), en un seul appel', async () => {
    mockedApiFetch.mockResolvedValue(acknowledgeResponse());
    const onAcknowledged = jest.fn();

    render(<WelcomeScreen locale="fr" onAcknowledged={onAcknowledged} />);
    await userEvent.click(screen.getByRole('button', { name: 'Plus tard' }));

    await waitFor(() =>
      expect(onAcknowledged).toHaveBeenCalledWith('2026-09-16T12:00:00+00:00', 'later'),
    );
    expect(mockedApiFetch).toHaveBeenCalledTimes(1);
  });

  it('ne séquestre pas l’utilisateur si l’acquittement échoue (direction d’échec sûre)', async () => {
    mockedApiFetch.mockRejectedValue(new Error('offline'));
    const onAcknowledged = jest.fn();

    render(<WelcomeScreen locale="fr" onAcknowledged={onAcknowledged} />);
    await userEvent.click(screen.getByRole('button', { name: 'Plus tard' }));

    // L'écran se ferme quand même : au pire il se réaffichera à la prochaine
    // connexion (le drapeau serveur n'a pas été posé), jamais il ne bloque.
    await waitFor(() => expect(onAcknowledged).toHaveBeenCalledTimes(1));
    expect(onAcknowledged.mock.calls[0][1]).toBe('later');
    expect(typeof onAcknowledged.mock.calls[0][0]).toBe('string');
  });

  it('ne double pas l’acquittement en cas de double clic', async () => {
    mockedApiFetch.mockResolvedValue(acknowledgeResponse());
    const onAcknowledged = jest.fn();

    render(<WelcomeScreen locale="fr" onAcknowledged={onAcknowledged} />);

    const start = screen.getByRole('button', { name: 'Définir mon mot de passe maintenant' });
    await userEvent.click(start);
    await userEvent.click(start);

    await waitFor(() => expect(onAcknowledged).toHaveBeenCalledTimes(1));
    expect(mockedApiFetch).toHaveBeenCalledTimes(1);
  });
});
