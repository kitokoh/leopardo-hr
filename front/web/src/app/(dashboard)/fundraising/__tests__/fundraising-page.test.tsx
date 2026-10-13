import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import CagnottesPage from '../page';

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

const settings = {
  data: {
    enabled_methods: ['card', 'mobile_money'],
    default_min_amount: 100,
    default_max_amount: 10000000,
    default_suggested_amounts: [1000, 5000],
    allow_anonymous: true,
    allow_messages: true,
    show_supporters: true,
  },
};

const draftFundraiser = {
  id: 42,
  slug: 'aide-fatou',
  title: 'Aide pour Fatou',
  description: null,
  beneficiary_name: 'Fatou Diallo',
  category: 'medical',
  category_label: 'Médical',
  goal_amount: 500000,
  collected_amount: 0,
  contributions_count: 0,
  available_balance: 0,
  currency: 'XOF',
  status: 'draft',
  status_label: 'Brouillon',
  public_url: '/cagnottes/aide-fatou',
  ends_at: null,
};

const pendingContribution = {
  id: 7,
  reference: 'FC-TEST000001',
  fundraiser_id: 42,
  amount: 50000,
  currency: 'XOF',
  payment_method: 'cash',
  payment_method_label: 'Espèces',
  provider: 'manual',
  status: 'pending',
  status_label: 'En attente',
  contributor_name: 'Moussa Diop',
  contributor_email: null,
  contributor_phone: null,
  is_anonymous: false,
  message: 'Courage !',
  paid_at: null,
  created_at: '2026-10-11T21:13:09+00:00',
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Espace tenant Cagnottes (FUND-090)', () => {
  it('liste les cagnottes et publie un brouillon', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [draftFundraiser] })) // GET fundraisers
      .mockResolvedValueOnce(jsonResponse(settings)) // GET settings
      .mockResolvedValueOnce(jsonResponse({ data: { ...draftFundraiser, status: 'active', status_label: 'Active' } })) // POST publish
      .mockResolvedValueOnce(jsonResponse({ data: [{ ...draftFundraiser, status: 'active', status_label: 'Active' }] })); // reload

    render(<CagnottesPage />);

    expect(await screen.findByText('Aide pour Fatou')).toBeInTheDocument();
    expect(screen.getByText('Brouillon')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Publier/ }));

    await waitFor(() => {
      const publishCall = mockedApiFetch.mock.calls.find(([endpoint]) => String(endpoint) === '/fundraising/fundraisers/42/publish');
      expect(publishCall).toBeDefined();
    });
  });

  it('crée une cagnotte en brouillon via le formulaire', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [] })) // GET fundraisers
      .mockResolvedValueOnce(jsonResponse(settings)) // GET settings
      .mockResolvedValueOnce(jsonResponse({ data: draftFundraiser }, 201)) // POST fundraisers
      .mockResolvedValueOnce(jsonResponse({ data: [draftFundraiser] })); // reload

    render(<CagnottesPage />);

    await userEvent.click(await screen.findByRole('button', { name: /Nouvelle cagnotte/ }));
    await userEvent.type(screen.getByPlaceholderText(/Aide pour les frais médicaux/), 'Aide pour Fatou');
    await userEvent.type(screen.getByPlaceholderText(/Nom de la personne/), 'Fatou Diallo');
    await userEvent.click(screen.getByRole('button', { name: /Créer le brouillon/ }));

    await waitFor(() => {
      const createCall = mockedApiFetch.mock.calls.find(([endpoint, options]) => String(endpoint) === '/fundraising/fundraisers' && (options as RequestInit)?.method === 'POST');
      expect(createCall).toBeDefined();
      const body = JSON.parse(String((createCall?.[1] as RequestInit).body));
      expect(body).toMatchObject({ title: 'Aide pour Fatou', beneficiary_name: 'Fatou Diallo', category: 'other' });
    });
  });

  it('affiche l\'état « verticale non activée » sur 403/404 fail-closed', async () => {
    const forbidden = Object.assign(new Error('forbidden'), { status: 403 });
    mockedApiFetch.mockRejectedValueOnce(forbidden);

    render(<CagnottesPage />);

    expect(await screen.findByText('Verticale non activée')).toBeInTheDocument();
  });

  it('liste les contributions et confirme un don manuel en attente (#8428)', async () => {
    const activeFundraiser = { ...draftFundraiser, status: 'active', status_label: 'En collecte' };
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [activeFundraiser] })) // GET fundraisers
      .mockResolvedValueOnce(jsonResponse(settings)) // GET settings
      .mockResolvedValueOnce(jsonResponse({ data: [pendingContribution] })) // GET contributions
      .mockResolvedValueOnce(jsonResponse({ data: { ...pendingContribution, status: 'completed', status_label: 'Confirmée' } })) // POST confirm
      .mockResolvedValueOnce(jsonResponse({ data: [{ ...activeFundraiser, collected_amount: 50000, contributions_count: 1 }] })) // reload fundraisers
      .mockResolvedValueOnce(jsonResponse({ data: [{ ...pendingContribution, status: 'completed', status_label: 'Confirmée' }] })); // reload contributions

    render(<CagnottesPage />);

    expect(await screen.findByText('Aide pour Fatou')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: /Contributions/ }));

    // La contribution en attente est visible avec son bouton Confirmer.
    expect(await screen.findByText(/Moussa Diop/)).toBeInTheDocument();
    expect(screen.getByText(/FC-TEST000001/)).toBeInTheDocument();
    const confirmButton = screen.getByRole('button', { name: 'Confirmer' });
    await userEvent.click(confirmButton);

    await waitFor(() => {
      const confirmCall = mockedApiFetch.mock.calls.find(([endpoint, options]) => String(endpoint) === '/fundraising/contributions/7/confirm' && (options as RequestInit)?.method === 'POST');
      expect(confirmCall).toBeDefined();
    });
    expect(await screen.findByText('Contribution confirmée.')).toBeInTheDocument();
  });

  it('enregistre le paramétrage du module (onglet Paramétrage)', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: [] }))
      .mockResolvedValueOnce(jsonResponse(settings))
      .mockResolvedValueOnce(jsonResponse(settings)) // PUT settings
      .mockResolvedValueOnce(jsonResponse(settings)); // reload settings

    render(<CagnottesPage />);

    await userEvent.click(await screen.findByRole('button', { name: /Paramétrage/ }));
    expect(await screen.findByText('Paramétrage du module')).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Enregistrer le paramétrage/ }));

    await waitFor(() => {
      const putCall = mockedApiFetch.mock.calls.find(([endpoint, options]) => String(endpoint) === '/fundraising/settings' && (options as RequestInit)?.method === 'PUT');
      expect(putCall).toBeDefined();
      const body = JSON.parse(String((putCall?.[1] as RequestInit).body));
      expect(body).toMatchObject({ enabled_methods: ['card', 'mobile_money'], allow_anonymous: true, show_supporters: true });
    });
  });
});
