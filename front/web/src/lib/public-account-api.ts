/**
 * Leopardo Public — client des routes de compte du canal public.
 *
 * ## Pourquoi ce fichier existe
 *
 * La navigation basse (`PublicBottomNav`) renvoie vers `/activites` et
 * `/profil`, mais ces pages n'existaient pas : deux liens morts. Les routes API
 * qu'elles consomment, elles, étaient déjà livrées (`/user/activities`,
 * `/user/favorites`, `/user/me`, `/user/account`, `/user/public-profile`,
 * `/user/data-export`). Ce module est la seule couche qui les appelle.
 *
 * ## Le contrat de résultat est le même que `public-conversation-api`
 *
 * `unauthenticated` n'est PAS une erreur : c'est un état d'écran à part
 * entière (l'onglet explique pourquoi il est vide et propose de se connecter).
 * Confondre les deux ferait afficher « Impossible de charger » à un visiteur
 * qui n'a simplement pas de compte — un mensonge sur l'état du système.
 *
 * ## Aucun état de succès déduit localement
 *
 * Une écriture (langue, consentement, retrait d'un favori) n'est affichée comme
 * réussie qu'après un `2xx` du serveur. Un `optimistic update` masquerait un
 * refus serveur — exactement ce que la règle de vérité du couloir interdit.
 */

export type AccountResult<T> =
  | { status: 'ok'; data: T }
  | { status: 'unauthenticated' }
  | { status: 'invalid'; fields?: Record<string, string[]> }
  | { status: 'error' };

interface Envelope<T> {
  data?: T;
  error?: string;
  errors?: Record<string, string[]>;
}

/** Référence d'activité — identifiants et statuts seulement, jamais de contenu métier. */
export interface PublicActivityReference {
  vertical: string;
  activity_type: string;
  reference: string;
  status: string;
  occurred_at: string | null;
}

export interface PublicFavoriteEntry {
  target_type: string;
  target_slug: string;
  /** Nom commercial rendu par l'API ; `null` si le profil n'est plus résoluble. */
  display_name: string | null;
  /** `false` = le professionnel n'est plus publié : la fiche est indisponible. */
  available: boolean;
  created_at: string | null;
}

export interface PublicSearchHistoryEntry {
  query: string;
  city: string | null;
  result_count: number | null;
  searched_at: string | null;
}

export interface PublicAccountIdentity {
  id: number;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  phone_e164: string | null;
  phone_verified_at: string | null;
  preferred_language: string | null;
  created_at: string | null;
}

export interface PublicProfileSettings {
  preferred_locale: string;
  approx_city: string | null;
  approx_country: string | null;
  notifications_opt_in: boolean;
  notifications_consent_at: string | null;
  privacy_policy_version: string | null;
  last_active_at: string | null;
  supported_locales: string[];
}

/**
 * Traduit une réponse HTTP en résultat explicite.
 *
 * `401` → `unauthenticated` (état d'écran, pas une erreur) ; `422` → `invalid`
 * avec les champs ; le reste → `error`.
 */
async function toResult<T>(response: Response): Promise<AccountResult<T>> {
  if (response.status === 401) {
    return { status: 'unauthenticated' };
  }

  if (response.status === 422) {
    const body = (await response.json().catch(() => ({}))) as Envelope<T>;
    return { status: 'invalid', fields: body.errors };
  }

  if (!response.ok) {
    return { status: 'error' };
  }

  const body = (await response.json().catch(() => ({}))) as Envelope<T>;

  if (body.data === undefined) {
    return { status: 'error' };
  }

  return { status: 'ok', data: body.data };
}

async function request<T>(
  path: string,
  init: RequestInit = {},
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<T>> {
  try {
    const response = await fetcher(path, {
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init.body ? { 'Content-Type': 'application/json' } : {}),
        ...(init.headers ?? {}),
      },
      credentials: 'include',
    });

    return await toResult<T>(response);
  } catch {
    // Réseau coupé : état d'erreur explicite, jamais un écran vide.
    return { status: 'error' };
  }
}

