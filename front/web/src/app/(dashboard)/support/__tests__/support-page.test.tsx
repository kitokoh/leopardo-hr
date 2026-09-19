import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError, apiFetch } from '@/lib/api-client';
import SupportPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;

    constructor(message: string, status = 400, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const ticketsPayload = {
  data: [
    {
      id: 11,
      subject: 'Export paie en erreur',
      category: 'technical',
      priority: 'high',
      status: 'open',
      messages_count: 2,
      last_message_at: '2026-09-18T10:00:00+00:00',
      created_at: '2026-09-17T09:00:00+00:00',
    },
    {
      id: 12,
      subject: 'Question de facturation',
      category: 'billing',
      priority: 'normal',
      status: 'closed',
      messages_count: 3,
      last_message_at: '2026-09-15T10:00:00+00:00',
      created_at: '2026-09-14T09:00:00+00:00',
    },
  ],
  meta: { current_page: 1, last_page: 1, total: 2 },
};

const openTicketDetail = {
  data: {
    id: 11,
    subject: 'Export paie en erreur',
    category: 'technical',
    priority: 'high',
    status: 'open',
    last_message_at: '2026-09-18T10:00:00+00:00',
    created_at: '2026-09-17T09:00:00+00:00',
    messages: [
      { id: 101, body: 'Le bouton export renvoie une erreur 500.', from_platform: false, created_at: '2026-09-17T09:00:00+00:00' },
      { id: 102, body: 'Nous investiguons, correctif en cours.', from_platform: true, created_at: '2026-09-18T10:00:00+00:00' },
    ],
  },
};

const closedTicketDetail = {
  data: {
    ...openTicketDetail.data,
    id: 12,
    subject: 'Question de facturation',
    category: 'billing',
    priority: 'normal',
    status: 'closed',
    messages: [
      { id: 201, body: 'Pourquoi deux prélèvements ce mois-ci ?', from_platform: false, created_at: '2026-09-14T09:00:00+00:00' },
    ],
  },
};

/** Mock apiFetch par route (liste, détail, mutations). */
function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url.startsWith('/support-tickets?') && method === 'GET') {
      return { json: async () => ticketsPayload } as Response;
    }

    if (url === '/support-tickets/11' && method === 'GET') {
      return { json: async () => openTicketDetail } as Response;
    }

    if (url === '/support-tickets/12' && method === 'GET') {
      return { json: async () => closedTicketDetail } as Response;
    }

    if (url === '/support-tickets' && method === 'POST') {
      return {
        json: async () => ({
          data: {
            id: 42,
            subject: 'Nouveau besoin',
            category: 'general',
            priority: 'normal',
            status: 'open',
            messages: [{ id: 401, body: 'Détail du besoin.', from_platform: false, created_at: '2026-09-19T09:00:00+00:00' }],
          },
        }),
      } as Response;
    }

    if (url === '/support-tickets/11/reply' && method === 'POST') {
      return {
        json: async () => ({
          data: {
            ...openTicketDetail.data,
            messages: [
              ...openTicketDetail.data.messages,
              { id: 103, body: 'Merci, je reste disponible.', from_platform: false, created_at: '2026-09-18T11:00:00+00:00' },
            ],
          },
        }),
      } as Response;
    }

    if (url === '/support-tickets/11/close' && method === 'POST') {
      return {
        json: async () => ({ data: { ...openTicketDetail.data, status: 'closed' } }),
      } as Response;
    }

    return { json: async () => ({ data: [] }) } as Response;
  });
}

/** Corps JSON d'un appel de mutation capturé par le mock. */
function bodyOf(url: string, method: string): Record<string, unknown> {
  const call = mockedApiFetch.mock.calls.find(
    ([calledUrl, calledOptions]) =>
      calledUrl === url && (calledOptions as RequestInit | undefined)?.method === method,
  );

  expect(call).toBeDefined();

  return JSON.parse(String((call?.[1] as RequestInit).body)) as Record<string, unknown>;
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  mockApiRoutes();
});

describe('SupportPage (#7759) — liste', () => {
  it('affiche les tickets avec statut, catégorie, priorité et dernière activité', async () => {
    render(<SupportPage />);

    expect(await screen.findByText('Export paie en erreur')).toBeInTheDocument();
    expect(screen.getByText('Question de facturation')).toBeInTheDocument();

    expect(screen.getByTestId('support-ticket-status-11')).toHaveTextContent('Ouvert');
    expect(screen.getByTestId('support-ticket-status-12')).toHaveTextContent('Clos');
    expect(screen.getByTestId('support-ticket-row-11')).toHaveTextContent('Technique');
    expect(screen.getByTestId('support-ticket-row-11')).toHaveTextContent('Haute');

    expect(mockedApiFetch).toHaveBeenCalledWith('/support-tickets?per_page=20', { _cacheBust: true });
  });

  it('filtre la liste par statut via GET /support-tickets?status=…', async () => {
    render(<SupportPage />);
    await screen.findByText('Export paie en erreur');

    await userEvent.click(screen.getByTestId('support-filter-closed'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/support-tickets?per_page=20&status=closed', { _cacheBust: true }),
    );
  });

  it('affiche l’état vide quand aucun ticket n’existe', async () => {
    mockedApiFetch.mockImplementation(async () => ({
      json: async () => ({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } }),
    }) as unknown as Response);

    render(<SupportPage />);

    expect(await screen.findByTestId('support-empty')).toHaveTextContent('Aucun ticket pour ce filtre.');
  });
});

