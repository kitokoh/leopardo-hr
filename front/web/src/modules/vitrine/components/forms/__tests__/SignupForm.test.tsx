import React from 'react';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SignupForm } from '../SignupForm';
import { submitSignupForm, fetchTrialStatus } from '@/modules/vitrine/lib/forms';

// ─────────────────────────────────────────────────────────────────────────────
// framer-motion neutralisé (test déterministe, issue constat QA 2026-08-15) :
// 1. `AnimatePresence mode="wait"` ne monte l'étape suivante qu'après la sortie
//    animée (0,3 s) pilotée par RAF. framer-motion capture la VRAIE
//    requestAnimationFrame au chargement du module : les fake timers ne la
//    pilotent pas → le test « polls pending → ready » échouait de façon
//    NON DÉTERMINISTE sur main (parfois l'animation se terminait en temps réel,
//    parfois non).
// 2. `motion.<tag>` rend l'élément DOM réel du tag (div, input, select, …) :
//    le design system (Input → motion.input, Select → motion.select) doit
//    produire de vrais contrôles pour les requêtes getByRole/getAllByRole.
// ─────────────────────────────────────────────────────────────────────────────
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

// Mock the form submission
jest.mock('@/modules/vitrine/lib/forms', () => ({
  submitSignupForm: jest.fn(),
  submitVerifyForm: jest.fn(),
  fetchTrialStatus: jest.fn(),
  getLeadSource: () => 'signup_form',
  localeDefaultCountry: (locale?: string) => 'FR',
  initialFormState: {
    isSubmitting: false,
    isSuccess: false,
    isError: false,
    message: '',
    errors: {},
  },
  createFormReducer: () => (state: any, action: any) => {
    switch (action.type) {
      case 'SUBMIT_START':
        return { ...state, isSubmitting: true };
      case 'SUBMIT_SUCCESS':
        return { ...state, isSubmitting: false, isSuccess: true, message: action.payload?.message ?? '' };
      case 'SUBMIT_ERROR':
        return { ...state, isSubmitting: false, isError: true, message: action.payload?.message ?? '' };
      case 'RESET':
        return { ...state, isSubmitting: false };
      default:
        return state;
    }
  },
}));

const mockedSubmitSignupForm = submitSignupForm as jest.Mock;

// The component is localized via useVitrineLocale(); the test environment
// defaults to navigator.language (en-US). Pin the locale to French so the
// assertions on the FR copy stay deterministic (issue #2648).
beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});


