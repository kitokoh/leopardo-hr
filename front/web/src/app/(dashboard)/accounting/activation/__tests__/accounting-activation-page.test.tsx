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

describe('AccountingActivationPage (#5626 wizard)', () => {
  it("affiche l'étape 1 du wizard (langue + devise) quand le module n'est pas activé", async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => pendingStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByRole('heading', { name: /Identité & langue des documents/ })).toBeInTheDocument();
    expect(screen.getByText('Langue des documents')).toBeInTheDocument();
    expect(screen.getByText('Devise')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Continuer/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Retour/i })).toBeDisabled();
    expect(mockedApiFetch).toHaveBeenCalledWith('/accounting/activation');
  });

  it('parcourt les 4 étapes, collecte le paramétrage et active via POST /accounting/activation/complete', async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response)
      .mockResolvedValueOnce({ json: async () => completedStatus } as Response);

    render(<AccountingActivationPage />);

    // Step 1 — langue + devise
    await screen.findByRole('heading', { name: /Identité & langue des documents/ });
    await userEvent.selectOptions(screen.getByText('Langue des documents').parentElement!.querySelector('select')!, 'fr');
    await userEvent.selectOptions(screen.getByText('Devise').parentElement!.querySelector('select')!, 'DZD');
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));

    // Step 2 — TVA + séries
    expect(await screen.findByRole('heading', { name: /TVA & séries de numérotation/ })).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Libellé du taux 1'), 'TVA standard');
    await userEvent.type(screen.getByLabelText('Taux (%) 1'), '19');
    await userEvent.type(screen.getByLabelText('Facture'), 'FAC-');
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));

    // Step 3 — modèle PDF & mentions
    expect(await screen.findByRole('heading', { name: /Modèle PDF & mentions légales/ })).toBeInTheDocument();
    await userEvent.type(screen.getByLabelText('Modèle PDF'), 'moderne');
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));

    // Step 4 — récapitulatif + activation
    expect(await screen.findByRole('heading', { name: /Finaliser l'activation/ })).toBeInTheDocument();
    expect(screen.getByText('DZD')).toBeInTheDocument();
    expect(screen.getByText('TVA standard (19%)')).toBeInTheDocument();
    expect(screen.getByText(/Facture : FAC-/)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Activer la Comptabilité/i }));

    await waitFor(() =>
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/activation/complete',
        expect.objectContaining({ method: 'POST' }),
      ),
    );

    const payload = JSON.parse(
      (mockedApiFetch.mock.calls.find(([endpoint]) => endpoint === '/accounting/activation/complete')![1] as RequestInit)
        .body as string,
    );
    expect(payload).toEqual({
      currency: 'DZD',
      document_language: 'fr',
      tva_rates: [{ label: 'TVA standard', rate: 19 }],
      number_series: { invoice: 'FAC-' },
      template_style: 'moderne',
    });

    expect(await screen.findByText('Comptabilité activée')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Accéder au module Comptabilité/i })).toHaveAttribute('href', '/accounting');
  });

  it('permet d\'ajouter et supprimer une ligne de taux TVA', async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => pendingStatus } as Response);

    render(<AccountingActivationPage />);
    await screen.findByRole('heading', { name: /Identité & langue des documents/ });
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));

    await screen.findByRole('heading', { name: /TVA & séries de numérotation/ });
    await userEvent.click(screen.getByRole('button', { name: /Ajouter un taux/i }));
    expect(screen.getByLabelText('Libellé du taux 2')).toBeInTheDocument();

    await userEvent.click(screen.getAllByRole('button', { name: /Supprimer ce taux/i })[1]);
    expect(screen.queryByLabelText('Libellé du taux 2')).not.toBeInTheDocument();
  });

  it("affiche directement l'état complet si le module est déjà activé", async () => {
    mockedApiFetch.mockResolvedValue({ json: async () => completedStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByText('Comptabilité activée')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Accéder au module Comptabilité/i })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /Identité & langue/ })).not.toBeInTheDocument();
  });

  it('affiche une erreur si le chargement échoue, avec bouton réessayer', async () => {
    mockedApiFetch
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response);

    render(<AccountingActivationPage />);

    expect(await screen.findByText(/Impossible de charger l'état d'activation/i)).toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Réessayer/i }));
    expect(await screen.findByRole('heading', { name: /Identité & langue des documents/ })).toBeInTheDocument();
  });

  it("affiche une erreur si l'activation échoue", async () => {
    mockedApiFetch
      .mockResolvedValueOnce({ json: async () => pendingStatus } as Response)
      .mockRejectedValueOnce(new Error('server'));

    render(<AccountingActivationPage />);

    await screen.findByRole('heading', { name: /Identité & langue des documents/ });
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));
    await screen.findByRole('heading', { name: /TVA & séries de numérotation/ });
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));
    await screen.findByRole('heading', { name: /Modèle PDF & mentions légales/ });
    await userEvent.click(screen.getByRole('button', { name: /Continuer/i }));
    await screen.findByRole('heading', { name: /Finaliser l'activation/ });
    await userEvent.click(screen.getByRole('button', { name: /Activer la Comptabilité/i }));

    expect(await screen.findByText(/L'activation a échoué/i)).toBeInTheDocument();
  });
});
