import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { ForgotPasswordForm } from '../ForgotPasswordForm';

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

describe('ForgotPasswordForm', () => {
  it('rejects an invalid email with a localized error', async () => {
    mockedApiFetch.mockResolvedValue({ ok: true } as Response);

    render(<ForgotPasswordForm />);
    await userEvent.type(screen.getByLabelText(/adresse email/i), 'pas-un-email');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer le lien/i }));

    expect(await screen.findByText('Adresse email invalide.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('calls POST /auth/forgot-password and shows the generic success panel', async () => {
    mockedApiFetch.mockResolvedValue({ ok: true } as Response);

    render(<ForgotPasswordForm />);
    await userEvent.type(screen.getByLabelText(/adresse email/i), 'karim@techcorp.dz');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer le lien/i }));

    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/auth/forgot-password',
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ email: 'karim@techcorp.dz' }),
      })
    );
    expect(await screen.findByText('Email envoyé')).toBeInTheDocument();
  });

  it('surfaces the API error message (rate limit, network…)', async () => {
    mockedApiFetch.mockRejectedValueOnce(new Error('Trop de tentatives.'));

    render(<ForgotPasswordForm />);
    await userEvent.type(screen.getByLabelText(/adresse email/i), 'karim@techcorp.dz');
    await userEvent.click(screen.getByRole('button', { name: /Envoyer le lien/i }));

    expect(await screen.findByText('Trop de tentatives.')).toBeInTheDocument();
  });
});
