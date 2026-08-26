import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import { ChallengeForm } from '../ChallengeForm';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const mockRouterPush = jest.fn();
jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockRouterPush }),
}));

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.sessionStorage.clear();
});

function mockMeUser() {
  mockedApiFetch.mockResolvedValueOnce({
    ok: true,
    json: async () => ({ data: { id: 42, email: 'manager@company.test', language: 'fr', is_rtl: false, role: 'manager' } }),
  } as unknown as Response);
}

describe('ChallengeForm (issue #5612)', () => {
  it('shows a "session expired" state when no challenge token is stored', async () => {
    render(<ChallengeForm />);

    expect(await screen.findByText(/La session de vérification a expiré/i)).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });

  it('submits the TOTP code with the challenge token and redirects to the dashboard', async () => {
    window.sessionStorage.setItem('mfa_challenge_token', 'ch-abc');
    mockMeUser();

    render(<ChallengeForm />);

    await userEvent.type(screen.getByLabelText(/Code à 6 chiffres/i), '123456');
    await userEvent.click(screen.getByRole('button', { name: /Vérifier/i }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/auth/2fa/verify',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"challenge_token":"ch-abc"'),
        }),
      );
    });
    expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me');
    await waitFor(() => expect(mockRouterPush).toHaveBeenCalledWith('/dashboard'));
    expect(window.sessionStorage.getItem('mfa_challenge_token')).toBeNull();
  });

  it('submits a recovery code when the toggle is used', async () => {
    window.sessionStorage.setItem('mfa_challenge_token', 'ch-rec');
    mockMeUser();

    render(<ChallengeForm />);

    await userEvent.click(screen.getByRole('button', { name: /Utiliser un code de récupération/i }));
    await userEvent.type(screen.getByLabelText(/Code de récupération/i), 'AAAA-BBBB-CCCC');
    await userEvent.click(screen.getByRole('button', { name: /Vérifier/i }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/auth/2fa/verify',
        expect.objectContaining({
          body: expect.stringContaining('"recovery_code":"AAAA-BBBB-CCCC"'),
        }),
      );
    });
  });

  it('surfaces the API error message on an invalid code', async () => {
    window.sessionStorage.setItem('mfa_challenge_token', 'ch-bad');
    mockedApiFetch.mockRejectedValueOnce(
      new (class extends Error {
        status = 422;
        code = 'TWO_FA_INVALID';
      })('Code invalide ou expiré. Réessayez.'),
    );

    render(<ChallengeForm />);

    await userEvent.type(screen.getByLabelText(/Code à 6 chiffres/i), '000000');
    await userEvent.click(screen.getByRole('button', { name: /Vérifier/i }));

    expect(await screen.findByText('Code invalide ou expiré. Réessayez.')).toBeInTheDocument();
    expect(mockRouterPush).not.toHaveBeenCalled();
  });
});
