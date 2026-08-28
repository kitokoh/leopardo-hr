import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import AbsencesPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const absencesPayload = {
  data: [
    {
      id: 1,
      absence_type: { name: 'Congé payé' },
      start_date: '2026-07-06',
      end_date: '2026-07-10',
      reason: 'Vacances annuelles',
      days_count: 5,
      status: 'pending',
    },
    {
      id: 2,
      absence_type: { name: 'Maladie' },
      start_date: '2026-07-15',
      end_date: '2026-07-16',
      reason: null,
      days_count: 2,
      status: 'approved',
    },
  ],
};

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

/** Mock apiFetch selon l'URL (liste, soldes, mutation). */
function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
    const method = (options?.method ?? 'GET').toUpperCase();

    if (url === '/me/leave-balances' && method === 'GET') {
      return { json: async () => balancesPayload } as Response;
    }

    if (url === '/absences' && method === 'GET') {
      return { json: async () => absencesPayload } as Response;
    }

    if (url === '/absences' && method === 'POST') {
      return { json: async () => ({ data: { id: 3 } }) } as Response;
    }

    return { json: async () => ({ data: [] }) } as Response;
  });
}

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
  mockApiRoutes();
});

describe('AbsencesPage (#5019)', () => {
  it('affiche la liste avec statuts localisés et actions pour les demandes en attente', async () => {
    render(<AbsencesPage />);

    expect(await screen.findByText('Congé payé')).toBeInTheDocument();
    expect(screen.getByText('En attente')).toBeInTheDocument();
    expect(screen.getByText('Approuvée')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Approuver/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Refuser/i })).toBeInTheDocument();
  });

  it('approuve une absence via PUT /absences/{id}/approve et met à jour le statut', async () => {
    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    await userEvent.click(screen.getByRole('button', { name: /Approuver/i }));
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/absences/1/approve', { method: 'PUT' })
    );
    // Statut mis à jour : plus d'action, badge approuvé.
    expect((await screen.findAllByText('Approuvée')).length).toBeGreaterThan(0);
  });

  it('exige un motif avant de refuser (PUT /absences/{id}/reject avec rejected_reason)', async () => {
    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    await userEvent.click(screen.getByRole('button', { name: /Refuser/i }));
    await screen.findByText('Refuser la demande');

    // Motif vide → message requis, aucun appel API.
    await userEvent.click(screen.getByRole('button', { name: /Confirmer le refus/i }));
    expect(await screen.findByText('Le motif est obligatoire pour refuser.')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/absences/1/reject', expect.anything());

    // Motif rempli → appel API avec le payload attendu.
    await userEvent.type(screen.getByLabelText(/motif du refus/i), 'Absence non justifiée');
    await userEvent.click(screen.getByRole('button', { name: /Confirmer le refus/i }));
    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/absences/1/reject',
        expect.objectContaining({ method: 'PUT', body: JSON.stringify({ rejected_reason: 'Absence non justifiée' }) })
      )
    );
  });
});

describe('AbsencesPage — formulaire de demande (#5693)', () => {
  it('liste les types d’absence depuis /me/leave-balances dans le formulaire', async () => {
    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    await userEvent.click(screen.getByRole('button', { name: /Demander/i }));

    expect(await screen.findByText('Nouvelle absence')).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /Type/i })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Congé payé' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Maladie' })).toBeInTheDocument();
  });

  it('soumet une demande via POST /absences puis recharge la liste', async () => {
    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    await userEvent.click(screen.getByRole('button', { name: /Demander/i }));
    await screen.findByText('Nouvelle absence');

    await userEvent.selectOptions(screen.getByRole('combobox', { name: /Type/i }), '1');
    await userEvent.type(screen.getByLabelText(/Début/i), '2026-09-01');
    await userEvent.type(screen.getByLabelText(/Fin/i), '2026-09-05');
    await userEvent.type(screen.getByLabelText(/Motif/i), 'Vacances');

    await userEvent.click(screen.getByRole('button', { name: /Soumettre au RH/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/absences',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({
            absence_type_id: 1,
            start_date: '2026-09-01',
            end_date: '2026-09-05',
            reason: 'Vacances',
          }),
        })
      )
    );
    // Formulaire fermé + confirmation affichée.
    expect(await screen.findByText("Demande d'absence transmise au RH.")).toBeInTheDocument();
    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/absences', expect.anything()));
  });

  it('bloque la soumission si les dates sont manquantes ou incohérentes', async () => {
    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    await userEvent.click(screen.getByRole('button', { name: /Demander/i }));
    await screen.findByText('Nouvelle absence');

    // Type sélectionné mais dates vides → erreur de dates, aucun POST.
    await userEvent.selectOptions(screen.getByRole('combobox', { name: /Type/i }), '1');
    await userEvent.click(screen.getByRole('button', { name: /Soumettre au RH/i }));
    expect(await screen.findByText('Dates de début et fin requises (fin ≥ début).')).toBeInTheDocument();
    expect(mockedApiFetch).not.toHaveBeenCalledWith('/absences', expect.objectContaining({ method: 'POST' }));
  });

  it('désactive le bouton Demander quand aucun type n’est disponible', async () => {
    mockedApiFetch.mockImplementation(async (url: string, options?: RequestInit) => {
      const method = (options?.method ?? 'GET').toUpperCase();
      if (url === '/absences' && method === 'GET') {
        return { json: async () => absencesPayload } as Response;
      }
      if (url === '/me/leave-balances' && method === 'GET') {
        return { json: async () => ({ data: [] }) } as Response;
      }
      return { json: async () => ({ data: [] }) } as Response;
    });

    render(<AbsencesPage />);
    await screen.findByText('Congé payé');

    expect(screen.getByRole('button', { name: /Demander/i })).toBeDisabled();
  });
});
