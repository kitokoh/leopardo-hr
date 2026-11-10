import { render, screen, waitFor } from '@testing-library/react';

import { apiFetch } from '@/lib/api-client';
import { MOBILE_DISTRIBUTION_STATUS } from '@/lib/mobile-distribution-status';
import AccountPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;

    constructor(message: string, status = 400, code?: string) {
      super(message);
      this.name = 'ApiError';
      this.status = status;
      this.code = code;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

/**
 * #9300 — carte « Applications mobiles » du profil.
 *
 * Verrouillé ici : la carte n'est rendue QUE si le tenant a une app mobile de
 * stock réellement distribuable (verticale activée ET canal sain). Un tenant
 * non concerné ne voit RIEN (pas de carte vide, pas de bouton mort) — c'est
 * le critère d'acceptation de l'issue.
 */

function mockApiRoutes() {
  mockedApiFetch.mockImplementation(async (url: string) => {
    if (url === '/auth/me') {
      return { json: async () => ({ data: null }) } as Response;
    }
    if (url === '/auth/2fa/status') {
      return { json: async () => ({ data: { enabled: false } }) } as Response;
    }
    return { json: async () => ({ data: null }) } as Response;
  });
}

function storeSession(features: Record<string, boolean>) {
  window.localStorage.setItem(
    'auth_user',
    JSON.stringify({ role: 'manager', language: 'fr', company: { features } }),
  );
}

const managerChannelHealthy =
  MOBILE_DISTRIBUTION_STATUS.manager !== 'failed' && MOBILE_DISTRIBUTION_STATUS.manager !== 'unknown';

beforeEach(() => {
  jest.clearAllMocks();
  window.localStorage.clear();
  window.localStorage.setItem('preferred_locale', 'fr');
  mockApiRoutes();
});

describe('AccountPage (#9300) — accès au téléchargement mobile', () => {
  it('un tenant sans verticale de stock ne voit AUCUNE carte', async () => {
    storeSession({ dashboard: true });

    render(<AccountPage />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));

    expect(screen.queryByTestId('account-mobile-download-card')).not.toBeInTheDocument();
    expect(screen.queryByTestId('account-mobile-download-link')).not.toBeInTheDocument();
  });

  it('une verticale sans app distribuable (commerce) ne voit aucune carte', async () => {
    storeSession({ retail: true, dashboard: true });

    render(<AccountPage />);

    await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));

    expect(screen.queryByTestId('account-mobile-download-card')).not.toBeInTheDocument();
  });

  it('un restaurateur voit la carte et le lien quand le canal est sain', async () => {
    if (!managerChannelHealthy) {
      // Canal `manager` en échec : la carte ne doit PAS apparaître (règle
      // d'honnêteté #6697 — jamais de bouton vers un build inexistant).
      storeSession({ restaurantmanager: true, dashboard: true });
      render(<AccountPage />);
      await waitFor(() => expect(mockedApiFetch).toHaveBeenCalledWith('/auth/me'));
      expect(screen.queryByTestId('account-mobile-download-card')).not.toBeInTheDocument();
      return;
    }

    storeSession({ restaurantmanager: true, dashboard: true });

    render(<AccountPage />);

    expect(await screen.findByTestId('account-mobile-download-card')).toBeInTheDocument();

    const link = screen.getByTestId('account-mobile-download-link');
    expect(link.getAttribute('href')).toBeTruthy();

    // Le web reste la référence : renvoi vers le catalogue complet.
    expect(screen.getByTestId('account-mobile-catalog-link')).toHaveAttribute(
      'href',
      '/mon-logiciel',
    );
  });
});