describe('SignupForm Component', () => {
  describe('Rendering', () => {
    it('should render signup form', () => {
      render(<SignupForm />);
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should render email input', () => {
      render(<SignupForm />);
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should render company input', () => {
      render(<SignupForm />);
      expect(screen.getByRole('textbox', { name: /entreprise/i })).toBeInTheDocument();
    });

    it('should render submit button', () => {
      render(<SignupForm />);
      expect(screen.getByRole('button', { name: /recevoir mon code de vérification/i })).toBeInTheDocument();
    });
  });

  describe('Country field (#4476)', () => {
    it('renders a country select with a locale-derived default', async () => {
      render(<SignupForm page="/signup" />);
      const countrySelect = await screen.findByLabelText(/Pays|Country|Ülke|البلد/);
      expect(countrySelect).toBeInTheDocument();
      expect((countrySelect as HTMLSelectElement).value).toBe('FR');
    });
  });

  describe('Form Validation', () => {
    it('should show error for invalid email', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'invalid-email');
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));
      
      await waitFor(() => {
        expect(screen.getByText(/email invalide|valid email/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty email', async () => {
      render(<SignupForm />);
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de vérification/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/email (invalide|trop court)/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty company', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de vérification/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/entreprise doit contenir/i)).toBeInTheDocument();
      });
    });

    it('should show error for invalid phone', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
      await userEvent.type(screen.getByRole('textbox', { name: /téléphone/i }), 'not-a-phone');

      const submitButton = screen.getByRole('button', { name: /recevoir mon code de vérification/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/numéro de téléphone invalide/i)).toBeInTheDocument();
      });
    });
  });

  describe('Form Submission', () => {
    it('should accept valid trial request fields', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');

      const submitButton = screen.getByRole('button', { name: /recevoir mon code de vérification/i });
      expect(submitButton).not.toBeDisabled();
    });
  });

  describe('Accessibility', () => {
    it('should have accessible form labels', () => {
      render(<SignupForm />);
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should be keyboard navigable', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      emailInput.focus();
      expect(emailInput).toHaveFocus();
      
      await userEvent.tab();
      const nextElement = document.activeElement;
      expect(nextElement).not.toBe(emailInput);
    });

    it('should have proper form structure', () => {
      const { container } = render(<SignupForm />);
      const form = container.querySelector('form');
      expect(form).toBeInTheDocument();
    });
  });

  describe('Cold-start fallback (PA2-MKT-002)', () => {
    beforeEach(() => {
      mockedSubmitSignupForm.mockReset();
    });

    async function fillValidForm() {
      await userEvent.type(screen.getByRole('textbox', { name: /email/i }), 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
      const selects = screen.getAllByRole('combobox');
      // employees AVANT role : SignupForm fait `watch('role')` — la sélection
      // du rôle re-rend le composant et (avec le mock framer-motion, commits
      // synchrones) peut orpheliner la référence du 2e select → la valeur
      // « employees » était perdue. L'ordre des champs n'a pas d'importance
      // métier : on remplit employees en premier pour un test déterministe.
      await userEvent.selectOptions(selects[1], '1-10');
      await userEvent.selectOptions(selects[0], 'founder');
      await userEvent.click(screen.getByRole('checkbox'));
    }

    it('shows the "we will contact you" pending screen instead of a fake OTP step when provisioned is false', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: false,
        message: "Demande d'essai reçue. Notre équipe vous contacte sous 24h ouvrables.",
        data: { nextStep: 'contact_under_24h' },
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /demande d'essai reçue/i })).toBeInTheDocument();
      });
      expect(screen.getByText(/notre équipe vous contacte sous 24h ouvrables/i)).toBeInTheDocument();
      expect(screen.queryByText(/vérifiez votre email/i)).not.toBeInTheDocument();
    });

    it('shows the OTP verification step when provisioned is true (default backend path)', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
    });
  });

  describe('Guided trial tracking (#2469)', () => {
    beforeEach(() => {
      mockedSubmitSignupForm.mockReset();
      (fetchTrialStatus as jest.Mock).mockReset();
      sessionStorage.clear();
    });

    async function fillValidForm() {
      await userEvent.type(screen.getByRole('textbox', { name: /email/i }), 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
      const selects = screen.getAllByRole('combobox');
      // employees AVANT role : SignupForm fait `watch('role')` — la sélection
      // du rôle re-rend le composant et (avec le mock framer-motion, commits
      // synchrones) peut orpheliner la référence du 2e select → la valeur
      // « employees » était perdue. L'ordre des champs n'a pas d'importance
      // métier : on remplit employees en premier pour un test déterministe.
      await userEvent.selectOptions(selects[1], '1-10');
      await userEvent.selectOptions(selects[0], 'founder');
      await userEvent.click(screen.getByRole('checkbox'));
    }

    it('shows the "Suivre l\'etat de mon espace" link on the OTP screen when a provisioning token is returned', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: { provisioning_token: 'a'.repeat(64) },
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.getByRole('button', { name: /suivre l'état de mon espace/i })).toBeInTheDocument();
    });

    it('does not show the tracking link without a provisioning token', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de vérification envoyé.',
        data: {},
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));

      await waitFor(() => {
        expect(screen.getByText(/vérifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.queryByRole('button', { name: /suivre l'état de mon espace/i })).not.toBeInTheDocument();
    });

    it('polls pending → ready and shows the access link', async () => {
      jest.useFakeTimers();
      const user = userEvent.setup({ advanceTimers: jest.advanceTimersByTime });
      try {
        mockedSubmitSignupForm.mockResolvedValue({
          success: true,
          provisioned: true,
          message: 'Code de vérification envoyé.',
          data: { provisioning_token: 'b'.repeat(64) },
        });
        (fetchTrialStatus as jest.Mock)
          .mockResolvedValueOnce({ success: true, data: { status: 'pending' } })
          .mockResolvedValueOnce({
            success: true,
            data: { status: 'ready', login_url: 'https://demo.leopardo.app/access?t=123' },
          });

        render(<SignupForm />);
        await user.type(screen.getByRole('textbox', { name: /email/i }), 'test@example.com');
        await user.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
        const selects = screen.getAllByRole('combobox');
        await user.selectOptions(selects[1], '1-10');
        await user.selectOptions(selects[0], 'founder');
        // fireEvent (et non user.click) : avec jest.useFakeTimers() actif au
        // milieu de la suite, les clicks userEvent sont intermittemment avalés
        // (désynchronisation pointerup/click par l'avancement des timers) —
        // échec non déterministe sur main. fireEvent est synchrone.
        fireEvent.click(screen.getByRole('checkbox'));
        fireEvent.click(screen.getByRole('button', { name: /recevoir mon code de vérification/i }));
        await screen.findByText(/vérifiez votre email/i);

        fireEvent.click(screen.getByRole('button', { name: /suivre l'état de mon espace/i }));

        // premier poll immédiat : pending → spinner
        expect(await screen.findByText(/préparation de votre espace/i)).toBeInTheDocument();

        // second poll après 5 s : ready → lien d'accès
        await act(async () => {
          jest.advanceTimersByTime(5000);
        });
        expect(await screen.findByText(/votre espace est prêt/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /accéder à mon espace/i })).toHaveAttribute(
          'href',
          'https://demo.leopardo.app/access?t=123'
        );
      } finally {
        jest.useRealTimers();
      }
    });
  });

  describe('User Interactions', () => {
    it('should clear form after successful submission', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i }) as HTMLInputElement;
      await userEvent.type(emailInput, 'test@example.com');
      
      expect(emailInput.value).toBe('test@example.com');
    });

    it('should show loading state during submission', async () => {
      render(<SignupForm />);
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de vérification/i });
      
      expect(submitButton).not.toHaveAttribute('disabled');
    });
  });
});
