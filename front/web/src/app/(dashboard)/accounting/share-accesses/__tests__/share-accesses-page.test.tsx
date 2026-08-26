import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import ShareAccessesPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const documentsPayload = {
  data: [
    { id: 11, number: 'FAC-2026-0001', type: 'invoice', status: 'sent' },
    { id: 12, number: 'FAC-2026-0002', type: 'credit_note', status: 'draft' },
  ],
};

const accessesPayload = {
  data: [
    {
      id: 101,
      action: 'accounting.share.download',
      module: 'accounting',
      request_id: 'req-abc-123',
      ip_address: '41.111.22.33',
      user_agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126',
      created_at: '2026-08-24T14:32:00+00:00',
    },
    {
      id: 100,
      action: 'accounting.share.info',
      module: 'accounting',
      request_id: 'req-abc-122',
      ip_address: '105.99.44.7',
      user_agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5) Safari/604.1',
      created_at: '2026-08-24T13:15:00+00:00',
    },
  ],
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 2 },
};

const emptyAccessesPayload = {
  data: [],
  links: {},
  meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
};

const paginatedAccessesPayload = {
  data: [
    {
      id: 101,
      action: 'accounting.share.download',
      module: 'accounting',
      request_id: 'req-abc-123',
      ip_address: '41.111.22.33',
      user_agent: 'Mozilla/5.0',
      created_at: '2026-08-24T14:32:00+00:00',
    },
  ],
  links: {},
  meta: { current_page: 1, last_page: 2, per_page: 25, total: 26 },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('ShareAccessesPage (#5522)', () => {
  it('charge les documents et invite à sélectionner un document', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => documentsPayload } as Response);

    render(<ShareAccessesPage />);

    expect(await screen.findByText('FAC-2026-0001 · invoice')).toBeInTheDocument();
    expect(screen.getByText(/Sélectionnez un document pour afficher/i)).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/documents?per_page=100');
  });

  it('affiche le tableau des accès (action localisée, IP, user-agent, request_id) après sélection', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => documentsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => accessesPayload } as Response);

    render(<ShareAccessesPage />);

    await screen.findByText('FAC-2026-0001 · invoice');
    await userEvent.selectOptions(screen.getByLabelText(/Document/i), '11');

    expect(await screen.findByText('Téléchargement')).toBeInTheDocument();
    expect(screen.getByText('Consultation')).toBeInTheDocument();
    expect(screen.getByText('41.111.22.33')).toBeInTheDocument();
    expect(screen.getByText('req-abc-123')).toBeInTheDocument();
    expect(screen.getByText('2 accès')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/accounting/documents/shared/11/accesses?per_page=25&page=1',
    );
  });

  it('affiche l\'état vide quand le document n\'a aucun accès', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => documentsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => emptyAccessesPayload } as Response);

    render(<ShareAccessesPage />);

    await screen.findByText('FAC-2026-0001 · invoice');
    await userEvent.selectOptions(screen.getByLabelText(/Document/i), '11');

    expect(await screen.findByText('Aucun accès enregistré')).toBeInTheDocument();
  });

  it('affiche une erreur quand le chargement des accès échoue', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => documentsPayload } as Response)
      .mockRejectedValueOnce(new Error('network'));

    render(<ShareAccessesPage />);

    await screen.findByText('FAC-2026-0001 · invoice');
    await userEvent.selectOptions(screen.getByLabelText(/Document/i), '11');

    expect(await screen.findByText(/Impossible de charger les accès/i)).toBeInTheDocument();
  });

  it('pagine : boutons précédent/suivant et compteur de page', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => documentsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => paginatedAccessesPayload } as Response)
      .mockResolvedValueOnce({ json: async () => paginatedAccessesPayload } as Response);

    render(<ShareAccessesPage />);

    await screen.findByText('FAC-2026-0001 · invoice');
    await userEvent.selectOptions(screen.getByLabelText(/Document/i), '11');

    expect(await screen.findByText('Page 1 sur 2')).toBeInTheDocument();

    const next = screen.getByRole('button', { name: /Page suivante/i });
    expect(next).toBeEnabled();
    await userEvent.click(next);

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/documents/shared/11/accesses?per_page=25&page=2',
      ),
    );
    expect(screen.getByText('Page 2 sur 2')).toBeInTheDocument();

    const prev = screen.getByRole('button', { name: /Page précédente/i });
    expect(prev).toBeEnabled();
    await userEvent.click(prev);

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/documents/shared/11/accesses?per_page=25&page=1',
      ),
    );
  });
});
