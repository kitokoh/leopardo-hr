import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import FeeBatchPage from '../fee-charges/batch/page';

/**
 * #9793 — encaissement en lot DEPUIS LA FICHE D'UN ÉLÈVE, et reçu PDF du lot.
 *
 * Ce que ces tests verrouillent :
 *   - `?student_id=` fait appeler l'endpoint de l'élève (et non la liste
 *     globale), puis PRÉ-SÉLECTIONNE toutes ses charges avec le montant au
 *     solde restant — un parent règle scolarité + cantine + transport d'un coup ;
 *   - le reçu PDF n'apparaît qu'APRÈS un encaissement réussi, et pointe sur le
 *     `batch_id` rendu par le serveur : un lien affiché trop tôt, ou construit
 *     sans identifiant, mènerait à un 404 au guichet.
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

const STUDENT_CHARGES = {
  data: {
    student: { id: 42, student_number: 'STU-0001', display_name: 'Lina Benali' },
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
      {
        id: 12,
        student_id: 42,
        student: { id: 42, display_name: 'Lina Benali' },
        fee_type: { id: 4, label: 'Cantine' },
        amount: 12000,
        paid_amount: 0,
        remaining_amount: 12000,
        currency: 'DZD',
        status: 'pending',
      },
    ],
  },
  meta: { charges: 2 },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  window.history.replaceState({}, '', '/edu-manager/fee-charges/batch');
});

describe('Encaissement en lot depuis un élève (#9793)', () => {
  it('pré-sélectionne toutes les charges de l’élève au solde restant', async () => {
    window.history.replaceState({}, '', '/edu-manager/fee-charges/batch?student_id=42');
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(STUDENT_CHARGES));

    render(<FeeBatchPage />);

    // L'endpoint de l'élève est appelé, pas la liste globale des charges.
    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        expect.stringContaining('/edu-manager/students/42/fee-charges'),
        expect.anything(),
      );
    });

    // Le bandeau nomme l'élève et le nombre de charges à encaisser. On cible le
    // bandeau entier : le nom apparaît aussi dans les options du sélecteur.
    expect(
      await screen.findByText(/Élève Lina Benali — 2 facturation\(s\) à encaisser\./),
    ).toBeInTheDocument();

    // Une ligne par charge, chacune au solde restant (jamais au montant dû).
    const amountInputs = screen.getAllByLabelText('Montant');
    expect(amountInputs).toHaveLength(2);
    expect(amountInputs[0]).toHaveValue(30000);
    expect(amountInputs[1]).toHaveValue(12000);
  });

  it('affiche le reçu PDF du lot après un encaissement réussi', async () => {
    window.history.replaceState({}, '', '/edu-manager/fee-charges/batch?student_id=42');

    mockedApiFetch
      .mockResolvedValueOnce(jsonResponse(STUDENT_CHARGES))
      .mockResolvedValueOnce(
        jsonResponse(
          {
            data: [],
            summary: {
              lines: 2,
              recorded: 2,
              total_amount: 42000,
              currency: 'DZD',
              batch_id: 'lot-recu-42',
            },
          },
          201,
        ),
      );

    render(<FeeBatchPage />);

    await waitFor(() => expect(screen.getAllByLabelText('Montant')).toHaveLength(2));

    // Avant l'encaissement, aucun reçu : le lien mènerait à un 404.
    expect(screen.queryByText('Télécharger le reçu (PDF)')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: 'Encaisser le lot' }));

    const receipt = await screen.findByText('Télécharger le reçu (PDF)');
    expect(receipt.closest('a')).toHaveAttribute(
      'href',
      '/api/v1/edu-manager/fee-payments/batches/lot-recu-42/receipt.pdf',
    );
  });
});
