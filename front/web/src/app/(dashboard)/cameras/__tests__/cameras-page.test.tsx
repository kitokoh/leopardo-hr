import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CamerasModulePage from '../page';
import {
  createCamera,
  deleteCamera,
  listCameras,
  testRtspSource,
} from '@/lib/cameras';

/**
 * #7476 + #7425 (tranche 1) — page `/cameras` : ce que le test verrouille.
 *
 *  1. la page dit la **vérité sur la chaîne vidéo** (nœud Edge requis) au lieu
 *     d'afficher un lecteur qui ne peut pas fonctionner ;
 *  2. elle affiche la **limite de plan** renvoyée par l'API (le client doit
 *     savoir où il en est avant d'ajouter une caméra) ;
 *  3. une URL RTSP invalide est refusée **côté client** (aucun appel réseau) ;
 *  4. l'ajout, le test de source et la suppression appellent bien le service,
 *     et un échec de suppression reste visible (jamais d'échec silencieux).
 */

jest.mock('@/lib/i18n', () => {
  const actual = jest.requireActual('@/lib/i18n');
  return { ...actual, getPreferredLocale: () => 'fr' };
});

jest.mock('@/lib/cameras', () => ({
  __esModule: true,
  CAMERAS_MODULE_KEY: 'cameras',
  isRtspUrl: (value: string) => /^rtsp:\/\/[^\s"'<>]+$/i.test(value.trim()),
  listCameras: jest.fn(),
  createCamera: jest.fn(),
  deleteCamera: jest.fn(),
  testRtspSource: jest.fn(),
  listCameraPermissions: jest.fn().mockResolvedValue([]),
  listCameraAccessLogs: jest.fn().mockResolvedValue([]),
}));

const mockedList = listCameras as jest.MockedFunction<typeof listCameras>;
const mockedCreate = createCamera as jest.MockedFunction<typeof createCamera>;
const mockedDelete = deleteCamera as jest.MockedFunction<typeof deleteCamera>;
const mockedTest = testRtspSource as jest.MockedFunction<typeof testRtspSource>;

const camera = {
  id: 7,
  name: 'Entrée atelier',
  location: 'Atelier',
  is_active: true,
  sort_order: 0,
  thumbnail_url: null,
  stream_url: 'wss://proxy.example/cam/atelier',
  stream_token: 'tok',
  token_expires_at: '2026-09-16T12:00:00Z',
  created_at: '2026-09-01T08:00:00Z',
};

async function typeRtsp(value: string) {
  await userEvent.type(screen.getByTestId('camera-field-rtsp'), value);
}

describe('page /cameras — module Caméras (#7476, #7425)', () => {
  beforeEach(() => {
    mockedList.mockReset();
    mockedCreate.mockReset();
    mockedDelete.mockReset();
    mockedTest.mockReset();
    mockedList.mockResolvedValue({ cameras: [camera], planLimit: { max_cameras: 4, current_count: 1 } });
    window.confirm = jest.fn(() => true);
  });

  it('affiche la limite de plan et dit que le direct exige un nœud Edge', async () => {
    render(<CamerasModulePage />);

    await waitFor(() => {
      expect(screen.getByTestId('cameras-plan-limit')).toBeInTheDocument();
    });

    // Le quota vient de l'API : 1 caméra configurée sur 4.
    expect(screen.getByTestId('cameras-plan-limit').textContent).toContain('1');
    expect(screen.getByTestId('cameras-plan-limit').textContent).toContain('4');

    // L'état de la chaîne vidéo est annoncé (ADR-0021) — pas de faux direct.
    const notice = screen.getByTestId('cameras-edge-notice');
    expect(notice.textContent).toMatch(/Edge/i);
    expect(notice.textContent).toMatch(/ADR-0021/);

    expect(screen.getByTestId('camera-row-7')).toBeInTheDocument();
  });

  it('refuse une URL RTSP invalide sans appeler l’API', async () => {
    render(<CamerasModulePage />);
    await waitFor(() => expect(screen.getByTestId('camera-field-rtsp')).toBeInTheDocument());

    await userEvent.type(screen.getByTestId('camera-field-name'), 'Caméra test');
    await typeRtsp('http://192.168.1.20/stream');
    await userEvent.click(screen.getByTestId('camera-submit'));

    await waitFor(() => {
      expect(screen.getByTestId('camera-form-error')).toBeInTheDocument();
    });
    expect(mockedCreate).not.toHaveBeenCalled();
  });

  it('ajoute une caméra et l’affiche dans la liste', async () => {
    mockedCreate.mockResolvedValue({ ...camera, id: 9, name: 'Quai' });

    render(<CamerasModulePage />);
    await waitFor(() => expect(screen.getByTestId('camera-field-name')).toBeInTheDocument());

    await userEvent.type(screen.getByTestId('camera-field-name'), ' Quai ');
    await typeRtsp('rtsp://192.168.1.21:554/stream');
    await userEvent.type(screen.getByTestId('camera-field-location'), 'Quai de chargement');
    await userEvent.click(screen.getByTestId('camera-submit'));

    await waitFor(() => {
      expect(screen.getByTestId('camera-row-9')).toBeInTheDocument();
    });

    expect(mockedCreate).toHaveBeenCalledWith({
      name: 'Quai',
      rtsp_url: 'rtsp://192.168.1.21:554/stream',
      location: 'Quai de chargement',
    });
  });

  it('affiche le résultat du test de source (joignable comme injoignable)', async () => {
    mockedTest.mockResolvedValueOnce({ ok: true });
    render(<CamerasModulePage />);
    await waitFor(() => expect(screen.getByTestId('camera-field-rtsp')).toBeInTheDocument());

    await typeRtsp('rtsp://192.168.1.20:554/stream');
    await userEvent.click(screen.getByTestId('camera-test-source'));

    await waitFor(() => {
      expect(screen.getByTestId('camera-form-test').textContent).toMatch(/joignable/i);
    });

    mockedTest.mockResolvedValueOnce({ ok: false, error: 'host_not_allowed', message: 'Host not allowed' });
    await userEvent.click(screen.getByTestId('camera-test-source'));

    await waitFor(() => {
      const result = screen.getByTestId('camera-form-test');
      expect(result.textContent).toMatch(/injoignable/i);
      expect(result.textContent).toContain('Host not allowed');
    });
  });

  it('ne supprime qu’après confirmation, et signale un échec de suppression', async () => {
    render(<CamerasModulePage />);
    await waitFor(() => expect(screen.getByTestId('camera-delete-7')).toBeInTheDocument());

    // Refus de confirmation → aucune suppression.
    window.confirm = jest.fn(() => false);
    await userEvent.click(screen.getByTestId('camera-delete-7'));
    expect(mockedDelete).not.toHaveBeenCalled();

    // Confirmation → suppression appelée, ligne retirée.
    window.confirm = jest.fn(() => true);
    mockedDelete.mockResolvedValueOnce(undefined);
    await userEvent.click(screen.getByTestId('camera-delete-7'));

    await waitFor(() => {
      expect(mockedDelete).toHaveBeenCalledWith(7);
      expect(screen.queryByTestId('camera-row-7')).not.toBeInTheDocument();
    });

  });

  it('signale un échec de suppression (jamais d’échec silencieux)', async () => {
    mockedDelete.mockRejectedValueOnce(new Error('forbidden'));

    render(<CamerasModulePage />);
    await waitFor(() => expect(screen.getByTestId('camera-delete-7')).toBeInTheDocument());

    await userEvent.click(screen.getByTestId('camera-delete-7'));

    await waitFor(() => {
      expect(screen.getByTestId('cameras-error')).toBeInTheDocument();
    });
    // La caméra n'a pas été retirée de la liste : l'état affiché reste vrai.
    expect(screen.getByTestId('camera-row-7')).toBeInTheDocument();
  });
});
