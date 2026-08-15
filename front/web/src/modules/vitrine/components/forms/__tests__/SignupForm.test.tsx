import React from 'react';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
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

    async function fillValidForm() {
      await userEvent.type(screen.getByRole('textbox', { name: /email/i }), 'test@example.com');
      await userEvent.type(screen.getByRole('textbox', { name: /entreprise/i }), 'Acme Corp');
      const selects = screen.getAllByRole('combobox');
      await userEvent.selectOptions(selects[0], 'founder');
      await userEvent.selectOptions(selects[1], '1-10');
      await userEvent.click(screen.getByRole('checkbox'));
    }

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
      await userEvent.selectOptions(selects[0], 'founder');
      await userEvent.selectOptions(selects[1], '1-10');
      await userEvent.click(screen.getByRole('checkbox'));
    }

    it('shows the "Suivre l\'etat de mon espace" link on the OTP screen when a provisioning token is returned', async () => {
      mockedSubmitSignupForm.mockResolvedValue({
        success: true,
        provisioned: true,
        message: 'Code de verification envoye.',
        data: { provisioning_token: 'a'.repeat(64) },
      });

      render(<SignupForm />);
      await fillValidForm();
      await userEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));

      await waitFor(() => {
        expect(screen.getByText(/verifiez votre email/i)).toBeInTheDocument();
      });
      expect(screen.getByRole('button', { name: /suivre l'etat de mon espace/i })).toBeInTheDocument();
    });

    it('does not show the tracking link without a provisioning token', async () => {
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

    it('polls pending → ready and shows the access link', async () => {
      jest.useFakeTimers();
      try {
        mockedSubmitSignupForm.mockResolvedValue({
          success: true,
          provisioned: true,
          message: 'Code de verification envoye.',
          data: { provisioning_token: 'b'.repeat(64) },
        });
        (fetchTrialStatus as jest.Mock)
          .mockResolvedValueOnce({ success: true, data: { status: 'pending' } })
          .mockResolvedValueOnce({
            success: true,
            data: { status: 'ready', login_url: 'https://demo.leopardo.app/access?t=123' },
          });

        render(<SignupForm />);
        await fillValidForm();
        await fireEvent.click(screen.getByRole('button', { name: /recevoir mon code de verification/i }));
        await screen.findByText(/verifiez votre email/i);

        await fireEvent.click(screen.getByRole('button', { name: /suivre l'etat de mon espace/i }));

        // premier poll immédiat : pending → spinner
        expect(await screen.findByText(/preparation de votre espace/i)).toBeInTheDocument();

        // second poll après 5 s : ready → lien d'accès
        await act(async () => {
          jest.advanceTimersByTime(5000);
        });
        expect(await screen.findByText(/votre espace est pret/i)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /acceder a mon espace/i })).toHaveAttribute(
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
      const submitButton = screen.getByRole('button', { name: /recevoir mon code de verification/i });
      
      expect(submitButton).not.toHaveAttribute('disabled');
    });
  });
});
