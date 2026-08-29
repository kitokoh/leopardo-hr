import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import { LeaveBalanceCard } from '../LeaveBalanceCard';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const balancesPayload = {
  data: [
    {
      id: 11,
      employee_id: 501,
      absence_type_id: 1,
      balance: 18,
      used: 4,
      pending: 2,
      year: 2026,
      absence_type: { id: 1, name: 'Congé payé', code: 'CP' },
    },
    {
      id: 12,
      employee_id: 501,
      absence_type_id: 2,
      balance: 10,
      used: 3,
      pending: 0,
      year: 2026,
      absence_type: { id: 2, name: 'Maladie', code: 'MAL' },
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('LeaveBalanceCard (#5694)', () => {
  it('affiche le solde restant par type (balance − used − pending)', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => balancesPayload } as Response);

    render(<LeaveBalanceCard />);

    expect(await screen.findByText('Mon solde de congés')).toBeInTheDocument();
    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/me/leave-balances'));

    // Congé payé : 18 − 4 − 2 = 12 j disponibles, 4 utilisés.
    expect(screen.getByText(/12 j disponibles/)).toBeInTheDocument();
    expect(screen.getByText(/4 utilisés/)).toBeInTheDocument();
    // Maladie : 10 − 3 − 0 = 7 j disponibles, 3 utilisés.
    expect(screen.getByText(/7 j disponibles/)).toBeInTheDocument();
  });

  it('affiche un état vide quand aucun solde n’est configuré', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => ({ data: [] }) } as Response);

    render(<LeaveBalanceCard />);

    expect(await screen.findByText(/Aucun solde configuré/)).toBeInTheDocument();
  });

  it('affiche une erreur localisée si le chargement échoue', async () => {
    mockedApiFetch.mockRejectedValue(new Error('network'));

    render(<LeaveBalanceCard />);

    expect(await screen.findByText(/Impossible de charger le solde de congés/)).toBeInTheDocument();
  });
});
