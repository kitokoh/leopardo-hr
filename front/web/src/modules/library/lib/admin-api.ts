/**
 * Client API du back-office de la bibliothèque (BC-40 LIBRARY).
 *
 * Toutes les requêtes passent par `apiFetch` : le token n'est jamais lu en JS
 * (cookie httpOnly + proxy same-origin), et les erreurs remontent en `ApiError`
 * avec leur statut HTTP — c'est ce statut que l'écran traduit en message
 * (413 trop volumineux, 422 invalide, 501 stockage non configuré).
 *
 * Aucune de ces fonctions ne décide d'un droit d'accès : le serveur reste seul
 * juge (`LibraryResourcePolicy` + scope `BelongsToCompany`). Le client se
 * contente d'afficher ce que l'API a bien voulu renvoyer.
 */

import { ApiError, apiFetch } from '@/lib/api-client';

import type {
  LibraryAccessModel,
  LibraryDocumentType,
  LibraryResourceStatus,
} from '../types';

/** Ressource telle que renvoyée par la surface tenant (snake_case). */
export interface AdminResource {
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
  license_id: string | null;
  rights_holder: string | null;
  allow_download: boolean;
  featured: boolean;
  is_new: boolean;
  published_at: string | null;
  file_format: string | null;
  file_size: number | null;
  has_file: boolean;
}

/** Métadonnées de pagination renvoyées par `GET /library/resources`. */
export interface AdminPageMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

/** Filtres acceptés par la liste (allowlist serveur). */
export interface AdminFilters {
  status?: LibraryResourceStatus | '';
  access_model?: LibraryAccessModel | '';
  document_type?: LibraryDocumentType | '';
  language?: string;
  q?: string;
  page?: number;
  per_page?: number;
}

/** Charge utile de création / mise à jour. */
export interface AdminResourceInput {
  title: string;
  slug?: string;
  description?: string | null;
  document_type: LibraryDocumentType;
  language: string;
  access_model: LibraryAccessModel;
  price_minor?: number | null;
  currency?: string | null;
  license_id?: string | null;
  rights_holder?: string | null;
  allow_download?: boolean;
  featured?: boolean;
  is_new?: boolean;
}

/** Construit la query string en ignorant les filtres vides. */
export function buildListQuery(filters: AdminFilters): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === null || value === '') continue;
    params.set(key, String(value));
  }

  const query = params.toString();

  return query === '' ? '' : `?${query}`;
}

/** Liste paginée des ressources du tenant. */
export async function fetchAdminResources(
  filters: AdminFilters = {},
): Promise<{ data: AdminResource[]; meta: AdminPageMeta }> {
  const response = await apiFetch(`/library/resources${buildListQuery(filters)}`);

  return (await response.json()) as { data: AdminResource[]; meta: AdminPageMeta };
}

/** Crée une ressource (le serveur la place en brouillon). */
export async function createAdminResource(input: AdminResourceInput): Promise<AdminResource> {
  const response = await apiFetch('/library/resources', {
    method: 'POST',
    body: JSON.stringify(input),
  });

  const payload = (await response.json()) as { data: AdminResource };

  return payload.data;
}

/** Met à jour les métadonnées d'une ressource. */
export async function updateAdminResource(
  id: number,
  input: Partial<AdminResourceInput>,
): Promise<AdminResource> {
  const response = await apiFetch(`/library/resources/${id}`, {
    method: 'PUT',
    body: JSON.stringify(input),
  });

  const payload = (await response.json()) as { data: AdminResource };

  return payload.data;
}

/** Supprime une ressource. */
export async function deleteAdminResource(id: number): Promise<void> {
  await apiFetch(`/library/resources/${id}`, { method: 'DELETE' });
}

/** Transition éditoriale : `publish` | `unpublish` | `archive`. */
export async function transitionAdminResource(
  id: number,
  action: 'publish' | 'unpublish' | 'archive',
): Promise<AdminResource> {
  const response = await apiFetch(`/library/resources/${id}/${action}`, { method: 'POST' });

  const payload = (await response.json()) as { data: AdminResource };

  return payload.data;
}

/**
 * Téléverse le fichier d'une ressource.
 *
 * Le `Content-Type` n'est jamais posé à la main : `apiFetch` le laisse à
 * `fetch` pour que la frontière multipart soit correcte.
 */
export async function uploadAdminResourceFile(id: number, file: File): Promise<AdminResource> {
  const body = new FormData();
  body.append('file', file);

  const response = await apiFetch(`/library/resources/${id}/file`, {
    method: 'POST',
    body,
  });

  const payload = (await response.json()) as { data: AdminResource };

  return payload.data;
}

/** Nature d'une erreur d'upload, pour choisir le libellé i18n. */
export type UploadFailure = 'too_large' | 'bad_type' | 'storage_unavailable' | 'generic';

/**
 * Traduit une exception d'upload en cause exploitable par l'écran.
 *
 * On se fonde sur le STATUT HTTP (contrat serveur) et non sur le texte du
 * message, qui est localisé et donc instable.
 */
export function classifyUploadFailure(error: unknown): UploadFailure {
  if (error instanceof ApiError) {
    if (error.status === 413) return 'too_large';
    if (error.status === 501) return 'storage_unavailable';
    if (error.status === 422) return 'bad_type';
  }

  return 'generic';
}
