import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { storeAuthSession, type StoredAuthUser } from '@/lib/i18n';
import { OnboardingWizard } from '../OnboardingWizard';

jest.mock('framer-motion', () => {
  const { createElement, Fragment, forwardRef } = require('react');

  const stripMotionProps = ({
    initial,
    animate,
    exit,
    transition,
    whileFocus,
    whileHover,
    whileTap,
    whileInView,
    variants,
    layout,
    ...rest
  }: any) => rest;

  return {
    AnimatePresence: ({ children }: any) => createElement(Fragment, null, children),
    motion: new Proxy(
      {},
      {
        get: (_target, tag: string) => {
          const MockMotionElement = forwardRef((props: any, ref: any) =>
            createElement(tag, { ...stripMotionProps(props), ref }, props.children)
          );
          MockMotionElement.displayName = `MockMotion${String(tag)}`;
          return MockMotionElement;
        },
      }
    ),
  };
});

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

jest.mock('@/lib/i18n', () => ({
  ...jest.requireActual('@/lib/i18n'),
  storeAuthSession: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const managerUser: StoredAuthUser = {
  id: 1,
  first_name: 'Karim',
  last_name: 'Bensaad',
  email: 'karim@techcorp.dz',
  role: 'manager',
  manager_role: 'principal',
  language: 'fr',
  is_rtl: false,
  company: {
    id: 'company-1',
    name: 'TechCorp',
    language: 'fr',
    timezone: 'Africa/Algiers',
    currency: 'DZD',
    metadata: {},
  },
};

const checklistPayload = {
  data: {
    completed_steps: 0,
    total_steps: 2,
    progress_percent: 0,
    go_live_ready: false,
    steps: [
      {
        id: 1,
        step_key: 'add_employees',
        title: 'Ajouter des employés',
        description: null,
        status: 'pending',
        order: 1,
        required: true,
      },
      {
        id: 2,
        step_key: 'setup_schedules',
        title: 'Définir les horaires',
        description: null,
        status: 'pending',
        order: 2,
        required: false,
      },
    ],
  },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('OnboardingWizard', () => {
  it('renders the REAL backend steps (object shape) with a localized badge', async () => {
    mockedApiFetch.mockResolvedValue({
      json: async () => checklistPayload,
    } as Response);

    render(<OnboardingWizard user={managerUser} onComplete={jest.fn()} />);

    // Titres localisés depuis le copy tree (pas les titres FR seedés du backend).
    expect((await screen.findAllByText('Ajoutez vos équipes')).length).toBeGreaterThan(0);
    expect(screen.getAllByText('Définissez les horaires').length).toBeGreaterThan(0);
    // Badge d'étape localisé (plus de « Étape » en dur).
    expect(screen.getByText('Étape 1 sur 2')).toBeInTheDocument();
  });

  it('completes each real step via PATCH, then marks onboarding finished', async () => {
    mockedApiFetch.mockResolvedValue({
      json: async () => checklistPayload,
    } as Response);

    const onComplete = jest.fn();
    render(<OnboardingWizard user={managerUser} onComplete={onComplete} />);

    await screen.findAllByText('Ajoutez vos équipes');

    // Étape 1 (requise) : « Suivant » → PATCH complete.
    await userEvent.click(screen.getByRole('button', { name: /Suivant/i }));
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/onboarding-setup/add_employees/complete', {
        method: 'PATCH',
      })
    );
    expect(screen.getByText('Étape 2 sur 2')).toBeInTheDocument();

    // Étape 2 (dernière, optionnelle) : « Terminer » → PATCH complete.
    await userEvent.click(screen.getByRole('button', { name: /Terminer/i }));
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/onboarding-setup/setup_schedules/complete', {
        method: 'PATCH',
      })
    );

    // Toutes les étapes réelles sont traitées → état terminé → fermeture + profil local mis à jour.
    expect(await screen.findByText('Configuration terminée !')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: /Terminer/i }));
    expect(storeAuthSession).toHaveBeenCalled();
    expect(onComplete).toHaveBeenCalled();
  });

  it('skips an optional step via PATCH skip', async () => {
    mockedApiFetch.mockResolvedValue({
      json: async () => checklistPayload,
    } as Response);

    render(<OnboardingWizard user={managerUser} onComplete={jest.fn()} />);

    await screen.findAllByText('Ajoutez vos équipes');
    await userEvent.click(screen.getByRole('button', { name: /Suivant/i }));

    await screen.findAllByText('Définissez les horaires');
    await userEvent.click(screen.getByRole('button', { name: /Passer cette étape/i }));
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/onboarding-setup/setup_schedules/skip', {
        method: 'PATCH',
      })
    );
  });

  it('shows a localized error and a retry button when the checklist fails', async () => {
    mockedApiFetch.mockRejectedValueOnce(new Error('network down'));

    render(<OnboardingWizard user={managerUser} onComplete={jest.fn()} />);

    expect(
      await screen.findByText('Impossible de charger les étapes de configuration.')
    ).toBeInTheDocument();

    mockedApiFetch.mockResolvedValueOnce({
      json: async () => checklistPayload,
    } as Response);
    await userEvent.click(screen.getByRole('button', { name: /Réessayer/i }));
    expect((await screen.findAllByText('Ajoutez vos équipes')).length).toBeGreaterThan(0);
  });
});
