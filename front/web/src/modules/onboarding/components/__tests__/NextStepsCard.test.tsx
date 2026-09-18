import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { NEXT_STEPS_DISMISS_KEY, NextStepsCard } from '../NextStepsCard';

/**
 * #7494 — carte « Prochaines étapes » du dashboard (fin de la modale à
 * 10 étapes). Verrouille : 3 étapes visibles max + « Tout voir », complétion/
 * saut via les endpoints EXISTANTS (aucune complétion fictive locale),
 * dismiss réversible (pastille), et silence quand tout est prêt.
 */
jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

type Step = {
  step_key: string;
  title: string;
  status: 'pending' | 'completed' | 'skipped';
  order: number;
  required?: boolean;
};

function checklistResponse(steps: Step[], goLiveReady = false) {
  return {
    ok: true,
    status: 200,
    json: async () => ({
      data: {
        completed_steps: steps.filter((s) => s.status !== 'pending').length,
        total_steps: steps.length,
        go_live_ready: goLiveReady,
        employees_count: 3,
        steps,
      },
    }),
  } as unknown as Response;
}

const fiveSteps: Step[] = [
  { step_key: 'company_info', title: 'Infos entreprise', status: 'pending', order: 1, required: true },
  { step_key: 'first_employee', title: 'Premier employé', status: 'pending', order: 2, required: true },
  { step_key: 'configure_schedules', title: 'Horaires', status: 'pending', order: 3, required: true },
  { step_key: 'first_attendance', title: 'Premier pointage', status: 'pending', order: 4, required: true },
  { step_key: 'customize_showcase', title: 'Personnaliser votre site vitrine', status: 'pending', order: 5, required: false },
];

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
});

describe('NextStepsCard (#7494)', () => {
  it('affiche au plus 3 étapes, « Tout voir » révèle le reste', async () => {
    mockedApiFetch.mockResolvedValue(checklistResponse(fiveSteps));
    render(<NextStepsCard locale="fr" />);

    await waitFor(() => expect(screen.getByTestId('next-steps-card')).toBeInTheDocument());
    expect(screen.getByTestId('next-step-company_info')).toBeInTheDocument();
    expect(screen.getByTestId('next-step-configure_schedules')).toBeInTheDocument();
    // 4e et 5e étapes repliées derrière « Tout voir ».
    expect(screen.queryByTestId('next-step-first_attendance')).not.toBeInTheDocument();

    await userEvent.click(screen.getByTestId('next-steps-view-all'));
    expect(screen.getByTestId('next-step-first_attendance')).toBeInTheDocument();
    expect(screen.getByTestId('next-step-customize_showcase')).toBeInTheDocument();
  });

  it('complète une étape via l’endpoint serveur EXISTANT (jamais localement)', async () => {
    mockedApiFetch.mockResolvedValue(checklistResponse(fiveSteps));
    render(<NextStepsCard locale="fr" />);

    await waitFor(() => expect(screen.getByTestId('next-step-company_info')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('next-step-complete-company_info'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/onboarding-setup/company_info/complete', {
        method: 'PATCH',
      }),
    );
  });

  it('ne propose « Passer » que sur les étapes optionnelles', async () => {
    mockedApiFetch.mockResolvedValue(checklistResponse(fiveSteps));
    render(<NextStepsCard locale="fr" />);

    await waitFor(() => expect(screen.getByTestId('next-steps-card')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('next-steps-view-all'));

    expect(screen.queryByTestId('next-step-skip-company_info')).not.toBeInTheDocument();
    expect(screen.getByTestId('next-step-skip-customize_showcase')).toBeInTheDocument();
  });

  it('dismiss réversible : la carte devient une pastille, un clic la restaure', async () => {
    mockedApiFetch.mockResolvedValue(checklistResponse(fiveSteps));
    render(<NextStepsCard locale="fr" />);

    await waitFor(() => expect(screen.getByTestId('next-steps-card')).toBeInTheDocument());
    await userEvent.click(screen.getByTestId('next-steps-dismiss'));

    expect(screen.queryByTestId('next-steps-card')).not.toBeInTheDocument();
    expect(window.localStorage.getItem(NEXT_STEPS_DISMISS_KEY)).toBe('true');

    await userEvent.click(screen.getByTestId('next-steps-restore'));
    expect(screen.getByTestId('next-steps-card')).toBeInTheDocument();
  });

  it('reste muette quand le tenant est prêt (go_live_ready) ou sans étape', async () => {
    mockedApiFetch.mockResolvedValue(checklistResponse(fiveSteps, true));
    const { container } = render(<NextStepsCard locale="fr" />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalled());
    expect(container.querySelector('[data-testid="next-steps-card"]')).toBeNull();
  });
});
