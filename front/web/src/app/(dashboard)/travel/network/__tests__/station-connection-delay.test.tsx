import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import TravelNetworkPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/**
 * M5 — le délai de correspondance par gare doit être RÉGLABLE depuis l'écran.
 *
 * Le backend exposait `min_connection_minutes` sur `TravelStationResource` et
 * l'acceptait en écriture, mais l'écran réseau ne l'affichait ni ne l'envoyait :
 * la fonctionnalité n'était atteignable qu'en appelant l'API à la main. Ces
 * tests verrouillent les deux moitiés du contrat.
 */

const cities = {
  data: [
    { id: 1, country_iso2: 'CM', name: 'Douala', region: null, status: 'active' },
    { id: 2, country_iso2: 'CM', name: 'Yaoundé', region: null, status: 'active' },
  ],
};

const countries = {
  data: [{ id: 1, iso2: 'CM', iso3: 'CMR', name: 'Cameroun', phone_code: '+237', status: 'active' }],
};

const stations = {
  data: [
    {
      id: 10,
      code: 'DLA-1',
      name: 'Gare de Douala',
      city_id: 1,
      address: null,
      contact_phone: null,
      timezone: 'Africa/Douala',
      is_terminal: true,
      min_connection_minutes: 90,
      status: 'active',
    },
    {
      id: 11,
      code: 'YDE-1',
      name: 'Gare de Yaoundé',
      city_id: 2,
      address: null,
      contact_phone: null,
      timezone: 'Africa/Douala',
      is_terminal: false,
      min_connection_minutes: null,
      status: 'active',
    },
  ],
};

function mockRoutes() {
  mockedApiFetch.mockImplementation(async (url: string) => {
    if (url.startsWith('/travel/cities')) return { ok: true, json: async () => cities } as Response;
    if (url.startsWith('/travel/countries')) return { ok: true, json: async () => countries } as Response;
    if (url.startsWith('/travel/stations')) return { ok: true, json: async () => stations } as Response;
    return { ok: true, json: async () => ({ data: [] }) } as Response;
  });
}

describe('Travel network — délai de correspondance par gare (M5)', () => {
  beforeEach(() => {
    mockedApiFetch.mockReset();
    mockRoutes();
  });

  it('affiche le délai réglé sur une gare', async () => {
    render(<TravelNetworkPage />);

    expect(await screen.findByText('Gare de Douala')).toBeInTheDocument();
    expect(screen.getByText('90 min')).toBeInTheDocument();
  });

  it('affiche le repli explicite quand la gare n’impose aucun délai', async () => {
    render(<TravelNetworkPage />);

    expect(await screen.findByText('Gare de Yaoundé')).toBeInTheDocument();

    // Une cellule vide se lirait comme « 0 minute » : on nomme le repli.
    expect(screen.getByText('Agency default')).toBeInTheDocument();
  });

  it('envoie min_connection_minutes à la création d’une gare', async () => {
    const user = userEvent.setup();
    render(<TravelNetworkPage />);

    await screen.findByText('Gare de Douala');

    await user.click(screen.getByRole('button', { name: /^create$/i }));

    await user.type(screen.getByLabelText(/^code/i), 'BFS-1');
    await user.type(screen.getByLabelText(/^name/i), 'Gare de Bafoussam');

    // `city_id` est obligatoire côté formulaire : sans lui le POST n'est
    // jamais émis et le test passerait pour une mauvaise raison.
    await user.selectOptions(screen.getByLabelText(/^city/i), '1');

    const delayInput = screen.getByLabelText(/min\. connection/i);
    await user.type(delayInput, '120');

    await user.click(screen.getByRole('button', { name: /^save$/i }));

    await waitFor(() => {
      const post = mockedApiFetch.mock.calls.find(
        ([url, init]) => String(url).startsWith('/travel/stations') && (init as RequestInit | undefined)?.method === 'POST',
      );
      expect(post).toBeDefined();
      const body = JSON.parse(String((post?.[1] as RequestInit).body));
      expect(body.min_connection_minutes).toBe(120);
    });
  });
});
