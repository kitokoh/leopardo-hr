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

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('AbsencesPage (#5019)', () => {
  it('affiche la liste avec statuts localisés et actions pour les demandes en attente', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => absencesPayload } as Response);

    render(<AbsencesPage />);

    expect(await screen.findByText('Congé payé')).toBeInTheDocument();
    expect(screen.getByText('En attente')).toBeInTheDocument();
    expect(screen.getByText('Approuvée')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Approuver/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Refuser/i })).toBeInTheDocument();
  });

  it('approuve une absence via PUT /absences/{id}/approve et met à jour le statut', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => absencesPayload } as Response);

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
    mockedApiFetch.mockResolvedValue({ json: async () => absencesPayload } as Response);

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
