import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import ChartPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const accountsPayload = {
  data: [
    { code: '101', label: 'Capital social', type: 'equity', class: 1, is_system: true, is_active: true },
    { code: '512', label: 'Banque', type: 'asset', class: 5, is_system: false, is_active: true },
    { code: '401', label: 'Fournisseurs', type: 'liability', class: 4, is_system: false, is_active: false },
  ],
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('ChartPage (#5534)', () => {
  it('affiche le plan comptable avec les statuts et la note système', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => accountsPayload } as Response);

    render(<ChartPage />);

    expect(await screen.findByText('Capital social')).toBeInTheDocument();
    expect(screen.getByText('Banque')).toBeInTheDocument();
    expect(screen.getAllByText('Actif').length).toBeGreaterThan(0);
    expect(screen.getByText(/Compte système — suppression interdite/i)).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/chart');
  });

  it('crée un compte via POST /accounting/chart puis recharge', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => ({ data: [] }) } as Response)
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response);

    render(<ChartPage />);

    await screen.findByText('Capital social');
    await userEvent.click(screen.getByRole('button', { name: /Ajouter un compte/i }));

    await userEvent.type(screen.getByPlaceholderText('Code'), '411');
    await userEvent.type(screen.getByPlaceholderText('Libellé'), 'Clients');
    await userEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/chart',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ code: '411', label: 'Clients', type: 'asset', class: 6 }),
        }),
      ),
    );
  });

  it('désactive/active un compte via PUT /accounting/chart/{code}', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => ({ data: {} }) } as Response)
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response);

    render(<ChartPage />);

    await screen.findByText('Banque');
    const toggleButtons = screen.getAllByTitle('Activer/Désactiver');
    await userEvent.click(toggleButtons[1]); // compte 512 (non système)

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/chart/512',
        expect.objectContaining({ method: 'PUT', body: JSON.stringify({ is_active: false }) }),
      ),
    );
  });

  it('supprime un compte non système via DELETE /accounting/chart/{code}', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response)
      .mockResolvedValueOnce({ json: async () => ({ data: {} }) } as Response)
      .mockResolvedValueOnce({ json: async () => accountsPayload } as Response);

    render(<ChartPage />);

    await screen.findByText('Banque');
    const deleteButtons = screen.getAllByTitle('Supprimer');
    expect(deleteButtons).toHaveLength(2); // le compte système n'a pas de bouton supprimer
    await userEvent.click(deleteButtons[0]);

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/chart/512', { method: 'DELETE' }),
    );
  });
});
