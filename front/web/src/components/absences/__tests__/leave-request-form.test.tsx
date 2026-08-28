import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import LeaveRequestForm from '../LeaveRequestForm';

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
      used: 10,
      pending: 0,
      year: 2026,
      absence_type: { id: 12, name: 'Congé maladie', code: 'sick' },
    },
  ],
};

const okResponse = { ok: true, json: async () => ({ data: { id: 99 } }) } as Response;

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('LeaveRequestForm (#5693)', () => {
  it('propose uniquement les types avec solde disponible et soumet POST /absences', async () => {
    mockedApiFetch.mockResolvedValue(okResponse);
    const onSubmitted = jest.fn();

    render(<LeaveRequestForm locale="fr" onSubmitted={onSubmitted} />);

    expect(await screen.findByText('Nouvelle absence')).toBeInTheDocument();

    // Congé maladie a 0 disponible → absent du sélecteur.
    const select = screen.getByLabelText('Type');
    expect(select).toBeInTheDocument();
    expect(screen.queryByText(/Congé maladie/)).not.toBeInTheDocument();
    expect(screen.getByText(/Congé payé/)).toBeInTheDocument();

    await userEvent.selectOptions(select, '11');
    await userEvent.type(screen.getByLabelText('Début'), '2026-09-01');
    await userEvent.type(screen.getByLabelText('Fin'), '2026-09-05');
    await userEvent.type(screen.getByLabelText('Motif'), 'Vacances');
    await userEvent.click(screen.getByRole('button', { name: /Soumettre au RH/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/absences',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({
            absence_type_id: 11,
            start_date: '2026-09-01',
            end_date: '2026-09-05',
            reason: 'Vacances',
          }),
        })
      )
    );

    expect(onSubmitted).toHaveBeenCalled();
    expect(await screen.findByText("Demande d'absence transmise au RH.")).toBeInTheDocument();
  });

  it('refuse une soumission incomplète (type manquant)', async () => {
    mockedApiFetch.mockResolvedValue(okResponse);

    render(<LeaveRequestForm locale="fr" onSubmitted={jest.fn()} />);

    await screen.findByText('Nouvelle absence');

    await userEvent.click(screen.getByRole('button', { name: /Soumettre au RH/i }));

    expect(await screen.findByText("Type d'absence requis")).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith(
      '/absences',
      expect.objectContaining({ method: 'POST' })
    );
  });

  it('refuse une fin antérieure au début', async () => {
    mockedApiFetch.mockResolvedValue(okResponse);

    render(<LeaveRequestForm locale="fr" onSubmitted={jest.fn()} />);

    await screen.findByText('Nouvelle absence');

    await userEvent.selectOptions(screen.getByLabelText('Type'), '11');
    await userEvent.type(screen.getByLabelText('Début'), '2026-09-10');
    await userEvent.type(screen.getByLabelText('Fin'), '2026-09-01');
    await userEvent.type(screen.getByLabelText('Motif'), 'Vacances');
    await userEvent.click(screen.getByRole('button', { name: /Soumettre au RH/i }));

    expect(await screen.findByText('Renseignez les dates de début et de fin.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith(
      '/absences',
      expect.objectContaining({ method: 'POST' })
    );
  });
});
