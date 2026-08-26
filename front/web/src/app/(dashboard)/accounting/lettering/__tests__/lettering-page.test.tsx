import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import LetteringPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const journalPayload = {
  period: '2026-08',
  balanced: true,
  closed: false,
  totals: { total_debit: 100, total_credit: 100 },
  entries: [
    { id: 1, date: '2026-08-03', piece: 'FAC-2026-0001', description: 'Vente', account_code: '411', account_label: 'Clients', debit: 100, credit: 0 },
    { id: 2, date: '2026-08-03', piece: 'FAC-2026-0001', description: 'Vente', account_code: '701', account_label: 'Ventes', debit: 0, credit: 100 },
    { id: 3, date: '2026-08-10', piece: 'FAC-2026-0002', description: 'Achat', account_code: '607', account_label: 'Achats', debit: 50, credit: 0 },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('LetteringPage (#5534)', () => {
  it('liste le journal avec totaux et indicateur équilibré', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => journalPayload } as Response);

    render(<LetteringPage />);

    expect((await screen.findAllByText('FAC-2026-0001')).length).toBeGreaterThan(0);
    expect(screen.getByText('Journal équilibré')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/journal?period=2026-08');
  });

  it('lettre 2+ écritures sélectionnées via POST /accounting/journal/lettering', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => journalPayload } as Response)
      .mockResolvedValueOnce({ ok: true, json: async () => ({ data: {} }) } as unknown as Response)
      .mockResolvedValueOnce({ json: async () => journalPayload } as Response);

    render(<LetteringPage />);

    expect((await screen.findAllByText('FAC-2026-0001')).length).toBeGreaterThan(0);

    const checkboxes = screen.getAllByRole('checkbox');
    await userEvent.click(checkboxes[0]);
    await userEvent.click(checkboxes[1]);

    await userEvent.type(screen.getByPlaceholderText('Lettre'), 'A1');
    await userEvent.click(screen.getByRole('button', { name: /Lettrer \(2\)/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/journal/lettering',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ letter: 'A1', entry_ids: [1, 2] }),
        }),
      ),
    );
    expect(await screen.findByText('Écritures lettrées.')).toBeInTheDocument();
  });

  it('refuse de lettrer avec moins de 2 écritures', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => journalPayload } as Response);

    render(<LetteringPage />);

    expect((await screen.findAllByText('FAC-2026-0001')).length).toBeGreaterThan(0);

    const checkboxes = screen.getAllByRole('checkbox');
    await userEvent.click(checkboxes[0]);

    await userEvent.type(screen.getByPlaceholderText('Lettre'), 'A1');
    await userEvent.click(screen.getByRole('button', { name: /Lettrer \(1\)/i }));

    expect(await screen.findByText('Sélectionnez au moins 2 écritures.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/accounting/journal/lettering', expect.anything());
  });
});
