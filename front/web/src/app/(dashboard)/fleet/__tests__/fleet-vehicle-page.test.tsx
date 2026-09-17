import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import FleetVehiclePage from '../[id]/page';

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

// useParams du setup global renvoie '/' — on surcharge pour le véhicule #12.
jest.mock('next/navigation', () => ({
  ...jest.requireActual('next/navigation'),
  useParams: () => ({ id: '12' }),
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

const vehicle = {
  data: {
    id: 12,
    plate_number: 'LT-4521-AB',
    brand: 'Toyota',
    model: 'Hiace',
    year: 2021,
    type: 'van',
    vin: 'VIN123',
    fuel_type: 'diesel',
    status: 'active',
    mileage: 84500,
    insurance_expiry: '2026-12-31',
    technical_control_expiry: '2026-10-01',
    assigned_driver_id: 7,
  },
};

// #7526 — valeurs relevées sur la réponse RÉELLE de `GET /vehicles/1/position` :
// le payload Traccar est relayé tel quel et `speed` y est en **nœuds**.
// Une fixture en km/h (l'ancien `speed: 42.5`) faisait passer le test alors que
// la page affichait une vitesse 1,852 fois trop faible.
const position = {
  data: { latitude: 4.0511, longitude: 9.7679, speed: 22.68, fixTime: '2026-09-14T09:30:00+00:00' },
};

const trips = {
  data: [
    {
      id: 501,
      vehicle_id: 12,
      driver_id: 7,
      start_time: '2026-09-14T06:00:00+00:00',
      end_time: '2026-09-14T07:15:00+00:00',
      // Clés copiées de `VehicleTripResource` (réponse réelle), et non des
      // noms attendus par la page : c'est ce décalage qui rendait le test vert
      // alors que les itinéraires s'affichaient vides.
      start_address: 'Yaoundé, Centre, Cameroun',
      end_address: 'Douala, Wouri, Littoral, Cameroun',
      distance_km: '250.00',
      duration_minutes: 135,
      max_speed_kmh: '76.86',
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Fiche véhicule — flotte agence (#7400)', () => {
  it('affiche la fiche, la position live et les itinéraires', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse(vehicle))
      .mockResolvedValueOnce(jsonResponse(position))
      .mockResolvedValueOnce(jsonResponse(trips));

    render(<FleetVehiclePage />);

    // Fiche.
    expect(await screen.findByText('Toyota Hiace')).toBeInTheDocument();
    expect(screen.getByText('En service')).toBeInTheDocument();
    expect(screen.getByText('84500 km')).toBeInTheDocument();
    expect(screen.getByText('#7')).toBeInTheDocument();

    // Position (dernier relevé du traceur).
    expect(screen.getByText('4.05110, 9.76790')).toBeInTheDocument();
    // 22,68 nœuds → 42 km/h (conversion nœuds → km/h).
    expect(screen.getByText('42 km/h')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ouvrir dans OpenStreetMap' })).toHaveAttribute(
      'href',
      expect.stringContaining('mlat=4.0511'),
    );

    // Itinéraires.
    expect(screen.getByText('Yaoundé, Centre, Cameroun')).toBeInTheDocument();
    expect(screen.getByText('Douala, Wouri, Littoral, Cameroun')).toBeInTheDocument();
    expect(screen.getByText('250 km')).toBeInTheDocument();

    expect(mockedApiFetch).toHaveBeenCalledWith('/vehicles/12');
    expect(mockedApiFetch).toHaveBeenCalledWith('/vehicles/12/position');
    expect(mockedApiFetch).toHaveBeenCalledWith('/vehicles/12/trips?per_page=50');
  });

  it('affiche un état vide honnête quand aucun traceur n’est appairé (position 404)', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse(vehicle))
      .mockRejectedValueOnce(new Error('404'))
      .mockResolvedValueOnce(jsonResponse({ data: [] }));

    render(<FleetVehiclePage />);

    expect(await screen.findByText('Toyota Hiace')).toBeInTheDocument();
    expect(
      screen.getByText('Aucune position disponible : aucun traceur synchronisé pour ce véhicule.'),
    ).toBeInTheDocument();
    expect(screen.getByText('Aucun itinéraire enregistré')).toBeInTheDocument();
  });

  it('affiche « véhicule introuvable » si la fiche répond une erreur', async () => {
    mockedApiFetch.mockRejectedValueOnce(new Error('404'));

    render(<FleetVehiclePage />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Véhicule introuvable');
  });
});
