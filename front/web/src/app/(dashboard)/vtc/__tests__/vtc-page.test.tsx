import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import VtcDispatchPage from '../page';

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

const ridesPayload = {
  data: [
    {
      id: 42,
      reference: 'RIDE-2026-0042',
      status: 'dispatching',
      driver_id: null,
      pending_offer_driver_id: 7,
      pickup: { latitude: 3.848, longitude: 11.502, address: 'Poste centrale, Yaoundé' },
      dropoff: { latitude: 3.866, longitude: 11.517, address: 'Aéroport de Nsimalen' },
      estimated_price_minor: 450000,
      final_price_minor: null,
      currency: 'XAF',
      requested_at: '2026-10-10T09:12:00+00:00',
    },
    {
      id: 43,
      reference: 'RIDE-2026-0043',
      status: 'in_progress',
      driver_id: 5,
      pending_offer_driver_id: null,
      pickup: { latitude: 3.85, longitude: 11.51, address: 'Bastos' },
      dropoff: { latitude: 3.87, longitude: 11.52, address: 'Mvan' },
      estimated_price_minor: 300000,
      final_price_minor: null,
      currency: 'XAF',
      requested_at: '2026-10-10T08:58:00+00:00',
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Console dispatch VTC (BC-34, VTC-07/#8363)', () => {
  it('liste les courses actives avec statut, adresses et offre en cours', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(ridesPayload));

    render(<VtcDispatchPage />);

    expect(await screen.findByText('RIDE-2026-0042')).toBeInTheDocument();
    expect(screen.getByText('RIDE-2026-0043')).toBeInTheDocument();
    expect(screen.getByText('Poste centrale, Yaoundé')).toBeInTheDocument();
    expect(screen.getByText('En dispatch')).toBeInTheDocument();
    expect(screen.getByText('En cours')).toBeInTheDocument();
    expect(screen.getByText(/Offre en cours/)).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/vtc/dispatch/rides');
  });

  it('affiche l’état vide quand aucune course n’est active', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));

    render(<VtcDispatchPage />);

    expect(await screen.findByText('Aucune course active')).toBeInTheDocument();
  });

  it('affiche l’état « module désactivé » sur 403 (flag vtc off, fail-closed)', async () => {
    const { ApiError } = jest.requireMock('@/lib/api-client') as {
      ApiError: new (message: string, status: number, code?: string) => Error;
    };
    mockedApiFetch.mockRejectedValueOnce(new ApiError('Module disabled', 403, 'MODULE_DISABLED'));

    render(<VtcDispatchPage />);

    expect(
      await screen.findByText('Le module VTC n’est pas activé pour cette entreprise.'),
    ).toBeInTheDocument();
  });

  it('affiche une erreur générique sur échec réseau', async () => {
    mockedApiFetch.mockRejectedValueOnce(new Error('network down'));

    render(<VtcDispatchPage />);

    expect(await screen.findByText('Impossible de charger le dispatch VTC')).toBeInTheDocument();
  });
});
