/**
 * BC-19 DEVICE — service client du module « Caméras » (espace tenant).
 *
 * Enveloppe typée des endpoints `/api/v1/cameras/*` (privés : auth Sanctum +
 * gate `module.cameras` + RBAC `api.manager`) consommés par la page `/cameras`.
 * Tous les appels passent par `apiFetch` (proxy same-origin → cookie httpOnly),
 * jamais par une URL absolue.
 *
 * Ce qui N'EST PAS fait ici, volontairement : aucun lecteur vidéo n'est
 * embarqué. La chaîne vidéo tourne sur un **nœud Edge** installé chez le client
 * (`docs/architecture/adr/0021-chaine-video-topologie-edge.md`) ; l'API ne
 * connaît que la vérification de jeton et l'URL de flux. Afficher un lecteur
 * qui ne peut pas fonctionner serait un mensonge d'interface (#7477) — la page
 * expose donc l'URL de flux et l'état de la source, pas un faux direct.
 *
 * Contrat backend : `app/Modules/Cameras` (contrôleurs `CameraController`,
 * `CameraPermissionController`, `CameraAccessLogController`).
 */

import { apiFetch } from '@/lib/api-client';

/** Charge utile d'une caméra (`CameraService::buildStreamPayload`). */
export interface CameraStream {
  id: number;
  name: string;
  location: string | null;
  is_active: boolean;
  sort_order: number;
  thumbnail_url: string | null;
  stream_url: string;
  stream_token: string;
  token_expires_at: string;
  created_at: string | null;
}

/**
 * Limite de plan remontée par `GET /cameras` (`plan_limit`) : `max_cameras`
 * vient de `Company::features` ou du plan (`CameraService::maxCameras`), `null`
 * signifiant « illimité ».
 */
export interface CameraPlanLimit {
  max_cameras: number | null;
  current_count: number;
}

export interface CameraPermission {
  id: number;
  camera_id: number;
  employee_id: number;
  can_view: boolean;
  can_share: boolean;
  can_manage: boolean;
  granted_at: string | null;
  expires_at: string | null;
}

export interface CameraAccessLog {
  id: number;
  camera_id: number;
  employee_id: number | null;
  actor_type: string | null;
  action: string;
  reason: string | null;
  ip_address: string | null;
  created_at: string | null;
}

/**
 * Résultat d'un test de source RTSP (`POST /cameras/test-rtsp`).
 *
 * Le contrat #3147 renvoie les refus **métier** en 200 avec `ok: false`
 * (`host_not_allowed`, `invalid_url`) et les incidents en 408/422/503 : dans les
 * deux cas la raison est affichée, jamais avalée.
 */
export interface RtspTestResult {
  ok: boolean;
  error?: string;
  message?: string;
}

export interface CreateCameraInput {
  name: string;
  rtsp_url: string;
  location?: string;
}

async function readJson<T>(response: Response): Promise<T> {
  return (await response.json()) as T;
}

/** GET /cameras — liste paginée + limite de plan. */
export async function listCameras(): Promise<{ cameras: CameraStream[]; planLimit: CameraPlanLimit }> {
  const response = await apiFetch('/cameras');
  const payload = await readJson<{
    data?: CameraStream[];
    plan_limit?: CameraPlanLimit;
  }>(response);

  return {
    cameras: Array.isArray(payload.data) ? payload.data : [],
    planLimit: payload.plan_limit ?? { max_cameras: null, current_count: 0 },
  };
}

/** POST /cameras — ajoute une caméra (URL RTSP chiffrée côté serveur). */
export async function createCamera(input: CreateCameraInput): Promise<CameraStream> {
  const response = await apiFetch('/cameras', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  const payload = await readJson<{ data: CameraStream }>(response);
  return payload.data;
}

/** DELETE /cameras/{id}. */
export async function deleteCamera(cameraId: number): Promise<void> {
  await apiFetch(`/cameras/${cameraId}`, { method: 'DELETE' });
}

/** POST /cameras/test-rtsp — vérifie que la source répond (ffprobe, serveur). */
export async function testRtspSource(rtspUrl: string): Promise<RtspTestResult> {
  try {
    const response = await apiFetch('/cameras/test-rtsp', {
      method: 'POST',
      body: JSON.stringify({ rtsp_url: rtspUrl }),
    });
    const payload = await readJson<RtspTestResult>(response);
    return { ok: payload.ok === true, error: payload.error, message: payload.message };
  } catch (error) {
    // 408 / 422 / 503 : le message du serveur est plus précis que le nôtre.
    const status = (error as { status?: number }).status;
    const message = error instanceof Error ? error.message : undefined;
    return { ok: false, error: status ? `HTTP_${status}` : 'NETWORK', message };
  }
}

/** GET /cameras/{id}/permissions — autorisations individuelles. */
export async function listCameraPermissions(cameraId: number): Promise<CameraPermission[]> {
  const response = await apiFetch(`/cameras/${cameraId}/permissions`);
  const payload = await readJson<{ data?: CameraPermission[] }>(response);
  return Array.isArray(payload.data) ? payload.data : [];
}

/** GET /cameras/{id}/access-logs — journal d'accès (traçabilité vie privée). */
export async function listCameraAccessLogs(cameraId: number): Promise<CameraAccessLog[]> {
  const response = await apiFetch(`/cameras/${cameraId}/access-logs`);
  const payload = await readJson<{ data?: CameraAccessLog[] }>(response);
  return Array.isArray(payload.data) ? payload.data : [];
}

/**
 * #7476 — garde de cohérence du catalogue : les clés `HORIZONTAL_TOOLS` de
 * l'API et `SELF_ACTIVATABLE_MODULE_KEYS` du web doivent rester identiques
 * (sinon `POST /company/modules/{key}/activate` répond 422 fail-closed alors
 * que le bouton est affiché). Le test unitaire `cameras-module.test.ts`
 * verrouille la présence effective de `cameras` dans les deux listes.
 */
export const CAMERAS_MODULE_KEY = 'cameras';

/** URL RTSP : même règle que `StoreCameraRequest` (schéma `rtsp://`). */
export function isRtspUrl(value: string): boolean {
  return /^rtsp:\/\/[^\s"'<>]+$/i.test(value.trim());
}
