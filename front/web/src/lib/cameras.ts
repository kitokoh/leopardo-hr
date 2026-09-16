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

import { apiFetch, resolveApiBaseUrl } from '@/lib/api-client';

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

/* ------------------------------------------------------------------------- *
 * #7425 (tranche 2) — partage à un tiers et viewer sans compte.
 *
 * Le jeton d'accès tiers est un **porteur** : qui l'a voit le flux. Deux règles
 * en découlent, et elles ne sont pas négociables côté client :
 *
 *  1. le jeton ne voyage **jamais** dans la chaîne de requête — il est placé
 *     dans le **fragment** de l'URL (`#t=…`), que le navigateur ne transmet
 *     pas au serveur : ni journaux du proxy/CDN, ni en-tête `Referer`. C'est la
 *     règle déjà appliquée par `PublicCameraViewerController` (#4931/#6560,
 *     qui a supprimé le repli `?t=`) ;
 *  2. l'API n'accepte le jeton que par l'en-tête **`X-Token`** (contrôleur
 *     public) : la page le lit dans le fragment et le transporte en en-tête.
 * ------------------------------------------------------------------------- */

export interface CameraAccessToken {
  id: number;
  camera_id: number;
  label: string | null;
  granted_to_email: string | null;
  granted_to_name: string | null;
  granted_by: number;
  permissions: Record<string, unknown> | null;
  expires_at: string | null;
  last_used_at: string | null;
  use_count: number;
  is_revoked: boolean;
  created_at: string | null;
  /** Renseigné UNIQUEMENT à la création (le token n'est jamais relu ensuite). */
  token?: string;
  /** Lien prêt à partager, construit par l'API (fragment, jamais de query). */
  share_url?: string;
}

export interface CreateCameraAccessTokenInput {
  label?: string;
  expires_in_minutes: number;
  granted_to_email?: string;
  granted_to_name?: string;
}

/** Payload public du viewer tiers (`GET /view/cam`, en-tête `X-Token`). */
export interface PublicCameraView {
  camera: { id: number; name: string; location: string | null };
  stream_url: string;
  stream_token: string;
  expires_at: string | null;
  permissions: Record<string, unknown> | null;
  label: string | null;
}

export type PublicCameraViewResult =
  | { ok: true; view: PublicCameraView }
  | { ok: false; reason: 'invalid_token' | 'camera_unavailable' | 'network' };

/** Durées proposées par l'API (`cameras.access_token_durations`). */
export const CAMERA_SHARE_DURATIONS = [60, 1440, 10080, 43200] as const;

/**
 * Construit le lien de partage. Le jeton va **dans le fragment** : sans lui, la
 * page `/view/cam` ne peut rien afficher ; avec lui en query string, le jeton
 * fuit dans les journaux.
 */
export function buildCameraViewerUrl(origin: string, token: string): string {
  const base = origin.replace(/\/+$/, '');
  return `${base}/view/cam#t=${token}`;
}

/** Lit le jeton du fragment d'URL. Refuse explicitement l'ancienne forme `?t=`. */
export function readCameraTokenFromUrl(href: string): { token: string | null; legacy: boolean } {
  const url = new URL(href, 'http://localhost');
  const fromFragment = new URLSearchParams(url.hash.replace(/^#/, '')).get('t')
    ?? (url.hash.replace(/^#/, '').startsWith('t=') ? url.hash.replace(/^#/, '').slice(2) : '');

  if (fromFragment) {
    return { token: fromFragment, legacy: false };
  }
  // Forme héritée : jeton en clair dans la chaîne de requête. On ne l'utilise
  // PAS (il est déjà journalisé) et on le dit à l'utilisateur.
  return { token: null, legacy: url.searchParams.has('t') };
}

export async function listCameraAccessTokens(cameraId: number): Promise<CameraAccessToken[]> {
  const response = await apiFetch(`/cameras/${cameraId}/access-tokens`);
  const payload = await readJson<{ data?: CameraAccessToken[] }>(response);
  return Array.isArray(payload.data) ? payload.data : [];
}

export async function createCameraAccessToken(
  cameraId: number,
  input: CreateCameraAccessTokenInput,
): Promise<CameraAccessToken> {
  const response = await apiFetch(`/cameras/${cameraId}/access-tokens`, {
    method: 'POST',
    body: JSON.stringify(input),
  });
  const payload = await readJson<{ data: CameraAccessToken }>(response);
  return payload.data;
}

export async function revokeCameraAccessToken(cameraId: number, tokenId: number): Promise<void> {
  await apiFetch(`/cameras/${cameraId}/access-tokens/${tokenId}`, { method: 'DELETE' });
}

/**
 * `GET /view/cam` — endpoint **public** (aucune session) : le jeton part en
 * en-tête `X-Token`. Appel direct à l'API (pas `apiFetch`) : la page viewer
 * n'est pas dans le portail client et n'a aucun cookie à envoyer.
 */
export async function fetchPublicCameraView(token: string): Promise<PublicCameraViewResult> {
  const response = await fetch(`${resolveApiBaseUrl()}/view/cam`, {
    headers: { Accept: 'application/json', 'X-Token': token },
  });

  if (response.status === 404) {
    const payload = (await response.json().catch(() => ({}))) as { error?: string };
    return {
      ok: false,
      reason: payload.error === 'CAMERA_NOT_FOUND' ? 'camera_unavailable' : 'invalid_token',
    };
  }
  if (!response.ok) {
    return { ok: false, reason: 'network' };
  }

  const payload = (await response.json()) as { data?: PublicCameraView };
  if (!payload.data) {
    return { ok: false, reason: 'network' };
  }

  return { ok: true, view: payload.data };
}
