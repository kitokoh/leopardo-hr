import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import ReconciliationPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const statementsPayload = {
  data: [
    { id: 7, statement_period: '2026-08', import_reference: 'BQ-0826', status: 'imported' },
  ],
};

const statementDetailPayload = {
  data: {
    id: 7,
    statement_period: '2026-08',
    import_reference: 'BQ-0826',
    opening_balance: 1000,
    closing_balance: 1850,
    status: 'imported',
    lines: [
      { id: 1, line_number: 1, line_date: '2026-08-03', label: 'Virement client', amount: 850, external_reference: null, status: 'pending', matched_payment_id: null, confidence: 0.95, proposed_payment_id: 42 },
      { id: 2, line_number: 2, line_date: '2026-08-10', label: 'Frais bancaires', amount: -50, external_reference: null, status: 'matched', matched_payment_id: 41, confidence: null, proposed_payment_id: null },
    ],
  },
};

const paymentsPayload = {
  data: [{ id: 42, document_id: 9, amount: 850, method: 'transfer', reference: 'VIR-001', status: 'recorded' }],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('ReconciliationPage (#5523)', () => {
  it('liste les relevés puis affiche les lignes avec propositions', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => statementsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => statementDetailPayload } as Response)
      .mockResolvedValueOnce({ json: async () => paymentsPayload } as Response);

    render(<ReconciliationPage />);

    await screen.findByText(/BQ-0826/);
    await userEvent.selectOptions(screen.getByLabelText(/Relevés/i), '7');

    expect(await screen.findByText('Virement client')).toBeInTheDocument();
    expect(screen.getByText('Frais bancaires')).toBeInTheDocument();
    expect(screen.getByText(/Proposé #42/i)).toBeInTheDocument();
    expect(screen.getByText('95 %')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/bank-statements/7');
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/payments?status=recorded');
  });

  it('matche une ligne pending via POST /accounting/bank-statement-lines/{line}/match', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => statementsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => statementDetailPayload } as Response)
      .mockResolvedValueOnce({ json: async () => paymentsPayload } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: { confidence: 0.95 } }) } as unknown as Response)
      .mockResolvedValueOnce({ json: async () => statementDetailPayload } as Response)
      .mockResolvedValueOnce({ json: async () => paymentsPayload } as Response);

    render(<ReconciliationPage />);

    await screen.findByText(/BQ-0826/);
    await userEvent.selectOptions(screen.getByLabelText(/Relevés/i), '7');
    await screen.findByText('Virement client');

    await userEvent.selectOptions(screen.getAllByRole('combobox')[1], '42');
    await userEvent.click(screen.getByRole('button', { name: /Matcher/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/bank-statement-lines/1/match',
        expect.objectContaining({ method: 'POST', body: JSON.stringify({ payment_id: 42 }) }),
      ),
    );
    expect(await screen.findByText('Ligne rapprochée.')).toBeInTheDocument();
  });
});
