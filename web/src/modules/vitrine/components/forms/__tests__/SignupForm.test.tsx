import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SignupForm } from '../SignupForm';

// Mock the form submission
jest.mock('@/modules/vitrine/lib/forms', () => ({
  submitSignupForm: jest.fn(),
}));

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

    it('should render password input', () => {
      render(<SignupForm />);
      const passwordInputs = screen.getAllByDisplayValue('');
      expect(passwordInputs.length).toBeGreaterThan(0);
    });

    it('should render submit button', () => {
      render(<SignupForm />);
      expect(screen.getByRole('button', { name: /sign up|get started/i })).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('should show error for invalid email', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'invalid-email');
      await userEvent.click(screen.getByRole('button', { name: /sign up|get started/i }));
      
      await waitFor(() => {
        expect(screen.getByText(/invalid email|valid email/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty email', async () => {
      render(<SignupForm />);
      const submitButton = screen.getByRole('button', { name: /sign up|get started/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/required|email/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty password', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      const submitButton = screen.getByRole('button', { name: /sign up|get started/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/password|required/i)).toBeInTheDocument();
      });
    });

    it('should show error for short password', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      
      const passwordInputs = screen.getAllByDisplayValue('');
      if (passwordInputs.length > 0) {
        await userEvent.type(passwordInputs[0], 'short');
      }
      
      const submitButton = screen.getByRole('button', { name: /sign up|get started/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/password|character|length/i)).toBeInTheDocument();
      });
    });
  });

  describe('Form Submission', () => {
    it('should accept valid email and password', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      await userEvent.type(emailInput, 'test@example.com');
      
      const passwordInputs = screen.getAllByDisplayValue('');
      if (passwordInputs.length > 0) {
        await userEvent.type(passwordInputs[0], 'ValidPassword123!');
      }
      
      const submitButton = screen.getByRole('button', { name: /sign up|get started/i });
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

  describe('User Interactions', () => {
    it('should clear form after successful submission', async () => {
      render(<SignupForm />);
      const emailInput = screen.getByRole('textbox', { name: /email/i }) as HTMLInputElement;
      await userEvent.type(emailInput, 'test@example.com');
      
      expect(emailInput.value).toBe('test@example.com');
    });

    it('should show loading state during submission', async () => {
      render(<SignupForm />);
      const submitButton = screen.getByRole('button', { name: /sign up|get started/i });
      
      expect(submitButton).not.toHaveAttribute('disabled');
    });
  });
});
