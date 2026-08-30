import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TravelPublicContactPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    constructor(message: string, status: number) {
      super(message);
      this.status = status;
    }
  },
}));

let searchParamsValue = new URLSearchParams();

jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useSearchParams: () => searchParamsValue,
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  searchParamsValue = new URLSearchParams({
    company_id: 'tenant-1',
    signature: 'abc',
    expires: '1788123456',
  });
});

describe('Formulaire de contact voyageurs public (TRAVEL-913)', () => {
  it('affiche le formulaire et envoie la demande avec consentement', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ status: 'received' }, 202));

    render(<TravelPublicContactPage />);

    await userEvent.type(screen.getByLabelText(/Email/), 'visiteur@example.com');
    await userEvent.type(screen.getByLabelText(/Message/), 'Demande d\u2019information.');
    await userEvent.click(screen.getByRole('checkbox'));
    await userEvent.click(screen.getByRole('button', { name: /Envoyer la demande|Send request/ }));

    await waitFor(() => {
      expect(screen.getByText(/Demande bien reçue/)).toBeVisible();
    });

    expect(mockedApiFetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/v1/travel/public/contact?company_id=tenant-1'),
      expect.objectContaining({ method: 'POST' }),
    );
    const body = JSON.parse((mockedApiFetch.mock.calls[0][1] as RequestInit).body as string);
    expect(body.email).toBe('visiteur@example.com');
    expect(body.consent_email).toBe(true);
  });

  it('bloque l\u2019envoi sans lien signé', async () => {
    searchParamsValue = new URLSearchParams();
    render(<TravelPublicContactPage />);

    await userEvent.type(screen.getByLabelText(/Email/), 'x@example.com');
    await userEvent.type(screen.getByLabelText(/Message/), 'Bonjour');
    await userEvent.click(screen.getByRole('checkbox'));
    await userEvent.click(screen.getByRole('button', { name: /Envoyer la demande|Send request/ }));

    await waitFor(() => {
      expect(screen.getByText(/Lien de contact invalide ou expiré/)).toBeVisible();
    });
    expect(mockedApiFetch).not.toHaveBeenCalled();
  });
});
