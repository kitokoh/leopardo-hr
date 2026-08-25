import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { ApiError, apiFetch } from '@/lib/api-client';

import { SharedDocumentView } from '../shared-document-view';

jest.mock('@/lib/api-client', () => {
  class MockApiError extends Error {
    status: number;
    code?: string;
    constructor(message: string, status: number, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  }
  return {
    apiFetch: jest.fn(),
    ApiError: MockApiError,
  };
});

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

const documentPayload = {
  data: {
    number: 'FAC-2026-0042',
    type: 'invoice',
    type_label: 'Facture',
    status: 'sent',
    issue_date: '2026-08-20',
    currency: 'DZD',
    total_ttc: 125000.5,
    expires_at: '2026-09-06T00:00:00+00:00',
  },
};

function mockSuccess(payload: unknown = documentPayload) {
  mockedApiFetch.mockResolvedValue({ json: async () => payload } as Response);
}

function mockNotFound() {
  mockedApiFetch.mockRejectedValue(new ApiError('DOCUMENT_SHARE_NOT_FOUND', 404));
}

function mockNetworkError() {
  mockedApiFetch.mockRejectedValue(new Error('fetch failed'));
}

describe('SharedDocumentView — portail client documents (#5233)', () => {
  beforeEach(() => {
    // mockReset() purge aussi les implémentations `*Once` éventuellement
    // non consommées du test précédent — isolation stricte entre tests.
    mockedApiFetch.mockReset();
    mockSuccess();
  });

  it('affiche le résumé du document partagé (numéro, type, statut, dates, total)', async () => {
    render(<SharedDocumentView token="abc123" locale="fr" />);

    expect(await screen.findByText('FAC-2026-0042')).toBeInTheDocument();
    expect(screen.getByText('Facture')).toBeInTheDocument();
    expect(screen.getByText('Envoyé')).toBeInTheDocument();
    // Intl fr-FR : 125 000,50 DZD
    expect(screen.getByText(/125\s?000[,.]5\s?0?\s?DZD/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Télécharger/i })).toBeInTheDocument();
  });

  it('appelle l’endpoint public avec le token et la locale en Accept-Language', async () => {
    render(<SharedDocumentView token="tok_123" locale="ar" />);

    await screen.findByText(/مساحة المستندات الآمنة/);
    expect(mockedApiFetch).toHaveBeenCalledWith(
      '/accounting/documents/shared/tok_123',
      expect.objectContaining({ headers: { 'Accept-Language': 'ar' } }),
    );
  });

  it('affiche l’écran « lien invalide ou expiré » sur 404', async () => {
    mockNotFound();

    render(<SharedDocumentView token="expired" locale="fr" />);

    expect(await screen.findByText('Lien invalide ou expiré')).toBeInTheDocument();
    expect(screen.queryByText('FAC-2026-0042')).not.toBeInTheDocument();
  });

  it('affiche l’écran d’erreur réseau puis recharge via « Réessayer »', async () => {
    mockNetworkError();

    render(<SharedDocumentView token="tok" locale="fr" />);

    expect(await screen.findByText('Impossible de charger le document')).toBeInTheDocument();

    mockSuccess();
    await userEvent.click(screen.getByRole('button', { name: /Réessayer/i }));

    expect(await screen.findByText('FAC-2026-0042')).toBeInTheDocument();
    expect(mockedApiFetch).toHaveBeenCalledTimes(2);
  });

  it('télécharge le PDF via l’endpoint /download (blob + ancre)', async () => {
    const createObjectURL = jest.fn(() => 'blob:mock');
    const revokeObjectURL = jest.fn();
    const clickSpy = jest.fn();
    URL.createObjectURL = createObjectURL;
    URL.revokeObjectURL = revokeObjectURL;
    HTMLAnchorElement.prototype.click = clickSpy;

    render(<SharedDocumentView token="tok" locale="fr" />);
    await screen.findByText('FAC-2026-0042');

    mockedApiFetch.mockResolvedValueOnce({
      blob: async () => new Blob(['pdf-bytes'], { type: 'application/pdf' }),
    } as Response);

    await userEvent.click(screen.getByRole('button', { name: /Télécharger/i }));

    await waitFor(() => {
      expect(mockedApiFetch).toHaveBeenCalledWith(
        '/accounting/documents/shared/tok/download',
        expect.objectContaining({ headers: { 'Accept-Language': 'fr' } }),
      );
    });
    expect(clickSpy).toHaveBeenCalled();
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:mock');
  });

  it('affiche une erreur explicite si le téléchargement échoue', async () => {
    render(<SharedDocumentView token="tok" locale="fr" />);
    await screen.findByText('FAC-2026-0042');

    mockedApiFetch.mockRejectedValueOnce(new ApiError('DOCUMENT_PDF_NOT_READY', 404));

    await userEvent.click(screen.getByRole('button', { name: /Télécharger/i }));

    expect(await screen.findByText(/Le téléchargement a échoué/)).toBeInTheDocument();
  });

  it('affiche l’expiration du lien au format localisé', async () => {
    render(<SharedDocumentView token="tok" locale="fr" />);

    expect(await screen.findByText(/Lien valide jusqu'au/)).toBeInTheDocument();
  });

  it('badge de statut « payé » en vert / « en retard » en rouge', async () => {
    mockSuccess({
      data: { ...documentPayload.data, status: 'overdue' },
    });

    render(<SharedDocumentView token="tok" locale="fr" />);

    const badge = await screen.findByText('En retard');
    expect(badge.className).toContain('bg-red-50');
  });
});
