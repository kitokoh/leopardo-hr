/**
 * BC-40 LIBRARY — client API de l'administration des ressources (#9241).
 *
 * Enveloppe typée des endpoints `/api/v1/library/resources/*` (privés, auth
 * Sanctum + middleware tenant + RBAC `LibraryResourcePolicy`). Les appels
 * passent par `apiFetch` (proxy same-origin → cookie httpOnly), jamais par des
 * URL absolues.
 *
 * Contrat backend : `app/Modules/Library/Interfaces/Api/V1/Controllers/
 * LibraryResourceController.php`. Le RBAC est vérifié CÔTÉ SERVEUR : masquer un
 * bouton ne suffit pas, et un appel non autorisé répond 403.
 */

import { apiFetch, ApiError } from '@/lib/api-client';

export type LibraryResourceStatus = 'draft' | 'pending' | 'published' | 'archived';

export type LibraryDocumentType = 'book' | 'guide' | 'report' | 'article' | 'template' | 'other';

export type LibraryAccessModel = 'free' | 'paid' | 'subscription' | 'internal';

export interface LibraryResource {
  id: number;
  slug: string;
  title: string;
  description: string | null;
  document_type: LibraryDocumentType;
  language: string;
  access_model: LibraryAccessModel;
  status: LibraryResourceStatus;
  price_minor: number | null;
  currency: string | null;
  license_id: number | null;
  rights_holder: string | null;
  allow_download: boolean;
  featured: boolean;
  is_new: boolean;
  published_at: string | null;
  file_format: string | null;
  file_size: number | null;
  has_file: boolean;
}

/** Charge utile de création/modification (champs éditables). */
export interface LibraryResourceInput {
  title: string;
  description?: string | null;
  document_type?: LibraryDocumentType;
  language?: string;
  access_model?: LibraryAccessModel;
  price_minor?: number | null;
  currency?: string | null;
  rights_holder?: string | null;
  allow_download?: boolean;
  featured?: boolean;
}

/** GET /library/resources — liste des ressources du tenant (brouillons inclus). */
export async function listLibraryResources(status?: LibraryResourceStatus): Promise<LibraryResource[]> {
  const query = status ? `?status=${encodeURIComponent(status)}` : '';
  const response = await apiFetch(`/library/resources${query}`);
  const body = (await response.json()) as { data?: LibraryResource[] };
  return Array.isArray(body.data) ? body.data : [];
}

/** POST /library/resources — crée une ressource (statut brouillon). */
export async function createLibraryResource(input: LibraryResourceInput): Promise<LibraryResource> {
  const response = await apiFetch('/library/resources', {
    method: 'POST',
    body: JSON.stringify(input),
  });
  const body = (await response.json()) as { data: LibraryResource };
  return body.data;
}

/** PUT /library/resources/{id} — modifie les métadonnées. */
export async function updateLibraryResource(
  id: number,
  input: Partial<LibraryResourceInput>,
): Promise<LibraryResource> {
  const response = await apiFetch(`/library/resources/${id}`, {
    method: 'PUT',
    body: JSON.stringify(input),
  });
  const body = (await response.json()) as { data: LibraryResource };
  return body.data;
}

/** DELETE /library/resources/{id} — supprime une ressource. */
export async function deleteLibraryResource(id: number): Promise<void> {
  await apiFetch(`/library/resources/${id}`, { method: 'DELETE' });
}

/**
 * POST /library/resources/{id}/file — téléverse le fichier (multipart).
 *
 * Le `Content-Type` n'est JAMAIS posé à la main : `apiFetch` le laisse à
 * `fetch` pour que la frontière multipart soit correcte.
 */
export async function uploadLibraryResourceFile(id: number, file: File): Promise<LibraryResource> {
  const form = new FormData();
  form.append('file', file);

  const response = await apiFetch(`/library/resources/${id}/file`, {
    method: 'POST',
    body: form,
  });
  const body = (await response.json()) as { data: LibraryResource };
  return body.data;
}

/**
 * Transition de statut — publier / dépublier / archiver.
 *
 * Le serveur REFUSE une transition invalide (409) : on ne devine pas la règle
 * côté client, on expose l'action et on affiche le refus tel quel.
 */
export async function transitionLibraryResource(
  id: number,
  action: 'publish' | 'unpublish' | 'archive',
): Promise<LibraryResource> {
  const response = await apiFetch(`/library/resources/${id}/${action}`, { method: 'POST' });
  const body = (await response.json()) as { data: LibraryResource };
  return body.data;
}

/**
 * Extrait un message d'erreur lisible d'une réponse API.
 *
 * 422 : première erreur de champ. 501 : stockage non configuré (message
 * serveur explicite). 409 : transition invalide. Sinon : `message` serveur.
 */
export function libraryErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    const payload = (error as ApiError & { payload?: unknown }).payload;
    const message = extractMessage(payload);
    if (message) return message;
    if (error.status === 403) return fallback;
    return error.message || fallback;
  }

  if (error instanceof Error && error.message) return error.message;
  return fallback;
}

function extractMessage(payload: unknown): string | null {
  if (!payload || typeof payload !== 'object') return null;
  const record = payload as Record<string, unknown>;

  if (record.errors && typeof record.errors === 'object') {
    const first = Object.values(record.errors as Record<string, unknown>).flat()[0];
    if (typeof first === 'string' && first !== '') return first;
  }

  if (typeof record.message === 'string' && record.message !== '') return record.message;
  return null;
}
