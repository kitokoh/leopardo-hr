import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import FiscalYearsPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const yearsPayload = {
  data: [
    { year: 2025, status: 'closed', closed_at: '2026-01-15T00:00:00+00:00', closed_by: '1' },
    { year: 2026, status: 'open', closed_at: null, closed_by: null },
  ],
  meta: { count: 2 },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('FiscalYearsPage (#5534)', () => {
  it('liste les exercices avec leurs statuts', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => yearsPayload } as Response);

    render(<FiscalYearsPage />);

    expect(await screen.findByText('2025')).toBeInTheDocument();
    expect(screen.getByText('2026')).toBeInTheDocument();
    expect(screen.getAllByText('Clôturé').length).toBe(1);
    expect(screen.getAllByText('Ouvert').length).toBe(1);
  });

  it('ouvre un exercice via POST /accounting/fiscal-years', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => yearsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => ({ data: { year: 2027, status: 'open' } }) } as Response)
      .mockResolvedValueOnce({ json: async () => yearsPayload } as Response);

    render(<FiscalYearsPage />);

    await screen.findByText('2025');
    const openButtons = screen.getAllByRole('button', { name: /^Ouvrir$/ });
    await userEvent.click(openButtons[0]);

    const input = screen.getByRole('spinbutton');
    await userEvent.clear(input);
    await userEvent.type(input, '2027');
    await userEvent.click(screen.getAllByRole('button', { name: /^Ouvrir$/ })[1]);

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/fiscal-years',
        expect.objectContaining({ method: 'POST', body: JSON.stringify({ year: 2027 }) }),
      ),
    );
  });

  it('clôture un exercice après confirmation via POST /accounting/fiscal-years/{year}/close', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => yearsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => ({ data: { year: 2026, status: 'closed' } }) } as Response)
      .mockResolvedValueOnce({ json: async () => yearsPayload } as Response);

    render(<FiscalYearsPage />);

    await screen.findByText('2025');
    await userEvent.click(screen.getByRole('button', { name: /Clôturer/i }));

    expect(await screen.findByText(/Clôturer l'exercice 2026 \?/i)).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', { name: /Confirmer la clôture/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/fiscal-years/2026/close', { method: 'POST' }),
    );
  });
});
