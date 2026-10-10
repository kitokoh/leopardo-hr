import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import VtcDriversPage from '../drivers/page';

jest.mock('@/lib/api-client', () => {
  class ApiError extends Error {
    status: number;
    code?: string;
    constructor(message: string, status: number, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  }
  return { apiFetch: jest.fn(), ApiError };
});

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

const driversPayload = {
  data: [
    {
      id: 5,
      user_id: 91,
      name: 'Aïcha Mbarga',
      phone: '+237690000001',
      status: 'busy',
      vehicle_id: 3,
      current_latitude: 3.84801,
      current_longitude: 11.50211,
      location_updated_at: '2026-10-10T09:14:00+00:00',
      created_at: '2026-10-01T10:00:00+00:00',
    },
    {
      id: 7,
      user_id: null,
      name: 'Jean Onana',
      phone: null,
      status: 'available',
      vehicle_id: null,
      current_latitude: null,
      current_longitude: null,
      location_updated_at: null,
      created_at: '2026-10-02T10:00:00+00:00',
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Vue chauffeurs VTC (BC-34, VTC-07/#8363)', () => {
  it('liste les chauffeurs avec statut et dernière position connue', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(driversPayload));

    render(<VtcDriversPage />);

    expect(await screen.findByText('Aïcha Mbarga')).toBeInTheDocument();
    expect(screen.getByText('Jean Onana')).toBeInTheDocument();
    expect(screen.getByText('En course')).toBeInTheDocument();
    expect(screen.getByText('Disponible')).toBeInTheDocument();
    expect(screen.getByText('3.84801, 11.50211')).toBeInTheDocument();
    expect(screen.getByText('Aucune position relevée')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/vtc/dispatch/drivers');
  });

  it('affiche l’état vide quand aucun chauffeur n’est enregistré', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));

    render(<VtcDriversPage />);

    expect(await screen.findByText('Aucun chauffeur enregistré')).toBeInTheDocument();
  });

  it('affiche l’état « module désactivé » sur 403 (flag vtc off, fail-closed)', async () => {
    const { ApiError } = jest.requireMock('@/lib/api-client') as {
      ApiError: new (message: string, status: number, code?: string) => Error;
    };
    mockedApiFetch.mockRejectedValueOnce(new ApiError('Module disabled', 403, 'MODULE_DISABLED'));

    render(<VtcDriversPage />);

    expect(
      await screen.findByText('Le module VTC n’est pas activé pour cette entreprise.'),
    ).toBeInTheDocument();
  });
});
