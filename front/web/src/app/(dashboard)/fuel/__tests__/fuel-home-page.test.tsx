import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import FuelHomePage from '../page';

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

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Hub station-service (BC-15 FUEL)', () => {
  it('affiche les tuiles d’outils et l’état vide quand aucune station n’existe', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse({ data: [] }));

    render(<FuelHomePage />);

    // Tuiles de navigation vers chaque outil.
    expect(await screen.findByText('Équipe & shifts')).toBeInTheDocument();
    expect(screen.getByText('Rotations & plannings')).toBeInTheDocument();
    expect(screen.getByText('Stock & carburants')).toBeInTheDocument();
    expect(screen.getByText('Ventes & caisse')).toBeInTheDocument();
    expect(screen.getByText('Poste pompiste')).toBeInTheDocument();
    // « Stations » apparaît deux fois : le KPI et la tuile.
    expect(screen.getAllByText('Stations').length).toBeGreaterThanOrEqual(2);

    // État vide actionnable pointant vers la création de station.
    expect(await screen.findByTestId('fuel-empty-state')).toBeInTheDocument();
    expect(screen.getByText('Créer une station')).toBeInTheDocument();
  });

  it('compte les stations, shifts et incidents dans les KPIs', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [{ id: 1 }, { id: 2 }] })) // stations
      .mockResolvedValueOnce(jsonResponse({ data: [{ id: 10 }] })) // shifts
      .mockResolvedValueOnce(jsonResponse({ data: [{ id: 20 }, { id: 21 }, { id: 22 }] })); // incidents

    render(<FuelHomePage />);

    await waitFor(() => {
      expect(screen.getByTestId('fuel-kpi-stations')).toHaveTextContent('2');
    });
    expect(screen.getByTestId('fuel-kpi-shifts')).toHaveTextContent('1');
    expect(screen.getByTestId('fuel-kpi-incidents')).toHaveTextContent('3');

    // Non vide → pas d'état vide.
    expect(screen.queryByTestId('fuel-empty-state')).not.toBeInTheDocument();
  });
});
