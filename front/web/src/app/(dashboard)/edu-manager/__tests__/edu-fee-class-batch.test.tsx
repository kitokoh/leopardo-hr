import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import FeeBatchPage from '../fee-charges/batch/page';

/**
 * #9792 — encaissement en lot DEPUIS LA FICHE D'UNE CLASSE, et filtres du lot.
 *
 * Ce que ces tests verrouillent :
 *   - `?class_id=` fait appeler l'endpoint d'effectif (et non la liste globale
 *     des charges), puis PRÉ-SÉLECTIONNE une ligne par charge avec le montant
 *     au solde restant — le caissier n'a plus qu'à valider ;
 *   - un élève sans charge n'ajoute aucune ligne (rien à encaisser) ;
 *   - les filtres de période et de moyen de paiement repartent vers l'API :
 *     un filtre saisi mais non transmis laisserait le caissier croire qu'il a
 *     restreint la sélection alors que non.
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

const ROSTER = {
  data: {
    class: { id: 7, code: '6A', name: '6ème A' },
    students: [
      {
        id: 42,
        student_number: 'STU-0001',
        display_name: 'Lina Benali',
        charges: [
          {
            id: 11,
            student_id: 42,
            student: { id: 42, display_name: 'Lina Benali' },
            fee_type: { id: 3, label: 'Scolarité' },
            amount: 50000,
            paid_amount: 20000,
            remaining_amount: 30000,
            currency: 'DZD',
            status: 'partial',
          },
        ],
      },
      {
        id: 43,
        student_number: 'STU-0002',
        display_name: 'Yacine Haddad',
        charges: [],
      },
    ],
  },
  meta: { students: 2, charges: 1 },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.history.replaceState({}, '', '/edu-manager/fee-charges/batch');
});

describe('Encaissement en lot depuis une classe (#9792)', () => {
  it('pré-sélectionne les charges de l’effectif et le solde restant', async () => {
    window.history.replaceState({}, '', '/edu-manager/fee-charges/batch?class_id=7');
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(ROSTER));

    render(<FeeBatchPage />);

    // L'endpoint d'effectif est appelé, pas la liste globale des charges.
    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        expect.stringContaining('/edu-manager/classes/7/fee-charges'),
        expect.anything(),
      );
    });

    // La bannière annonce la classe et le volume à encaisser.
    expect(await screen.findByText(/6ème A/)).toBeInTheDocument();

    // Une ligne pré-remplie au solde restant (30000), pas au montant dû.
    const amountInputs = screen.getAllByLabelText('Montant');
    expect(amountInputs[0]).toHaveValue(30000);

    // L'élève sans charge n'ajoute aucune ligne.
    expect(amountInputs).toHaveLength(1);
  });

  it('transmet la période et le moyen de paiement à l’API', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse({ data: [], meta: { total: 0 } }));

    render(<FeeBatchPage />);
    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalled());

    const from = screen.getByLabelText('Échéance à partir du');
    const to = screen.getByLabelText('Échéance jusqu\'au');
    await userEvent.type(from, '2026-09-01');
    await userEvent.type(to, '2026-09-30');

    await waitFor(() => {
      const calls = mockedApiFetch.mock.calls.map(([path]) => String(path));
      expect(calls.some((path) => path.includes('due_from=2026-09-01'))).toBe(true);
      expect(calls.some((path) => path.includes('due_to=2026-09-30'))).toBe(true);
    });

    await userEvent.selectOptions(screen.getByLabelText('Déjà réglé par'), 'cash');

    await waitFor(() => {
      const calls = mockedApiFetch.mock.calls.map(([path]) => String(path));
      expect(calls.some((path) => path.includes('paid_with_method=cash'))).toBe(true);
    });
  });
});
