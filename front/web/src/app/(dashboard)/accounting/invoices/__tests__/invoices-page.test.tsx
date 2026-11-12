import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import InvoicesPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const documentsPayload = {
  data: [
    {
      id: 1,
      type: 'invoice',
      number: 'FAC-2026-0001',
      status: 'draft',
      issue_date: '2026-08-01',
      currency: 'DZD',
      subtotal_ht: 1000,
      tax_amount: 190,
      total_ttc: 1190,
      paid_amount: 0,
      contact: { id: 7, name: 'SARL Exemple' },
    },
    {
      id: 2,
      type: 'invoice',
      number: 'FAC-2026-0002',
      status: 'paid',
      issue_date: '2026-08-02',
      currency: 'DZD',
      subtotal_ht: 500,
      tax_amount: 95,
      total_ttc: 595,
      paid_amount: 595,
      contact: { id: 8, name: 'EURL Test' },
    },
  ],
};

const contactsPayload = { data: [{ id: 7, name: 'SARL Exemple' }] };

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

/**
 * #9380 — écrans de facturation du portail tenant.
 */
describe('InvoicesPage (#9380)', () => {
  it('liste les factures et calcule le reste à encaisser', async () => {
    mockedApiFetch.mockImplementation(async (url: string) => {
      if (url.startsWith('/accounting/contacts')) {
        return { json: async () => contactsPayload } as Response;
      }
      return { json: async () => documentsPayload } as Response;
    });

    render(<InvoicesPage />);

    expect(await screen.findByText('FAC-2026-0001')).toBeInTheDocument();
    expect(screen.getByText('FAC-2026-0002')).toBeInTheDocument();
    expect(screen.getByText('SARL Exemple')).toBeInTheDocument();

    // TTC 1 190 + 595 = 1 785 ; payé 595 → reste 1 190.
    expect(screen.getByText('1 190,00')).toBeInTheDocument();
    expect(screen.getByText('1 785,00')).toBeInTheDocument();
  });

  it('filtre la liste par statut via l’API', async () => {
    mockedApiFetch.mockImplementation(async () => ({ json: async () => documentsPayload }) as Response);

    render(<InvoicesPage />);
    await screen.findByText('FAC-2026-0001');

    await userEvent.selectOptions(screen.getByRole('combobox', { name: /Filtrer par statut/i }), 'paid');

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(expect.stringContaining('status=paid')),
    );
  });

  it('crée un brouillon via POST /accounting/documents', async () => {
    mockedApiFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (url.startsWith('/accounting/contacts')) {
        return { json: async () => contactsPayload } as Response;
      }
      if (init?.method === 'POST') {
        return { ok: true, json: async () => ({ data: { id: 3 } }) } as Response;
      }
      return { json: async () => documentsPayload } as Response;
    });

    render(<InvoicesPage />);
    await screen.findByText('FAC-2026-0001');

    await userEvent.click(screen.getByRole('button', { name: /Nouvelle facture/i }));
    await userEvent.type(screen.getByPlaceholderText('Désignation'), 'Prestation de conseil');
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer le brouillon/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/documents',
        expect.objectContaining({ method: 'POST' }),
      ),
    );
  });

  it('affiche le message du backend quand l’annulation est refusée (403 document payé)', async () => {
    mockedApiFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (url.startsWith('/accounting/contacts')) {
        return { json: async () => contactsPayload } as Response;
      }
      if (init?.method === 'POST') {
        return {
          ok: false,
          json: async () => ({ message: 'Un document déjà payé ne peut pas être annulé : émettez un avoir.' }),
        } as Response;
      }
      return { json: async () => documentsPayload } as Response;
    });

    render(<InvoicesPage />);
    await screen.findByText('FAC-2026-0001');

    await userEvent.click(screen.getAllByRole('button', { name: /Annuler la facture/i })[0]);

    expect(
      await screen.findByText(/Un document déjà payé ne peut pas être annulé/i),
    ).toBeInTheDocument();
  });

  it('n’affiche pas d’action d’annulation sur une facture payée', async () => {
    mockedApiFetch.mockImplementation(async (url: string) => {
      if (url.startsWith('/accounting/contacts')) {
        return { json: async () => contactsPayload } as Response;
      }
      return { json: async () => documentsPayload } as Response;
    });

    render(<InvoicesPage />);
    await screen.findByText('FAC-2026-0002');

    // Deux factures, une seule annulable (la brouillon).
    expect(screen.getAllByRole('button', { name: /Annuler la facture/i })).toHaveLength(1);
  });
});