// ────────────────────────────────────────────────────────────────────────────
// Activités
// ────────────────────────────────────────────────────────────────────────────

/** GET /api/v1/user/activities — références d'activité du client. */
export function fetchMyActivities(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicActivityReference[]>> {
  return request<PublicActivityReference[]>('/api/v1/user/activities', {}, fetcher);
}

/** GET /api/v1/user/favorites — favoris du client. */
export function fetchMyFavorites(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicFavoriteEntry[]>> {
  return request<PublicFavoriteEntry[]>('/api/v1/user/favorites', {}, fetcher);
}

/**
 * DELETE /api/v1/user/favorites/{type}/{slug} — retirer un favori.
 *
 * L'API répond **204 sans corps** : il n'y a pas de `data` à lire. On ne passe
 * donc pas par `toResult` (qui exigerait un `data` et conclurait à une erreur
 * sur un succès). Un 204 est un succès explicite.
 */
export async function removeFavorite(
  targetType: string,
  targetSlug: string,
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<{ removed: true }>> {
  try {
    const response = await fetcher(
      `/api/v1/user/favorites/${encodeURIComponent(targetType)}/${encodeURIComponent(targetSlug)}`,
      { method: 'DELETE', headers: { Accept: 'application/json' }, credentials: 'include' },
    );

    if (response.status === 401) {
      return { status: 'unauthenticated' };
    }

    if (response.status === 204 || response.ok) {
      return { status: 'ok', data: { removed: true } };
    }

    return { status: 'error' };
  } catch {
    return { status: 'error' };
  }
}

/** GET /api/v1/user/search-history — recherches récentes. */
export function fetchMySearchHistory(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicSearchHistoryEntry[]>> {
  return request<PublicSearchHistoryEntry[]>('/api/v1/user/search-history', {}, fetcher);
}

/** DELETE /api/v1/user/search-history — effacer l'historique. */
export function clearSearchHistory(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<{ deleted: number }>> {
  return request<{ deleted: number }>(
    '/api/v1/user/search-history',
    { method: 'DELETE' },
    fetcher,
  );
}

// ────────────────────────────────────────────────────────────────────────────
// Profil
// ────────────────────────────────────────────────────────────────────────────

/** GET /api/v1/user/me — identité du client connecté. */
export function fetchMyIdentity(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicAccountIdentity>> {
  return request<PublicAccountIdentity>('/api/v1/user/me', {}, fetcher);
}

/** GET /api/v1/user/public-profile — réglages du profil public. */
export function fetchMyProfileSettings(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicProfileSettings>> {
  return request<PublicProfileSettings>('/api/v1/user/public-profile', {}, fetcher);
}

/**
 * PATCH /api/v1/user/public-profile — enregistrer la langue ou le consentement.
 *
 * La langue est écrite sur le COMPTE, pas dans le stockage local : c'est ce qui
 * la fait suivre le client d'un appareil à l'autre.
 */
export function updateMyProfileSettings(
  patch: Partial<
    Pick<
      PublicProfileSettings,
      'preferred_locale' | 'approx_city' | 'approx_country' | 'notifications_opt_in'
    >
  >,
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<PublicProfileSettings>> {
  return request<PublicProfileSettings>(
    '/api/v1/user/public-profile',
    { method: 'PATCH', body: JSON.stringify(patch) },
    fetcher,
  );
}

/** GET /api/v1/user/data-export — export RGPD (JSON structuré). */
export function fetchMyDataExport(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<Record<string, unknown>>> {
  return request<Record<string, unknown>>('/api/v1/user/data-export', {}, fetcher);
}

/** POST /api/v1/user/logout-all — déconnecter tous les appareils. */
export function logoutAllDevices(
  fetcher: typeof fetch = fetch,
): Promise<AccountResult<{ revoked: number }>> {
  return request<{ revoked: number }>('/api/v1/user/logout-all', { method: 'POST' }, fetcher);
}
