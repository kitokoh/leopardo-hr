import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SignupForm } from '../SignupForm';
import { submitSignupForm, fetchTrialStatus } from '@/modules/vitrine/lib/forms';

// Mock the form submission
jest.mock('@/modules/vitrine/lib/forms', () => ({
  submitSignupForm: jest.fn(),
  fetchTrialStatus: jest.fn(),
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
const mockedFetchTrialStatus = fetchTrialStatus as jest.Mock;

async function fillValidForm() {
  await userEvent.type(screen.getByRole('textbox', { name: /email/i }), 'test@example.com');
  await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
  const selects = screen.getAllByRole('combobox');
  await userEvent.selectOptions(selects[0], 'founder');
  await userEvent.selectOptions(selects[1], '1-10');
  await userEvent.click(screen.getByRole('checkbox'));
}

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
      expect(screen.getByRole('button', { name: /recevoir mon code de verification/i })).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('should show error for invalid email', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'invalid-email');
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));
      
      await waitFor(() => {
        expect(screen.getByText(/email invalide|valid email/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty email', async () => {
      render(<SignupForm />);
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/email (invalide|trop court)/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty company', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
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
      await userEvent.type(screen.getByRole('textbox', { name: /telephone/i }), 'not-a-phone');

      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/numero de telephone invalide/i)).toBeInTheDocument();
      });
    });
  });

  describe('Form Submission', () => {
    it('should accept valid trial request fields', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');

      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
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

    it('shows the "we will contact you" pending screen instead of a fake OTP step when provisioned is false', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: false,
        message: "Demande d'essai recue. Notre equipe vous contacte sous 24h ouvrables.",
        data: { nextStep: 'contact_under_24h' },
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /demande d'essai recue/i })).toBeInTheDocument();
      });
      expect(screen.getByText(/notre equipe vous contacte sous 24h ouvrables/i)).toBeInTheDocument();
      expect(screen.queryByText(/verifiez votre email/i)).not.toBeInTheDocument();
    });

    it('shows the OTP verification step when provisioned is true (default backend path)', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de verification envoye.',
        data: {},
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));

      await waitFor(() => {
        expect(screen.getByText(/verifiez votre email/i)).toBeInTheDocument();
      });
    });
  });

  describe('Trial provisioning tracking (#2469)', () => {
    beforeEach(() => {
      mockedFetchTrialStatus.mockReset();
      window.sessionStorage.clear();
    });

    const submitWithToken = async (token = 'a'.repeat(64)) => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de verification envoye.',
        provisioningToken: token,
        data: { provisioningToken: token },
      });
      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));
      await waitFor(() => {
        expect(screen.getByText(/verifiez votre email/i)).toBeInTheDocument();
      });
    };

    it('persists the provisioning token to sessionStorage and shows the tracking link on the OTP step', async () => {
      const token = 'b'.repeat(64);
      await submitWithToken(token);

      expect(window.sessionStorage.getItem('leopardo_trial_token')).toBe(token);
      expect(screen.getByRole('button', { name: /suivre l'etat de mon espace/i })).toBeInTheDocument();
    });

    it('does not show the tracking link when no token was returned', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de verification envoye.',
        data: {},
      });
      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));

      await waitFor(() => {
        expect(screen.getByText(/verifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.queryByRole('button', { name: /suivre l'etat de mon espace/i })).not.toBeInTheDocument();
    });

    it('shows the access link as soon as the first poll returns ready', async () => {
      await submitWithToken();

      mockedFetchTrialStatus.mockResolvedValue({
        success: true,
        data: { status: 'ready', login_url: '/sandbox/demo-login' },
      });

      await userEvent.click(screen.getByRole('button', { name: /suivre l'etat de mon espace/i }));

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /votre espace est pret/i })).toBeInTheDocument();
      });
      expect(screen.getByRole('link', { name: /acceder a mon espace/i })).toHaveAttribute(
        'href',
        '/sandbox/demo-login'
      );
    });

    it('shows the pending spinner first, then the access link when polling turns ready', async () => {
      await submitWithToken();

      mockedFetchTrialStatus
        .mockResolvedValueOnce({ success: true, data: { status: 'pending' } })
        .mockResolvedValue({ success: true, data: { status: 'ready', login_url: '/sandbox/demo-login' } });

      await userEvent.click(screen.getByRole('button', { name: /suivre l'etat de mon espace/i }));

      await waitFor(() => {
        expect(screen.getByRole('heading', { name: /creation de votre espace/i })).toBeInTheDocument();
      });

      // deuxième poll (intervalle réel de 5 s) → ready
      await waitFor(
        () => {
          expect(screen.getByRole('heading', { name: /votre espace est pret/i })).toBeInTheDocument();
        },
        { timeout: 7000 }
      );
      expect(screen.getByRole('link', { name: /acceder a mon espace/i })).toHaveAttribute(
        'href',
        '/sandbox/demo-login'
      );
    }, 20000);

    it('shows a generic failure message when provisioning fails', async () => {
      await submitWithToken();

      mockedFetchTrialStatus.mockResolvedValue({ success: true, data: { status: 'failed' } });

      await userEvent.click(screen.getByRole('button', { name: /suivre l'etat de mon espace/i }));

      await waitFor(() => {
        expect(
          screen.getByRole('heading', { name: /creation de l'espace interrompue/i })
        ).toBeInTheDocument();
      });
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
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
      
      expect(submitButton).not.toHaveAttribute('disabled');
    });
  });
});
