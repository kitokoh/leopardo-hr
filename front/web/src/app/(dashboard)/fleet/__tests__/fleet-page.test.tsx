import { render, screen } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import FleetPage from '../page';

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

const vehicles = {
  data: [
    {
      id: 12,
      plate_number: 'LT-4521-AB',
      brand: 'Toyota',
      model: 'Hiace',
      year: 2021,
      type: 'van',
      fuel_type: 'diesel',
      status: 'active',
      mileage: 84500,
      assigned_driver_id: 7,
    },
    {
      id: 13,
      plate_number: 'LT-7788-CD',
      brand: 'Hyundai',
      model: 'Tucson',
      year: 2019,
      type: 'car',
      fuel_type: 'gasoline',
      status: 'maintenance',
      mileage: 121300,
      assigned_driver_id: null,
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Flotte de l’agence (#7400)', () => {
  it('liste les véhicules du tenant avec statut, kilométrage et chauffeur', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(vehicles));

    render(<FleetPage />);

    expect(await screen.findByText('LT-4521-AB')).toBeInTheDocument();
    expect(screen.getByText('LT-7788-CD')).toBeInTheDocument();
    expect(screen.getByText('Toyota Hiace')).toBeInTheDocument();
    // Statuts traduits (fr) — jamais la valeur brute de l'API.
    expect(screen.getByText('En service')).toBeInTheDocument();
    expect(screen.getByText('En maintenance')).toBeInTheDocument();
    expect(screen.getByText('84500 km')).toBeInTheDocument();
    expect(screen.getByText('Non affecté')).toBeInTheDocument();

    // Chaque carte mène à la fiche véhicule.
    const links = screen.getAllByRole('link', { name: 'Voir le détail' });
    expect(links.map((link) => link.getAttribute('href'))).toEqual(['/fleet/12', '/fleet/13']);

    expect(mockedApiFetch).toHaveBeenCalledWith('/vehicles?per_page=100');
  });

  it('affiche un état vide explicite quand la flotte est vide', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: [] }));

    render(<FleetPage />);

    expect(await screen.findByText('Aucun véhicule enregistré')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Voir le détail' })).not.toBeInTheDocument();
  });

  it('affiche une erreur explicite (pas une liste vide) si l’API échoue', async () => {
    mockedApiFetch.mockRejectedValueOnce(new Error('network down'));

    render(<FleetPage />);

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de charger la flotte');
    expect(screen.queryByText('Aucun véhicule enregistré')).not.toBeInTheDocument();
  });
});
