import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { apiFetch } from '@/lib/api-client';
import CamerasPage from '../page';

jest.mock('@/lib/api-client', () => ({
  apiFetch: jest.fn(),
  ApiError: class ApiError extends Error {
    status: number;
    code?: string;
    constructor(message: string, status: number, code?: string) {
      super(message);
      this.status = status;
      this.code = code;
    }
  },
}));

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

function jsonResponse(payload: unknown, status = 200): Response {
  return {
    json: async () => payload,
    ok: status >= 200 && status < 300,
    status,
    headers: new Headers(),
    clone: () => jsonResponse(payload, status),
  } as unknown as Response;
}

const camerasPayload = {
  data: [
    {
      id: 7,
      name: 'Entrée principale',
      location: 'Hall',
      is_active: true,
      sort_order: 1,
      stream_url: 'wss://localhost/cam/7/webrtc',
      stream_token: 'jwt-7',
    },
    {
      id: 8,
      name: 'Quai de chargement',
      location: null,
      is_active: false,
      sort_order: 2,
      stream_url: 'wss://localhost/cam/8/webrtc',
      stream_token: 'jwt-8',
    },
  ],
  meta: { current_page: 1, per_page: 100, total: 2, last_page: 1 },
  plan_limit: { max_cameras: 4, current_count: 2 },
};

beforeAll(() => {
  window.localStorage.setItem('preferred_locale', 'fr');
});

beforeEach(() => {
  jest.clearAllMocks();
});

describe('Mur de caméras (BC-19, #7425)', () => {
  it("liste les caméras du tenant avec l'état actif/inactif et la limite du plan", async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(camerasPayload));

    render(<CamerasPage />);

    expect(await screen.findByText('Entrée principale')).toBeInTheDocument();
    expect(screen.getByText('Quai de chargement')).toBeInTheDocument();
    expect(screen.getByText('Hall')).toBeInTheDocument();
    expect(screen.getByText(/Caméras actives : 2 \/ 4/)).toBeInTheDocument();
    expect(screen.getByText('Inactive')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();

    // Le mur pointe vers le détail de chaque caméra.
    const link = screen.getByText('Entrée principale').closest('a');
    expect(link).toHaveAttribute('href', '/cameras/7');
  });

  it("affiche un état vide explicite quand le tenant n'a aucune caméra", async () => {
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({ data: [], meta: { total: 0 }, plan_limit: { max_cameras: 4, current_count: 0 } }),
    );

    render(<CamerasPage />);

    expect(await screen.findByText('Aucune caméra enregistrée')).toBeInTheDocument();
  });

  it("affiche le message dédié quand l'API répond 403 (middleware module.cameras)", async () => {
    const { ApiError } = jest.requireMock('@/lib/api-client') as {
      ApiError: new (message: string, status: number, code?: string) => Error;
    };
    mockedApiFetch.mockRejectedValueOnce(new ApiError('FEATURE_NOT_ENABLED', 403, 'FEATURE_NOT_ENABLED'));

    render(<CamerasPage />);

    expect(await screen.findByText('Module Caméras non activé')).toBeInTheDocument();
    expect(screen.queryByText('Aucune caméra enregistrée')).not.toBeInTheDocument();
  });

  it('crée une caméra avec les champs du contrat API (rtsp_url, location, sort_order)', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(camerasPayload));
    render(<CamerasPage />);
    await screen.findByText('Entrée principale');

    await userEvent.click(screen.getByText('Ajouter une caméra'));
    await userEvent.type(await screen.findByLabelText('Nom'), 'Quai nord');
    await userEvent.type(screen.getByLabelText('URL RTSP'), 'rtsp://192.168.1.10:554/stream');
    await userEvent.type(screen.getByLabelText('Emplacement'), 'Quai');

    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { id: 9 } }, 201)); // POST /cameras
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(camerasPayload)); // rechargement du mur

    await userEvent.click(screen.getByText('Créer la caméra'));

    await waitFor(() => {
      const postCall = mockedApiFetch.mock.calls.find(([url, options]) =>
        String(url) === '/cameras' && options?.method === 'POST',
      );
      expect(postCall).toBeDefined();
      const body = JSON.parse(String(postCall?.[1]?.body));
      expect(body.name).toBe('Quai nord');
      expect(body.rtsp_url).toBe('rtsp://192.168.1.10:554/stream');
      expect(body.location).toBe('Quai');
      expect(body.sort_order).toBe(0);
    });

    expect(await screen.findByText('Caméra ajoutée')).toBeInTheDocument();
  });

  it('expose le test RTSP : ok:false en 200, timeout 408 et ffprobe indisponible 503', async () => {
    mockedApiFetch.mockResolvedValueOnce(jsonResponse(camerasPayload));
    render(<CamerasPage />);
    await screen.findByText('Entrée principale');

    await userEvent.click(screen.getByText('Ajouter une caméra'));
    const rtspInput = await screen.findByLabelText('URL RTSP');
    await userEvent.type(rtspInput, 'rtsp://10.0.0.1/stream');

    // 1. Refus métier en 200 (`host_not_allowed`) — contrat #3147.
    mockedApiFetch.mockResolvedValueOnce(
      jsonResponse({ ok: false, error: 'host_not_allowed', message: 'Host not allowed' }, 200),
    );
    await userEvent.click(screen.getByText('Tester le flux'));
    expect(await screen.findByText("Cible interne interdite par l'API.")).toBeInTheDocument();

    // 2. 408 → délai dépassé.
    const { ApiError } = jest.requireMock('@/lib/api-client') as {
      ApiError: new (message: string, status: number, code?: string) => Error;
    };
    mockedApiFetch.mockRejectedValueOnce(new ApiError('RTSP_TIMEOUT', 408, 'RTSP_TIMEOUT'));
    await userEvent.click(screen.getByText('Tester le flux'));
    expect(await screen.findByText("Délai dépassé : vérifiez l'URL et le réseau.")).toBeInTheDocument();

    // 3. 503 → ffprobe indisponible.
    mockedApiFetch.mockRejectedValueOnce(new ApiError('VIDEO_PROXY_UNAVAILABLE', 503, 'VIDEO_PROXY_UNAVAILABLE'));
    await userEvent.click(screen.getByText('Tester le flux'));
    expect(await screen.findByText('Proxy vidéo indisponible : réessayez plus tard.')).toBeInTheDocument();

    // 4. Succès.
    mockedApiFetch.mockResolvedValueOnce(jsonResponse({ data: { ok: true, duration_ms: 42 } }, 200));
    await userEvent.click(screen.getByText('Tester le flux'));
    expect(await screen.findByText('Flux RTSP joignable')).toBeInTheDocument();
  });
});
