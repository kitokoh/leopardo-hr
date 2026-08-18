import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { ResetPasswordForm } from '../ResetPasswordForm';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

const validParams = { token: 'reset-token-123', email: 'karim@techcorp.dz' };

describe('ResetPasswordForm', () => {
  it('renders the missing-link state when token/email are absent', () => {
    render(<ResetPasswordForm token="" email="" />);

    expect(screen.getByText('Lien invalide ou expiré')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('rejects mismatching passwords', async () => {
    render(<ResetPasswordForm {...validParams} />);

    await userEvent.type(screen.getByLabelText(/nouveau mot de passe/i), 'longpassword1');
    await userEvent.type(screen.getByLabelText(/confirmer le mot de passe/i), 'different1');
    await userEvent.click(screen.getByRole('button', { name: /Réinitialiser le mot de passe/i }));

    expect(await screen.findByText('Les deux mots de passe ne correspondent pas.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('rejects a password shorter than 8 characters', async () => {
    render(<ResetPasswordForm {...validParams} />);

    await userEvent.type(screen.getByLabelText(/nouveau mot de passe/i), 'short');
    await userEvent.type(screen.getByLabelText(/confirmer le mot de passe/i), 'short');
    await userEvent.click(screen.getByRole('button', { name: /Réinitialiser le mot de passe/i }));

    expect(
      await screen.findByText('Le mot de passe doit contenir au moins 8 caractères.')
    ).toBeInTheDocument();
  });

  it('calls POST /auth/reset-password with token+email and shows the success panel', async () => {
    mockedApiFetch.mockResolvedValue({ ok: true } as Response);

    render(<ResetPasswordForm {...validParams} />);

    await userEvent.type(screen.getByLabelText(/nouveau mot de passe/i), 'longpassword1');
    await userEvent.type(screen.getByLabelText(/confirmer le mot de passe/i), 'longpassword1');
    await userEvent.click(screen.getByRole('button', { name: /Réinitialiser le mot de passe/i }));

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/auth/reset-password',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({
          token: 'reset-token-123',
          email: 'karim@techcorp.dz',
          password: 'longpassword1',
          password_confirmation: 'longpassword1',
        }),
      })
    );
    expect(await screen.findByText('Mot de passe réinitialisé')).toBeInTheDocument();
  });
});
