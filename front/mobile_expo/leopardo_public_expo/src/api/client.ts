import axios, { AxiosError, AxiosInstance } from 'axios';

/**
 * URL de base de l'API Leopardo.
 *
 * Definie par EXPO_PUBLIC_API_URL au build. Defaut : emulateur Android.
 */
export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_URL ?? 'http://10.0.2.2:8000/api/v1';

/**
 * Client des routes PUBLIQUES.
 *
 * ## Aucun jeton, jamais
 *
 * Toutes les routes appelees par cette app sont publiques
 * (`throttle:shop-public`) : elles ne lisent ni `Authorization` ni contexte
 * tenant. On n'installe donc AUCUN intercepteur d'authentification — envoyer
 * un jeton inutile elargirait la surface d'attaque sans rien apporter.
 *
 * ## Pas de rejeu automatique sur une mutation
 *
 * Une commande, une reservation ou une course ne sont JAMAIS rejouees par le
 * client HTTP : le rejeu est porte par `idempotency_key`, fournie par
 * l'appelant. Un retry reseau reel renvoie donc la meme ressource (201 puis
 * 200 cote serveur), jamais un doublon.
 */
export const publicApi: AxiosInstance = axios.create({
  baseURL: API_BASE_URL,
  timeout: 20000,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

publicApi.interceptors.request.use((config) => {
  config.headers['Accept-Language'] = 'fr';
  return config;
});

/**
 * Nature d'un echec, deja classifiee pour que l'ecran choisisse le bon etat.
 *
 * Les etats ne sont pas interchangeables : « hors ligne » n'est pas « erreur,
 * reessayez », et « ce commerce n'existe plus » n'est PAS une panne.
 */
export type PublicFailureKind =
  | 'network'
  | 'timeout'
  | 'server'
  | 'notFound'
  | 'invalidRequest';

export class PublicApiFailure extends Error {
  constructor(
    readonly kind: PublicFailureKind,
    message: string,
    readonly statusCode?: number,
  ) {
    super(message);
    this.name = 'PublicApiFailure';
  }

  /** Vrai quand on peut proposer « Reessayer » sans mentir. */
  get isRetryable(): boolean {
    return this.kind === 'network' || this.kind === 'timeout' || this.kind === 'server';
  }
}

/** Traduit une erreur axios en echec classifie. */
export function classifyFailure(error: unknown): PublicApiFailure {
  if (error instanceof PublicApiFailure) return error;

  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    const data = error.response?.data as
      | { message?: string; error?: string; errors?: Record<string, string[]> }
      | undefined;

    if (error.code === 'ECONNABORTED') {
      return new PublicApiFailure(
        'timeout',
        'Le serveur met trop de temps a repondre. Verifiez votre connexion.',
      );
    }

    if (!error.response) {
      return new PublicApiFailure(
        'network',
        'Pas de connexion. Verifiez votre reseau et reessayez.',
      );
    }

    if (status === 404) {
      return new PublicApiFailure(
        'notFound',
        'Cette fiche n\u2019est plus disponible.',
        404,
      );
    }

    if (status !== undefined && status >= 500) {
      return new PublicApiFailure(
        'server',
        'Le service est momentanement indisponible.',
        status,
      );
    }

    // 4xx metier : le premier message de validation Laravel est le plus utile.
    if (data?.errors) {
      const first = Object.values(data.errors)[0];
      if (Array.isArray(first) && first.length > 0) {
        return new PublicApiFailure('invalidRequest', first[0], status);
      }
    }

    return new PublicApiFailure(
      'invalidRequest',
      data?.message ?? data?.error ?? `Requete refusee (${status ?? 'inconnu'}).`,
      status,
    );
  }

  if (error instanceof Error) return new PublicApiFailure('server', error.message);
  return new PublicApiFailure('server', 'Une erreur inattendue est survenue.');
}

/** Corps `data` d'une reponse, ou objet vide si absent. */
export function dataOf(payload: unknown): Record<string, unknown> {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    const inner = (payload as { data: unknown }).data;
    if (inner && typeof inner === 'object' && !Array.isArray(inner)) {
      return inner as Record<string, unknown>;
    }
  }
  if (payload && typeof payload === 'object' && !Array.isArray(payload)) {
    return payload as Record<string, unknown>;
  }
  return {};
}

/** Liste `data` d'une reponse, ou tableau vide si absente. */
export function listOf(payload: unknown): unknown[] {
  if (payload && typeof payload === 'object' && 'data' in payload) {
    const inner = (payload as { data: unknown }).data;
    if (Array.isArray(inner)) return inner;
  }
  if (Array.isArray(payload)) return payload;
  return [];
}

/** `meta` d'une reponse paginee, ou objet vide. */
export function metaOf(payload: unknown): Record<string, unknown> {
  if (payload && typeof payload === 'object' && 'meta' in payload) {
    const meta = (payload as { meta: unknown }).meta;
    if (meta && typeof meta === 'object') return meta as Record<string, unknown>;
  }
  return {};
}

/**
 * Cle d'idempotence.
 *
 * Generee cote client et CONSERVEE jusqu'au succes : un double tap ou un
 * retry reseau renvoie la meme ressource, jamais un doublon.
 */
export function newIdempotencyKey(): string {
  const random = () => Math.random().toString(36).slice(2, 10);
  return `${Date.now().toString(36)}-${random()}-${random()}`;
}

/** UUID v4 — exige par le contrat VTC (`Idempotency-Key`). */
export function newUuidV4(): string {
  const hex = '0123456789abcdef';
  let out = '';
  for (let i = 0; i < 36; i += 1) {
    if (i === 8 || i === 13 || i === 18 || i === 23) out += '-';
    else if (i === 14) out += '4';
    else if (i === 19) out += hex[(Math.random() * 4) | 8];
    else out += hex[(Math.random() * 16) | 0];
  }
  return out;
}
