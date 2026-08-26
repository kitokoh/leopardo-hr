import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import AccountingActivationPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {},
}));

jest.mock('next/link', () => {
  const Link = ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
  return Link;
});

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const pendingStatus = {
  data: {
    completed: false,
    steps: { settings: true, contact: false, example_invoice: false },
    contact: null,
    example_invoice: null,
  },
};

const completedStatus = {
  data: {
    completed: true,
    steps: { settings: true, contact: true, example_invoice: true },
    contact: { id: 1, name: 'Client Test' },
    example_invoice: { id: 1, number: 'FAC-2026-0001' },
  },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('AccountingActivationPage (#5539)', () => {
  it('affiche la check-list avec les états fait/à faire', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => pendingStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByText('Paramétrage comptable')).toBeInTheDocument();
    expect(screen.getAllByText('Fait').length).toBeGreaterThan(0);
    expect(screen.getAllByText('À faire').length).toBe(2);
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/activation');
  });

  it('termine l\'activation via POST /accounting/activation/complete puis affiche l\'état complet', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response)
      .mockResolvedValueOnce({ json: async () => completedStatus } as Response);

    render(<AccountingActivationPage />);

    await screen.findByText('Paramétrage comptable');
    await userEvent.click(screen.getByRole('button', { name: /Terminer l'activation/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/activation/complete', { method: 'POST' }),
    );

    expect(await screen.findByText('Comptabilité activée')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Accéder au module Comptabilité/i })).toHaveAttribute('href', '/accounting');
  });

  it('affiche directement l\'état complet si le module est déjà activé', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => completedStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByText('Comptabilité activée')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Accéder au module Comptabilité/i })).toBeInTheDocument();
  });

  it('affiche une erreur si le chargement échoue, avec bouton réessayer', async () => {
    mockedApiFetch
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByText(/Impossible de charger l'état d'activation/i)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Réessayer/i }));
    expect(await screen.findByText('Paramétrage comptable')).toBeInTheDocument();
  });

  it('affiche une erreur si l\'activation échoue', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response)
      .mockRejectedValueOnce(new Error('server'));

    render(<AccountingActivationPage />);

    await screen.findByText('Paramétrage comptable');
    await userEvent.click(screen.getByRole('button', { name: /Terminer l'activation/i }));

    expect(await screen.findByText(/L'activation a échoué/i)).toBeInTheDocument();
  });
});
