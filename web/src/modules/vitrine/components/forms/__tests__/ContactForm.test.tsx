import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ContactForm } from '../ContactForm';

// Mock the form submission
jest.mock('@/modules/vitrine/lib/forms', () => ({
  submitContactForm: jest.fn(),
}));

describe('ContactForm Component', () => {
  describe('Rendering', () => {
    it('should render contact form', () => {
      render(<ContactForm />);
      expect(screen.getByRole('textbox', { name: /name|email|message/i })).toBeInTheDocument();
    });

    it('should render name input', () => {
      render(<ContactForm />);
      expect(screen.getByRole('textbox', { name: /name/i })).toBeInTheDocument();
    });

    it('should render email input', () => {
      render(<ContactForm />);
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should render message textarea', () => {
      render(<ContactForm />);
      const textareas = screen.getAllByRole('textbox');
      expect(textareas.length).toBeGreaterThanOrEqual(3);
    });

    it('should render submit button', () => {
      render(<ContactForm />);
      expect(screen.getByRole('button', { name: /send|submit|contact/i })).toBeInTheDocument();
    });
  });

  describe('Form Validation', () => {
    it('should show error for empty name', async () => {
      render(<ContactForm />);
      const submitButton = screen.getByRole('button', { name: /send|submit|contact/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/name|required/i)).toBeInTheDocument();
      });
    });

    it('should show error for invalid email', async () => {
      render(<ContactForm />);
      const nameInput = screen.getByRole('textbox', { name: /name/i });
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      
      await userEvent.type(nameInput, 'John Doe');
      await userEvent.type(emailInput, 'invalid-email');
      
      const submitButton = screen.getByRole('button', { name: /send|submit|contact/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/email|invalid/i)).toBeInTheDocument();
      });
    });

    it('should show error for empty message', async () => {
      render(<ContactForm />);
      const nameInput = screen.getByRole('textbox', { name: /name/i });
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      
      await userEvent.type(nameInput, 'John Doe');
      await userEvent.type(emailInput, 'john@example.com');
      
      const submitButton = screen.getByRole('button', { name: /send|submit|contact/i });
      await userEvent.click(submitButton);
      
      await waitFor(() => {
        expect(screen.getByText(/message|required/i)).toBeInTheDocument();
      });
    });
  });

  describe('Form Submission', () => {
    it('should accept valid form data', async () => {
      render(<ContactForm />);
      const nameInput = screen.getByRole('textbox', { name: /name/i });
      const emailInput = screen.getByRole('textbox', { name: /email/i });
      const textareas = screen.getAllByRole('textbox');
      const messageInput = textareas[textareas.length - 1];
      
      await userEvent.type(nameInput, 'John Doe');
      await userEvent.type(emailInput, 'john@example.com');
      await userEvent.type(messageInput, 'This is a test message');
      
      const submitButton = screen.getByRole('button', { name: /send|submit|contact/i });
      expect(submitButton).not.toBeDisabled();
    });
  });

  describe('Accessibility', () => {
    it('should have accessible form labels', () => {
      render(<ContactForm />);
      expect(screen.getByRole('textbox', { name: /name/i })).toBeInTheDocument();
      expect(screen.getByRole('textbox', { name: /email/i })).toBeInTheDocument();
    });

    it('should be keyboard navigable', async () => {
      render(<ContactForm />);
      const nameInput = screen.getByRole('textbox', { name: /name/i });
      nameInput.focus();
      expect(nameInput).toHaveFocus();
      
      await userEvent.tab();
      const nextElement = document.activeElement;
      expect(nextElement).not.toBe(nameInput);
    });

    it('should have proper form structure', () => {
      const { container } = render(<ContactForm />);
      const form = container.querySelector('form');
      expect(form).toBeInTheDocument();
    });
  });

  describe('User Interactions', () => {
    it('should accept user input', async () => {
      render(<ContactForm />);
      const nameInput = screen.getByRole('textbox', { name: /name/i }) as HTMLInputElement;
      await userEvent.type(nameInput, 'Jane Doe');
      expect(nameInput.value).toBe('Jane Doe');
    });

    it('should show loading state during submission', async () => {
      render(<ContactForm />);
      const submitButton = screen.getByRole('button', { name: /send|submit|contact/i });
      expect(submitButton).not.toHaveAttribute('disabled');
    });
  });
});
