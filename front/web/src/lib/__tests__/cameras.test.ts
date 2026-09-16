import { apiFetch, ApiError } from '@/lib/api-client';
import {
  CAMERAS_MODULE_KEY,
  buildCameraViewerUrl,
  createCameraAccessToken,
  fetchPublicCameraView,
  isRtspUrl,
  listCameraAccessTokens,
  readCameraTokenFromUrl,
  listCameraAccessLogs,
  listCameraPermissions,
  listCameras,
  testRtspSource,
} from '../cameras';

/**
 * BC-19 DEVICE — contrat client du module Caméras (#7476 + #7425 tranche 1).
 *
 * Ce qui est verrouillé ici :
 *  1. la **forme** des réponses API consommées par la page (`plan_limit`,
 *     liste vide tolérée) — une divergence ferait afficher un module vide ;
 *  2. l'**honnêteté** du test de source : un refus métier (`ok: false` en 200,
 *     contrat #3147) comme un incident HTTP (408/503) sont remontés à l'UI,
 *     jamais transformés en succès ;
 *  3. la validation RTSP locale, alignée sur `StoreCameraRequest` ;
 *  4. la clé de module, qui doit rester dans l'allowlist serveur.
 */

jest.mock('@/lib/api-client', () => {
  const actual = jest.requireActual('@/lib/api-client');
  return { ...actual, apiFetch: jest.fn() };
});

const mockedApiFetch = apiFetch as jest.MockedFunction<typeof apiFetch>;

type FakeResponse = { ok: boolean; status: number; json: () => Promise<unknown> };

const jsonResponse = (status: number, body: unknown): FakeResponse => ({
  ok: status >= 200 && status < 300,
  status,
  json: async () => body,
});

describe('lib/cameras — contrat client', () => {
  beforeEach(() => {
    mockedApiFetch.mockReset();
  });

  it('lit la liste et la limite de plan (contrat GET /cameras)', async () => {
    mockedApiFetch.mockResolvedValue(
      jsonResponse(200, {
        data: [
          {
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
          },
        ],
        plan_limit: { max_cameras: 4, current_count: 1 },
      }) as unknown as Response,
    );

    const payload = await listCameras();

    expect(payload.cameras).toHaveLength(1);
    expect(payload.cameras[0].name).toBe('Entrée atelier');
    expect(payload.planLimit).toEqual({ max_cameras: 4, current_count: 1 });
  });

  it('tolère une réponse sans plan_limit (aucune limite inventée)', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse(200, { data: [] }) as unknown as Response);

    const payload = await listCameras();

    expect(payload.cameras).toEqual([]);
    // `null` = illimité : on n'invente pas 0, qui ferait croire à un quota atteint.
    expect(payload.planLimit).toEqual({ max_cameras: null, current_count: 0 });
  });

  it('remonte un refus métier du test RTSP (ok: false en 200, contrat #3147)', async () => {
    mockedApiFetch.mockResolvedValue(
      jsonResponse(200, { ok: false, error: 'host_not_allowed', message: 'Host not allowed' }) as unknown as Response,
    );

    const result = await testRtspSource('rtsp://10.0.0.5:554/stream');

    expect(result.ok).toBe(false);
    expect(result.error).toBe('host_not_allowed');
    expect(result.message).toBe('Host not allowed');
  });

  it("ne transforme pas un incident HTTP en succès de test", async () => {
    mockedApiFetch.mockRejectedValue(new ApiError('Video proxy unavailable', 503, 'VIDEO_PROXY_UNAVAILABLE'));

    const result = await testRtspSource('rtsp://192.168.1.20:554/stream');

    expect(result.ok).toBe(false);
    expect(result.error).toBe('HTTP_503');
  });

  it("accepte un flux rtsp:// et rejette ce qui n'en est pas", () => {
    expect(isRtspUrl('rtsp://192.168.1.20:554/stream')).toBe(true);
    expect(isRtspUrl('rtsps://cam.local/stream')).toBe(false);
    expect(isRtspUrl('http://192.168.1.20/stream')).toBe(false);
    expect(isRtspUrl('rtsp://192.168.1.20/str eam')).toBe(false);
  });

  it('lit les accès (permissions et journal) sans casser sur une réponse vide', async () => {
    mockedApiFetch.mockResolvedValue(jsonResponse(200, {}) as unknown as Response);

    await expect(listCameraPermissions(3)).resolves.toEqual([]);
    await expect(listCameraAccessLogs(3)).resolves.toEqual([]);
  });

  it('expose la clé de module attendue par l’allowlist serveur', () => {
    expect(CAMERAS_MODULE_KEY).toBe('cameras');
  });
});

