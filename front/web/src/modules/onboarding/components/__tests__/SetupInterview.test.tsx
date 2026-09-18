import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import type { StoredAuthUser } from '@/lib/i18n';
import { SetupInterview, shouldShowSetupInterview } from '../SetupInterview';

/**
 * #7493 — entretien de préparation conversationnel.
 *
 * Verrouille : une seule question visible à la fois, « Passer » par question,
 * « Terminer plus tard » persisté côté SERVEUR (POST dismiss), l'ordre
 * adaptatif (solo → pas de questions d'équipe), la reprise depuis le brouillon
 * serveur, et la clôture qui affiche le récapitulatif humain des activations.
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

// framer-motion : rendu synchrone en test (pas d'animations).
jest.mock('framer-motion', () => ({
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  motion: new Proxy(
    {},
    {
      get: (_target, element) => {
        const Component = ({ children, ...props }: Record<string, unknown> & { children?: React.ReactNode }) => {
          const { initial, animate, exit, transition, ...rest } = props;
          void initial; void animate; void exit; void transition;
          const Tag = String(element) as 'div';
          return <Tag {...(rest as object)}>{children}</Tag>;
        };
        return Component;
      },
    },
  ),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const principal: StoredAuthUser = {
  role: 'manager',
  manager_role: 'principal',
  company: { id: 'company-1', metadata: {} },
};

function jsonResponse(data: unknown, ok = true, status = 200) {
  return { ok, status, json: async () => ({ data }) } as unknown as Response;
}

function mockInitialState(state: Record<string, unknown> = { status: 'not_started', answers: {} }) {
  mockedApiFetch.mockImplementation(async (path: string) => {
    if (path === '/setup-interview') {
      return jsonResponse(state);
    }
    if (path === '/setup-interview/complete') {
      return jsonResponse({
        status: 'completed',
        activated: { solutions: ['restaurant'], tools: ['employees', 'attendance'] },
      });
    }
    return jsonResponse({});
  });
}

beforeEach(() => {
  jest.clearAllMocks();
});

describe('shouldShowSetupInterview (#7493)', () => {
  it('propose l’entretien à un responsable dont l’espace n’est pas configuré', () => {
    expect(shouldShowSetupInterview(principal)).toBe(true);
  });

  it('miroir de la garde serveur : ni employé, ni session sans société', () => {
    expect(shouldShowSetupInterview(null)).toBe(false);
    expect(shouldShowSetupInterview({ ...principal, role: 'employee', manager_role: null })).toBe(false);
    expect(shouldShowSetupInterview({ role: 'manager', manager_role: 'principal' })).toBe(false);
  });

  it('ne redéclenche rien après complétion, report ou onboarding terminé', () => {
    expect(
      shouldShowSetupInterview({
        ...principal,
        company: { metadata: { setup_interview: { status: 'completed' } } },
      }),
    ).toBe(false);
    expect(
      shouldShowSetupInterview({
        ...principal,
        company: { metadata: { setup_interview: { status: 'dismissed' } } },
      }),
    ).toBe(false);
    expect(
      shouldShowSetupInterview({
        ...principal,
        company: { metadata: { onboarding_completed: true } },
      }),
    ).toBe(false);
  });
});

describe('SetupInterview (#7493)', () => {
  it('affiche UNE seule question à la fois, avec Passer et Terminer plus tard', async () => {
    mockInitialState();
    render(<SetupInterview locale="fr" onClose={jest.fn()} />);

    await waitFor(() => expect(screen.getByTestId('interview-question')).toBeInTheDocument());
    // Une seule question rendue.
    expect(screen.getAllByTestId('interview-question')).toHaveLength(1);
    expect(screen.getByText('Travaillez-vous seul(e) ou avec une équipe ?')).toBeInTheDocument();
    expect(screen.getByTestId('interview-skip')).toBeInTheDocument();
    expect(screen.getByTestId('interview-later')).toBeInTheDocument();
  });

  it('enregistre chaque réponse en brouillon SERVEUR et avance', async () => {
    mockInitialState();
    render(<SetupInterview locale="fr" onClose={jest.fn()} />);

    await waitFor(() => expect(screen.getByTestId('interview-option-team')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('interview-option-team'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/setup-interview/answers', {
        method: 'PATCH',
        body: JSON.stringify({ answers: { company_type: 'team' } }),
      }),
    );
    // Question suivante (taille d'équipe) affichée.
    expect(screen.getByText('Combien de personnes travaillent avec vous ?')).toBeInTheDocument();
  });

  it('ordre adaptatif : un(e) solo ne voit jamais les questions d’équipe', async () => {
    mockInitialState();
    render(<SetupInterview locale="fr" onClose={jest.fn()} />);

    await waitFor(() => expect(screen.getByTestId('interview-option-solo')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('interview-option-solo'));

    // La question de taille d'équipe est court-circuitée → activité directe.
    await waitFor(() =>
      expect(screen.getByText('Quelle est votre activité ?')).toBeInTheDocument(),
    );
    expect(screen.queryByText('Combien de personnes travaillent avec vous ?')).not.toBeInTheDocument();
  });

  it('« Terminer plus tard » persiste le report côté serveur puis ferme', async () => {
    mockInitialState();
    const onClose = jest.fn();
    render(<SetupInterview locale="fr" onClose={onClose} />);

    await waitFor(() => expect(screen.getByTestId('interview-later')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('interview-later'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/setup-interview/dismiss', { method: 'POST' }),
    );
    expect(onClose).toHaveBeenCalledWith('dismissed');
  });

  it('reprend depuis le brouillon serveur (reprise sur un autre appareil)', async () => {
    mockInitialState({
      status: 'in_progress',
      answers: { company_type: 'team', team_size: '11-50' },
    });
    render(<SetupInterview locale="fr" onClose={jest.fn()} />);

    // Les deux premières questions sont déjà répondues → activité.
    await waitFor(() =>
      expect(screen.getByText('Quelle est votre activité ?')).toBeInTheDocument(),
    );
  });

  it('à la fin du parcours, complete est appelé et le récapitulatif humain s’affiche', async () => {
    // Solo : parcours court (type, activité, lieux, priorités).
    mockInitialState({
      status: 'in_progress',
      answers: { company_type: 'solo', sector: 'restaurant', premises: 'single' },
    });
    const onClose = jest.fn();
    render(<SetupInterview locale="fr" onClose={onClose} />);

    // Dernière question visible : les priorités (multi-choix).
    await waitFor(() =>
      expect(screen.getByTestId('interview-multi-continue')).toBeInTheDocument(),
    );
    await userEvent.click(screen.getByTestId('interview-option-showcase'));
    await userEvent.click(screen.getByTestId('interview-multi-continue'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/setup-interview/complete', { method: 'POST' }),
    );
    await waitFor(() => expect(screen.getByTestId('interview-recap')).toBeInTheDocument());
    // Récapitulatif lisible — aucun jargon technique, des noms humains.
    expect(screen.getByText('Moteur restaurant')).toBeInTheDocument();
    expect(screen.getByText('Gestion des employés')).toBeInTheDocument();

    await userEvent.click(screen.getByTestId('interview-recap-cta'));
    expect(onClose).toHaveBeenCalledWith('completed');
  });

  it('un entretien déjà clôturé côté serveur ferme immédiatement le parcours', async () => {
    mockInitialState({ status: 'completed', answers: {} });
    const onClose = jest.fn();
    render(<SetupInterview locale="fr" onClose={onClose} />);

    await waitFor(() => expect(onClose).toHaveBeenCalledWith('closed'));
  });
});
