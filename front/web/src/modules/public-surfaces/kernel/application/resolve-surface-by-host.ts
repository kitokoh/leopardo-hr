/**
 * Cas d'usage : quelle vitrine verticale sert ce host ?
 *
 * ## Le défaut que ce fichier ferme
 *
 * L'architecture déclarée du produit est « une vitrine par verticale, chacune
 * avec son propre domaine » (`docs/ops/DOMAINS.md`, `identity/*-identity.ts`).
 * Les pièces existaient toutes — `canonicalDomain` par identité,
 * `resolveSurfaceDomain()` pour la surcharge d'environnement, un endpoint API
 * `GET /public/brands/by-host` — mais **aucune ne répondait à la question
 * « ce host, c'est quelle verticale ? »**. Résultat mesuré : les huit vitrines
 * étaient servies par une seule application, par chemin, sur un seul domaine.
 * Le modèle de domaine décrivait huit vitrines ; le routage en servait une.
 *
 * Ce fichier est la fonction manquante. Elle est PURE : elle ne lit ni
 * `headers()`, ni `process.env` directement (l'environnement est un paramètre),
 * donc elle se teste sans monter de serveur — même doctrine que
 * `resolveSurfaceDomain`.
 *
 * ## Pourquoi la correspondance est exacte et non par suffixe
 *
 * Un `endsWith` naïf ferait correspondre un sous-domaine attaquant
 * (`evil-` + le domaine canonique) à la vitrine légitime : il obtiendrait son
 * identité et son canonical.
 */

import type { SurfaceIdentity, SurfaceVertical } from '../domain/surface-identity';
import type { SurfaceRegistry } from '../domain/surface-registry';
import { resolveSurfaceDomain } from './resolve-surface-domain';

type EnvLike = Readonly<Record<string, string | undefined>>;

/**
 * Normalise un host de requête en hôte nu comparable.
 *
 * - retire le port (`restaurants.leopardo.app:3000` → `restaurants.leopardo.app`) ;
 * - minuscules (les hôtes sont insensibles à la casse) ;
 * - retire un `www.` de tête (même site, pas une verticale différente) ;
 * - retire un point final (`example.com.` est un FQDN valide).
 *
 * Retourne `''` pour une entrée vide ou manifestement invalide : l'appelant
 * traite alors « aucun host » comme « aucune vitrine », jamais comme un défaut.
 */
export function normalizeHost(host: string | null | undefined): string {
  if (!host) {
    return '';
  }

  let value = host.trim().toLowerCase();

  // IPv6 littéral (`[::1]:3000`) : pas de port à retirer après le `]`.
  if (value.startsWith('[')) {
    const close = value.indexOf(']');
    return close === -1 ? '' : value.slice(0, close + 1);
  }

  const colon = value.indexOf(':');
  if (colon !== -1) {
    value = value.slice(0, colon);
  }

  if (value.endsWith('.')) {
    value = value.slice(0, -1);
  }

  if (value.startsWith('www.')) {
    value = value.slice(4);
  }

  return value;
}

/**
 * Identité de la vitrine servie par ce host, ou `null`.
 *
 * `null` est un résultat NORMAL : le host peut être celui de l'application
 * principale (dashboard, vitrine commerciale, admin), qui n'est la vitrine
 * d'aucune verticale. L'appelant ne doit jamais retomber sur une identité par
 * défaut — c'est la règle fail-closed du registre.
 */
export function resolveVerticalByHost(
  host: string | null | undefined,
  registry: SurfaceRegistry,
  env: EnvLike = process.env,
): SurfaceIdentity | null {
  const normalized = normalizeHost(host);

  if (normalized === '') {
    return null;
  }

  for (const identity of registry.all()) {
    // `resolveSurfaceDomain` applique la surcharge d'environnement nommée par
    // verticale : un host de préproduction (`restaurants.staging.exemple.tld`)
    // doit résoudre la même vitrine que le domaine de production.
    if (normalizeHost(resolveSurfaceDomain(identity, env)) === normalized) {
      return identity;
    }
  }

  return null;
}

/** Vrai si ce host est le domaine canonique d'une vitrine déclarée. */
export function isSurfaceHost(
  host: string | null | undefined,
  registry: SurfaceRegistry,
  env: EnvLike = process.env,
): boolean {
  return resolveVerticalByHost(host, registry, env) !== null;
}

/**
 * Chemin à servir pour une requête arrivée sur le domaine d'une vitrine.
 *
 * Sur `restaurants.leopardo.app/chez-marcel`, le visiteur ne doit pas voir
 * `/restaurants/chez-marcel` : le domaine EST la verticale, donc le préfixe
 * est redondant. On le retire quand il est déjà là (un lien interne qui pointe
 * `/restaurants/x` depuis le domaine de la vitrine reste valide), et on
 * l'ajoute sinon.
 *
 * Retourne `null` quand aucune réécriture n'est nécessaire — l'appelant sert
 * alors la requête telle quelle, sans réponse intermédiaire.
 */
export function surfacePathForHost(
  identity: SurfaceIdentity,
  pathname: string,
): string | null {
  const base = identity.basePath;

  if (pathname === base || pathname.startsWith(`${base}/`)) {
    return null;
  }

  if (pathname === '/') {
    return base;
  }

  return `${base}${pathname.startsWith('/') ? pathname : `/${pathname}`}`;
}

/** Verticales servies par un host donné — utilitaire de diagnostic. */
export function verticalsForHosts(
  hosts: readonly string[],
  registry: SurfaceRegistry,
  env: EnvLike = process.env,
): SurfaceVertical[] {
  const found: SurfaceVertical[] = [];

  for (const host of hosts) {
    const identity = resolveVerticalByHost(host, registry, env);
    if (identity && !found.includes(identity.vertical)) {
      found.push(identity.vertical);
    }
  }

  return found;
}