describe('lib/cameras — partage et viewer (#7425 tranche 2)', () => {
  beforeEach(() => {
    mockedApiFetch.mockReset();
  });

  it('place le jeton dans le FRAGMENT, jamais dans la chaîne de requête', () => {
    const url = buildCameraViewerUrl('https://app.leopardo.test', 'jeton-abc');

    expect(url).toBe('https://app.leopardo.test/view/cam#t=jeton-abc');
    // Un jeton en query string fuit dans les journaux du proxy et le Referer
    // (#4931/#6560) : c'est la propriété que ce test verrouille.
    expect(url).not.toContain('?t=');
    expect(new URL(url).searchParams.has('t')).toBe(false);
  });

  it('lit le jeton du fragment et signale la forme héritée', () => {
    expect(readCameraTokenFromUrl('https://app.test/view/cam#t=abc')).toEqual({ token: 'abc', legacy: false });
    // Fragment sans clé (lien « #abc ») : accepté aussi.
    expect(readCameraTokenFromUrl('https://app.test/view/cam#t%3Dabc')).toEqual({ token: null, legacy: false });
    // Ancien lien : jeton en query string → non utilisé, signalé.
    expect(readCameraTokenFromUrl('https://app.test/view/cam?t=abc')).toEqual({ token: null, legacy: true });
    expect(readCameraTokenFromUrl('https://app.test/view/cam')).toEqual({ token: null, legacy: false });
  });

  it('crée un lien de partage et lit la liste des liens', async () => {
    mockedApiFetch
      .mockResolvedValueOnce(
        jsonResponse(201, {
          data: { id: 3, camera_id: 7, label: 'Assureur', expires_in_minutes: 60, token: 'jeton', share_url: 'https://app.test/view/cam#t=jeton' },
        }) as unknown as Response,
      )
      .mockResolvedValueOnce(jsonResponse(200, { data: [{ id: 3, camera_id: 7, is_revoked: false }] }) as unknown as Response);

    const created = await createCameraAccessToken(7, { label: 'Assureur', expires_in_minutes: 60 });
    const listed = await listCameraAccessTokens(7);

    expect(created.share_url).toContain('#t=jeton');
    expect(created.token).toBe('jeton');
    expect(listed).toHaveLength(1);
    // Le jeton brut n'est jamais relu en liste.
    expect(listed[0].token).toBeUndefined();
  });

  it('appelle GET /view/cam avec l’en-tête X-Token et mappe les refus', async () => {
    const fetchMock = jest.fn()
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ data: { camera: { id: 1, name: 'Cam', location: null }, stream_url: 'wss://x', stream_token: 'j', expires_at: null, permissions: null, label: null } }) })
      .mockResolvedValueOnce({ ok: false, status: 404, json: async () => ({ error: 'INVALID_TOKEN' }) })
      .mockResolvedValueOnce({ ok: false, status: 404, json: async () => ({ error: 'CAMERA_NOT_FOUND' }) });
    global.fetch = fetchMock as unknown as typeof fetch;

    const ok = await fetchPublicCameraView('jeton');
    const invalid = await fetchPublicCameraView('jeton-mort');
    const off = await fetchPublicCameraView('jeton-cam-off');

    expect(ok.ok).toBe(true);
    expect(invalid).toEqual({ ok: false, reason: 'invalid_token' });
    expect(off).toEqual({ ok: false, reason: 'camera_unavailable' });

    const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect((init.headers as Record<string, string>)['X-Token']).toBe('jeton');
  });
});
