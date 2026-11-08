import type { LibraryResource } from '../types';

/**
 * Politique d'accès de la bibliothèque — logique PURE.
 *
 * ⚠️ Ce module calcule ce qu'un visiteur peut FAIRE côté interface. Ce n'est
 * PAS une frontière de sécurité : la vérification réelle des droits (tenant,
 * achat, abonnement) est faite CÔTÉ SERVEUR par l'API. Ici, on décide
 * uniquement de l'affichage (bouton lecture, bouton téléchargement, CTA achat).
 */

/** Contexte d'accès d'un visiteur. */
export interface AccessContext {
  /** Visiteur authentifié ? */
  authenticated: boolean;
  /** Identifiant de tenant du visiteur (si connecté). */
  tenantId?: string;
  /** La ressource a-t-elle été achetée par ce visiteur ? */
  purchased?: boolean;
  /** Le visiteur a-t-il un abonnement actif ? */
  subscribed?: boolean;
}

/** Capacités calculées pour une ressource et un contexte donnés. */
export interface AccessCapabilities {
  /** Lecture en ligne autorisée. */
  canRead: boolean;
  /** Téléchargement du fichier complet autorisé. */
  canDownload: boolean;
  /** Achat requis avant lecture/téléchargement. */
  requiresPurchase: boolean;
  /** Abonnement requis. */
  requiresSubscription: boolean;
  /** Accès privé (tenant) requis. */
  requiresPrivateAccess: boolean;
  /** La ressource est-elle visible par ce visiteur ? */
  visible: boolean;
}

/**
 * Calcule les capacités d'accès d'un visiteur pour une ressource.
 *
 * Règles :
 * - `free`         : lecture + téléchargement (si la licence l'autorise) ;
 * - `read_only`    : lecture seule, téléchargement désactivé ;
 * - `purchase`     : lecture/téléchargement après achat ;
 * - `subscription` : lecture/téléchargement si abonnement actif ;
 * - `private`      : visible et lisible uniquement par le tenant propriétaire.
 */
export function getAccessCapabilities(
  resource: LibraryResource,
  context: AccessContext,
): AccessCapabilities {
  const licenseAllowsDownload = resource.license.allowDownload;

  switch (resource.accessModel) {
    case 'free':
      return {
        canRead: true,
        canDownload: licenseAllowsDownload,
        requiresPurchase: false,
        requiresSubscription: false,
        requiresPrivateAccess: false,
        visible: true,
      };

    case 'read_only':
      return {
        canRead: true,
        canDownload: false,
        requiresPurchase: false,
        requiresSubscription: false,
        requiresPrivateAccess: false,
        visible: true,
      };

    case 'purchase': {
      const owned = context.authenticated && context.purchased === true;
      return {
        canRead: owned,
        canDownload: owned && licenseAllowsDownload,
        requiresPurchase: !owned,
        requiresSubscription: false,
        requiresPrivateAccess: false,
        visible: true,
      };
    }

    case 'subscription': {
      const active = context.authenticated && context.subscribed === true;
      return {
        canRead: active,
        canDownload: active && licenseAllowsDownload,
        requiresPurchase: false,
        requiresSubscription: !active,
        requiresPrivateAccess: false,
        visible: true,
      };
    }

    case 'private': {
      const allowed =
        context.authenticated &&
        resource.tenantId !== undefined &&
        context.tenantId === resource.tenantId;
      return {
        canRead: allowed,
        canDownload: allowed && licenseAllowsDownload,
        requiresPurchase: false,
        requiresSubscription: false,
        requiresPrivateAccess: !allowed,
        visible: allowed,
      };
    }

    default:
      return {
        canRead: false,
        canDownload: false,
        requiresPurchase: false,
        requiresSubscription: false,
        requiresPrivateAccess: true,
        visible: false,
      };
  }
}

/** Vrai si la ressource est accessible sans compte (lecture publique). */
export function isPubliclyReadable(resource: LibraryResource): boolean {
  return resource.accessModel === 'free' || resource.accessModel === 'read_only';
}

/** Vrai si la ressource nécessite une authentification pour être lue. */
export function requiresAuthentication(resource: LibraryResource): boolean {
  return !isPubliclyReadable(resource);
}
