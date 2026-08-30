import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import FuelPumpPage from '../page';

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

const stations = {
  data: [
    { id: 1, code: 'ST-01', name: 'Station Autoroute', address: 'KM 12', phone: null, timezone: 'UTC', currency: 'XOF', status: 'active' },
  ],
};

const equipment = {
  data: [
    { kind: 'pump', id: 10, station_id: 1, code: 'P-1', status: 'active' },
    { kind: 'pump', id: 11, station_id: 1, code: 'P-2', status: 'active' },
    { kind: 'meter', id: 20, station_id: 1, code: 'M-1', status: 'active' },
    { kind: 'tank', id: 30, station_id: 1, code: 'T-1', status: 'active' },
  ],
};

const shifts = {
  data: [
    { id: 5, shift_id: 42, shift: { id: 42, name: 'Matin', start_time: '06:00', end_time: '14:00' } },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  mockedApiFetch
    .mockResolvedValueOnce(jsonResponse(stations))
    .mockResolvedValueOnce(jsonResponse(equipment))
    .mockResolvedValueOnce(jsonResponse(shifts));
});

describe('Écran pompiste (FUEL-013)', () => {
  it('affiche le shift actif et le parcours station → pompe → compteur', async () => {
    render(<FuelPumpPage />);

    // Shift actif affiché.
    expect(await screen.findByText(/Shift : Matin/)).toBeInTheDocument();

    // Étape 1 : station.
    await userEvent.click(await screen.findByText('Station Autoroute'));
    // Étape 2 : pompe.
    await userEvent.click(await screen.findByText('P-1'));
    // Étape 3 : compteur.
    await userEvent.click(await screen.findByText('M-1'));

    expect(await screen.findByText('Relevé du compteur')).toBeInTheDocument();
    expect(screen.getByText('M-1', { exact: false })).toBeInTheDocument();
  });

  it('enregistre un relevé avec idempotency_key et affiche le delta', async () => {
    render(<FuelPumpPage />);

    await userEvent.click(await screen.findByText('Station Autoroute'));
    await userEvent.click(await screen.findByText('P-1'));
    await userEvent.click(await screen.findByText('M-1'));

    const input = await screen.findByLabelText('Relevé du compteur');
    await userEvent.type(input, '1250.5');

    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 99 } }, 201)); // POST reading
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [{ delta_minor: 2500 }] })); // intervals

    await userEvent.click(screen.getByText('Enregistrer le relevé'));

    await waitFor(() => {
      const postCall = mockedApiFetch.mock.calls.find(([url]) =>
        String(url).includes('/fuel-station/stations/1/pumps/10/meters/20/readings'),
      );
      expect(postCall).toBeDefined();
      const body = JSON.parse(String(postCall?.[1]?.body));
      expect(body.reading_value_minor).toBe(125050);
      expect(body.reading_unit).toBe('l');
      expect(body.shift_id).toBe(42);
      expect(body.idempotency_key).toMatch(/^[A-Za-z0-9\-_.]{8,191}$/);
    });

    expect(await screen.findByText(/Relevé enregistré/)).toBeInTheDocument();
    expect(await screen.findByText(/Delta : 25.00 l/)).toBeInTheDocument();
  });

  it('signale une anomalie sur la station sélectionnée', async () => {
    render(<FuelPumpPage />);

    await userEvent.click(await screen.findByText('Station Autoroute'));
    await userEvent.click(await screen.findByText('P-1'));
    await userEvent.click(await screen.findByText('M-1'));

    await userEvent.click(await screen.findByText('Signaler une anomalie'));
    await userEvent.type(await screen.findByLabelText("Titre de l'anomalie"), 'Pompe en panne');
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 7 } }, 201));

    const buttons = screen.getAllByText('Signaler une anomalie');
    await userEvent.click(buttons[buttons.length - 1]);

    await waitFor(() => {
      const postCall = mockedApiFetch.mock.calls.find(([url]) =>
        String(url).includes('/fuel-station/incidents'),
      );
      expect(postCall).toBeDefined();
      const body = JSON.parse(String(postCall?.[1]?.body));
      expect(body.station_id).toBe(1);
      expect(body.title).toBe('Pompe en panne');
    });
  });
});
