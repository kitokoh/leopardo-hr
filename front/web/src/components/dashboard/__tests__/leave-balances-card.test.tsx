import { render, screen, waitFor } from '@testing-library/react';
import { apiFetch } from '@/lib/api-client';
import LeaveBalancesCard from '../LeaveBalancesCard';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const balancesPayload = {
  data: [
    {
      id: 1,
      absence_type_id: 11,
      balance: 30,
      used: 12,
      pending: 3,
      year: 2026,
      absence_type: { id: 11, name: 'Congé payé', code: 'paid' },
    },
    {
      id: 2,
      absence_type_id: 12,
      balance: 10,
      used: 0,
      pending: 0,
      year: 2026,
      absence_type: { id: 12, name: 'Congé maladie', code: 'sick' },
    },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('LeaveBalancesCard (#5694)', () => {
  it('affiche le solde disponible (balance − used − pending) par type', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => balancesPayload } as Response);

    render(<LeaveBalancesCard locale="fr" />);

    expect(await screen.findByText('Soldes de congés')).toBeInTheDocument();
    expect(screen.getByText('Congé payé')).toBeInTheDocument();
    // 30 − 12 − 3 = 15 disponibles
    expect(screen.getByText('15')).toBeInTheDocument();
    expect(screen.getByText('Congé maladie')).toBeInTheDocument();
    expect(screen.getByText('10')).toBeInTheDocument();
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/me/leave-balances')
    );
  });

  it('affiche un état vide quand aucun solde', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => ({ data: [] }) } as Response);

    render(<LeaveBalancesCard locale="fr" />);

    expect(await screen.findByText('Aucun solde pour cette année')).toBeInTheDocument();
  });

  it('affiche une erreur lisible en cas d’échec réseau', async () => {
    mockedApiFetch.mockRejectedValue(new Error('network'));

    render(<LeaveBalancesCard locale="fr" />);

    expect(await screen.findByText('Impossible de charger vos soldes.')).toBeInTheDocument();
  });
});