describe('SupportPage (#7759) — création', () => {
  it('crée un ticket avec sujet/catégorie/priorité/message puis ouvre son fil', async () => {
    render(<SupportPage />);
    await screen.findByText('Export paie en erreur');

    await userEvent.click(screen.getByTestId('support-create-toggle'));
    await userEvent.type(screen.getByTestId('support-create-subject'), 'Nouveau besoin');
    await userEvent.selectOptions(screen.getByTestId('support-create-category'), 'billing');
    await userEvent.selectOptions(screen.getByTestId('support-create-priority'), 'urgent');
    await userEvent.type(screen.getByTestId('support-create-message'), 'Détail du besoin.');
    await userEvent.click(screen.getByTestId('support-create-submit'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/support-tickets', expect.objectContaining({ method: 'POST' })),
    );

    expect(bodyOf('/support-tickets', 'POST')).toEqual({
      subject: 'Nouveau besoin',
      category: 'billing',
      priority: 'urgent',
      message: 'Détail du besoin.',
    });

    // Confirmation affichée et fil du ticket créé ouvert directement.
    expect(await screen.findByRole('status')).toHaveTextContent('Ticket créé.');
    expect(screen.getByTestId('support-ticket-detail')).toHaveTextContent('Détail du besoin.');
  });

  it('bloque la soumission quand un champ requis ne contient que des espaces', async () => {
    render(<SupportPage />);
    await screen.findByText('Export paie en erreur');

    await userEvent.click(screen.getByTestId('support-create-toggle'));
    await userEvent.type(screen.getByTestId('support-create-subject'), ' ');
    await userEvent.type(screen.getByTestId('support-create-message'), 'Message seul.');
    await userEvent.click(screen.getByTestId('support-create-submit'));

    expect(await screen.findByRole('alert')).toHaveTextContent('Renseignez le sujet et le message.');
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/support-tickets', expect.objectContaining({ method: 'POST' }));
  });
});

describe('SupportPage (#7759) — fil de messages et réponse', () => {
  it('affiche le fil (plateforme vs entreprise) et envoie une réponse', async () => {
    render(<SupportPage />);
    await userEvent.click(await screen.findByTestId('support-ticket-row-11'));

    expect(await screen.findByTestId('support-ticket-detail')).toHaveTextContent('Export paie en erreur');
    expect(screen.getByTestId('support-message-101')).toHaveTextContent('Votre entreprise');
    expect(screen.getByTestId('support-message-102')).toHaveTextContent('Équipe Leopardo');

    await userEvent.type(screen.getByTestId('support-reply-input'), 'Merci, je reste disponible.');
    await userEvent.click(screen.getByTestId('support-reply-submit'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/support-tickets/11/reply', expect.objectContaining({ method: 'POST' })),
    );

    expect(bodyOf('/support-tickets/11/reply', 'POST')).toEqual({ message: 'Merci, je reste disponible.' });
    expect(await screen.findByRole('status')).toHaveTextContent('Réponse envoyée.');
    expect(screen.getByTestId('support-message-103')).toHaveTextContent('Merci, je reste disponible.');
  });

  it('masque le formulaire de réponse sur un ticket clos (contrat API : 422)', async () => {
    render(<SupportPage />);
    await userEvent.click(await screen.findByTestId('support-ticket-row-12'));

    expect(await screen.findByTestId('support-closed-notice')).toHaveTextContent('Ce ticket est clos.');
    expect(screen.queryByTestId('support-reply-input')).toBeNull();
    expect(screen.queryByTestId('support-close-toggle')).toBeNull();
  });

  it('affiche l’explication localisée si l’API refuse la réponse (422)', async () => {
    mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
      const method = (options?.method ?? 'GET').toUpperCase();

      if (url.startsWith('/support-tickets?') && method === 'GET') {
        return { json: async () => ticketsPayload } as Response;
      }

      if (url === '/support-tickets/11' && method === 'GET') {
        return { json: async () => openTicketDetail } as Response;
      }

      if (method === 'POST') {
        throw new ApiError('TICKET_ALREADY_CLOSED', 422, 'TICKET_ALREADY_CLOSED');
      }

      return { json: async () => ({ data: [] }) } as Response;
    });

    render(<SupportPage />);
    await userEvent.click(await screen.findByTestId('support-ticket-row-11'));
    await screen.findByTestId('support-ticket-detail');

    await userEvent.type(screen.getByTestId('support-reply-input'), 'Encore un souci.');
    await userEvent.click(screen.getByTestId('support-reply-submit'));

    expect(await screen.findByRole('alert')).toHaveTextContent('Ce ticket est clos.');
  });
});

describe('SupportPage (#7759) — clôture', () => {
  it('clôture un ticket après confirmation inline (sans window.confirm)', async () => {
    const confirmSpy = jest.spyOn(window, 'confirm');

    render(<SupportPage />);
    await userEvent.click(await screen.findByTestId('support-ticket-row-11'));
    await screen.findByTestId('support-ticket-detail');

    await userEvent.click(screen.getByTestId('support-close-toggle'));
    expect(screen.getByTestId('support-close-confirm')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/support-tickets/11/close', expect.anything());

    await userEvent.click(screen.getByTestId('support-close-confirm'));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/support-tickets/11/close', { method: 'POST' }),
    );

    expect(await screen.findByRole('status')).toHaveTextContent('Ticket clôturé.');
    expect(screen.getByTestId('support-detail-status')).toHaveTextContent('Clos');
    expect(screen.getByTestId('support-closed-notice')).toBeInTheDocument();
    expect(confirmSpy).not.toHaveBeenCalled();

    confirmSpy.mockRestore();
  });
});
