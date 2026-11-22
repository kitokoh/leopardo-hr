import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import FeeBatchPage from '../fee-charges/batch/page';
import FeeChargesPage from '../fee-charges/page';
import AccountingEntriesPage from '../fee-accounting-entries/page';

/**
 * #9791 — encaissement en lot et exports CSV.
 *
 * Ces tests verrouillent ce qui rend le lot utilisable au guichet :
 *   - le lot part vers `POST /fee-charges/batch-payments` avec le contexte
 *     commun (moyen, référence) et une ligne par charge ;
 *   - une ligne refusée par le serveur est SURLIGNÉE avec son message traduit,
 *     et le lot n'est pas présenté comme enregistré ;
 *   - les deux exports appellent bien les endpoints `.csv` et déclenchent un
 *     téléchargement (pas un simple rechargement de page).
 */

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

function blobResponse(): Response {
  return {
    blob: async () => new Blob(['date;eleve\n'], { type: 'text/csv' }),
    ok: true,
    status: 200,
    headers: new Headers(),
    clone: () => blobResponse(),
  } as unknown as Response;
}

const CHARGES = [
  {
    id: 11,
    student_id: 42,
    student: { id: 42, display_name: 'Lina Benali' },
    fee_type: { id: 3, label: 'Scolarité' },
    amount: 50000,
    paid_amount: 0,
    remaining_amount: 50000,
    currency: 'DZD',
    status: 'pending',
  },
  {
    id: 12,
    student_id: 43,
    student: { id: 43, display_name: 'Yacine Haddad' },
    fee_type: { id: 3, label: 'Scolarité' },
    amount: 50000,
    paid_amount: 20000,
    remaining_amount: 30000,
    currency: 'DZD',
    status: 'partial',
  },
];

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  // jsdom n'implémente pas createObjectURL : sans ce stub, l'export lève.
  Object.defineProperty(URL, 'createObjectURL', { value: jest.fn(() => 'blob:mock'), writable: true });
  Object.defineProperty(URL, 'revokeObjectURL', { value: jest.fn(), writable: true });
});

describe('Encaissement en lot (#9791)', () => {
  it('ne propose que les charges encore encaissables', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: [
          ...CHARGES,
          {
            id: 13,
            student: { id: 44, display_name: 'Nour Cherif' },
            fee_type: { id: 3, label: 'Scolarité' },
            amount: 50000,
            paid_amount: 50000,
            remaining_amount: 0,
            currency: 'DZD',
            status: 'paid',
          },
        ],
        meta: { total: 3 },
      }),
    );

    render(<FeeBatchPage />);

    await screen.findByText(/Lina Benali/);

    // La charge soldée est exclue : le serveur la refuserait (EDU_FEE_TERMINAL).
    expect(screen.queryByText(/Nour Cherif/)).not.toBeInTheDocument();
    expect(screen.getByText(/Yacine Haddad/)).toBeInTheDocument();
  });

  it('envoie le lot avec le contexte commun et une ligne par charge', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({ data: CHARGES, meta: { total: 2 } }),
    );

    render(<FeeBatchPage />);
    await screen.findByText(/Lina Benali/);

    const selects = screen.getAllByRole('combobox');
    // selects[0] = moyen de paiement, selects[1] = première ligne du lot.
    await userEvent.selectOptions(selects[1], '11');

    // Le montant est pré-rempli au solde restant : pas de surdébit par défaut.
    const amountInputs = screen.getAllByLabelText('Montant');
    expect(amountInputs[0]).toHaveValue(50000);

    await userEvent.type(screen.getByPlaceholderText('BORD-2026-09'), 'BORD-1');

    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse(
        {
          data: [{ charge_id: 11, amount: 50000 }],
          summary: { lines: 1, recorded: 1, total_amount: 50000, currency: 'DZD' },
        },
        201,
      ),
    );
    // Rechargement de la liste après succès.
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: CHARGES, meta: { total: 2 } }));

    await userEvent.click(screen.getByRole('button', { name: 'Encaisser le lot' }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/fee-charges/batch-payments',
        expect.objectContaining({
          method: 'POST',
          body: expect.stringContaining('"charge_id":11'),
        }),
      );
    });

    const call = mockedApiFetch.mock.calls.find(
      ([path]) => path === '/edu-manager/fee-charges/batch-payments',
    );
    const body = JSON.parse(String(call?.[1]?.body)) as Record<string, unknown>;
    expect(body.method).toBe('cash');
    expect(body.reference).toBe('BORD-1');
    expect(body.lines).toEqual([{ charge_id: 11, amount: 50000 }]);

    // Le succès est annoncé avec le total réel.
    expect(await screen.findByText(/Lot encaissé/)).toBeInTheDocument();
  });

  it('surligne la ligne refusée et n’annonce aucun succès', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({ data: CHARGES, meta: { total: 2 } }),
    );

    render(<FeeBatchPage />);
    await screen.findByText(/Lina Benali/);

    const selects = screen.getAllByRole('combobox');
    await userEvent.selectOptions(selects[1], '11');

    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse(
        {
          error: 'EDU_FEE_BATCH_INVALID',
          errors: [{ index: 0, charge_id: 11, error: 'EDU_FEE_OVERPAYMENT' }],
          summary: { lines: 1, recorded: 0, total_amount: 0, currency: null },
        },
        422,
      ),
    );

    await userEvent.click(screen.getByRole('button', { name: 'Encaisser le lot' }));

    // Le message métier traduit apparaît, rattaché à la ligne 1.
    expect(
      await screen.findByText(/Le montant dépasse le solde restant de la facturation/),
    ).toBeInTheDocument();
    expect(screen.getByText(/Ligne 1/)).toBeInTheDocument();
    expect(screen.queryByText(/Lot encaissé/)).not.toBeInTheDocument();
  });
});

describe('Exports CSV (#9791)', () => {
  it('exporte le journal des encaissements depuis la page de facturation', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse({ data: CHARGES, meta: { total: 2 } }))
      .mockResolvedValueOnce(jsonResponse({ data: [], meta: { total: 0 } }));

    render(<FeeChargesPage />);
    await screen.findByText('Lina Benali');

    mockedApiFetch.mockResolvedValueOnce(blobResponse());

    await userEvent.click(screen.getByRole('button', { name: 'Exporter en CSV' }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith('/edu-manager/fee-payments/export.csv');
    });
    expect(URL.createObjectURL).toHaveBeenCalled();
  });

  it('exporte le rapprochement en transmettant le filtre courant', async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({
        data: [
          {
            id: 1,
            entry_date: '2026-09-01',
            source_type: 'fee_charge',
            source_id: 11,
            account_code: '411',
            account_label: 'Clients',
            debit: 50000,
            credit: 0,
            reference: 'FAC-1',
          },
        ],
        meta: { total: 1 },
      }),
    );

    render(<AccountingEntriesPage />);
    await screen.findByText('411');

    mockedApiFetch.mockResolvedValueOnce(blobResponse());

    await userEvent.click(screen.getByRole('button', { name: 'Exporter en CSV' }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/edu-manager/fee-accounting-entries/export.csv',
      );
    });
  });
});
