import { render, screen, waitFor } from '@testing-library/react';
import ViewerClient from '../ViewerClient';
import { fetchPublicCameraView } from '@/lib/cameras';

/**
 * #7425 (tranche 2) — viewer tiers, sans session.
 *
 * Verrouillé ici :
 *  1. le jeton est lu dans le **fragment** (`#t=…`) et envoyé à l'API ;
 *  2. un jeton resté dans la **chaîne de requête** (anciens liens) n'est PAS
 *     utilisé — il est déjà journalisé — et la page l'explique ;
 *  3. un lien révoqué/expiré et « caméra indisponible » sont distingués ;
 *  4. aucune image n'est simulée : l'absence de chaîne vidéo (nœud Edge,
 *     ADR-0021) est dite.
 */

jest.mock('@/lib/i18n', () => {
  const actual = jest.requireActual('@/lib/i18n');
  return { ...actual, getPreferredLocale: () => 'fr' };
});

jest.mock('@/lib/cameras', () => ({
  __esModule: true,
  fetchPublicCameraView: jest.fn(),
  readCameraTokenFromUrl: (href: string) => {
    const url = new URL(href, 'http://localhost');
    const hash = url.hash.replace(/^#/, '');
    const token = new URLSearchParams(hash).get('t');
    if (token) return { token, legacy: false };
    return { token: null, legacy: url.searchParams.has('t') };
  },
}));

const mockedFetch = fetchPublicCameraView as jest.MockedFunction<typeof fetchPublicCameraView>;

function setUrl(url: string) {
  window.history.replaceState({}, '', url);
}

describe('viewer tiers /view/cam (#7425)', () => {
  beforeEach(() => {
    mockedFetch.mockReset();
    setUrl('/view/cam');
  });

  it('lit le jeton du fragment, interroge l’API et affiche la caméra', async () => {
    setUrl('/view/cam#t=jeton-partage');
    mockedFetch.mockResolvedValue({
      ok: true,
      view: {
        camera: { id: 7, name: 'Entrée atelier', location: 'Atelier' },
        stream_url: 'wss://proxy.example/cam/7/webrtc',
        stream_token: 'jeton-partage',
        expires_at: '2026-09-17T10:00:00Z',
        permissions: { view: true },
        label: 'Assureur',
      },
    });

    render(<ViewerClient />);

    await waitFor(() => {
      expect(screen.getByTestId('viewer-ready')).toBeInTheDocument();
    });

    // Le jeton du fragment est bien celui transmis à l'API (en-tête côté lib).
    expect(mockedFetch).toHaveBeenCalledWith('jeton-partage');
    expect(screen.getByTestId('viewer-camera-name').textContent).toContain('Entrée atelier');
    // La chaîne vidéo exige un nœud Edge : c'est dit, aucune image n'est simulée.
    expect(screen.getByTestId('viewer-edge-notice').textContent).toMatch(/ADR-0021/);
    expect(screen.getByTestId('viewer-stream-url').textContent).toContain('wss://proxy.example');
  });

  it('refuse un jeton resté dans la chaîne de requête (lien hérité) sans appeler l’API', async () => {
    setUrl('/view/cam?t=jeton-fuite');

    render(<ViewerClient />);

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toMatch(/chaîne de requête/i);
    // Le point capital : on n'envoie pas un jeton déjà journalisé par les proxys.
    expect(mockedFetch).not.toHaveBeenCalled();
  });

  it('dit qu’un lien sans jeton est incomplet', async () => {
    setUrl('/view/cam');

    render(<ViewerClient />);

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toMatch(/incomplet/i);
    expect(mockedFetch).not.toHaveBeenCalled();
  });

  it('distingue un lien révoqué/expiré d’une caméra indisponible', async () => {
    setUrl('/view/cam#t=jeton-revoque');
    mockedFetch.mockResolvedValueOnce({ ok: false, reason: 'invalid_token' });

    const { unmount } = render(<ViewerClient />);
    await waitFor(() => {
      expect(screen.getByRole('alert').textContent).toMatch(/plus valide/i);
    });
    unmount();

    setUrl('/view/cam#t=jeton-cam-off');
    mockedFetch.mockResolvedValueOnce({ ok: false, reason: 'camera_unavailable' });
    render(<ViewerClient />);

    await waitFor(() => {
      expect(screen.getByRole('alert').textContent).toMatch(/pas accessible/i);
    });
  });

  it('propose de réessayer après un incident réseau', async () => {
    setUrl('/view/cam#t=jeton');
    mockedFetch.mockResolvedValue({ ok: false, reason: 'network' });

    render(<ViewerClient />);

    await waitFor(() => {
      expect(screen.getByTestId('viewer-retry')).toBeInTheDocument();
    });
    expect(mockedFetch).toHaveBeenCalledTimes(1);
  });
});
