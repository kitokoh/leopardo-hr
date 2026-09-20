/**
 * tenant-branding.ts — cache local de l'image de marque du tenant (#7860).
 *
 * Le shell (#7713) chargeait `/company/branding` une seule fois au montage :
 * couleurs et logo n'apparaissaient qu'après le round-trip API, et la page
 * `/settings/branding` ne notifiait personne après une sauvegarde — il
 * fallait recharger l'espace pour voir le nouveau thème.
 *
 * Ce module porte :
 *  - un cache `localStorage` (clé `tenant_branding`) hydraté instantanément
 *    par le layout à chaque connexion, rafraîchi ensuite par l'API ;
 *  - un canal de synchronisation intra-onglet : `storeTenantBranding()` et
 *    `clearTenantBrandingCache()` émettent le `CustomEvent`
 *    `tenant-branding-updated` sur `window` (detail = branding ou null),
 *    que le layout écoute pour se re-thémer immédiatement.
 *
 * Toutes les fonctions sont sûres côté serveur (no-op sans `window`) et
 * tolèrent un localStorage indisponible ou un JSON corrompu (repli null).
 */

export type TenantBranding = {
  display_name: string | null;
  logo_url: string | null;
  primary_color: string;
  accent_color: string;
  brand_mode: string;
};

/** Clé localStorage du cache de branding. */
export const TENANT_BRANDING_STORAGE_KEY = 'tenant_branding';

/** Nom du CustomEvent émis sur `window` à chaque mise à jour du cache. */
export const TENANT_BRANDING_EVENT = 'tenant-branding-updated';

function isTenantBranding(value: unknown): value is TenantBranding {
  if (!value || typeof value !== 'object') {
    return false;
  }
  const candidate = value as Record<string, unknown>;
  return (
    typeof candidate.primary_color === 'string' &&
    typeof candidate.accent_color === 'string' &&
    typeof candidate.brand_mode === 'string' &&
    (candidate.logo_url === null || typeof candidate.logo_url === 'string') &&
    (candidate.display_name === null || typeof candidate.display_name === 'string')
  );
}

function emitTenantBrandingUpdated(branding: TenantBranding | null): void {
  window.dispatchEvent(
    new CustomEvent<TenantBranding | null>(TENANT_BRANDING_EVENT, { detail: branding }),
  );
}

/** Lit le branding en cache ; null si absent, corrompu ou hors navigateur. */
export function readCachedTenantBranding(): TenantBranding | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    const raw = window.localStorage.getItem(TENANT_BRANDING_STORAGE_KEY);
    if (!raw) {
      return null;
    }
    const parsed: unknown = JSON.parse(raw);
    return isTenantBranding(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

/**
 * Écrit le branding en cache puis notifie l'onglet (`tenant-branding-updated`).
 * Un branding malformé est ignoré (le cache n'est jamais pollué).
 */
export function storeTenantBranding(branding: TenantBranding): void {
  if (typeof window === 'undefined' || !isTenantBranding(branding)) {
    return;
  }
  // Ne persiste que les champs du contrat : un payload plus riche (logo_path,
  // logo_disk…) est réduit avant écriture.
  const persisted: TenantBranding = {
    display_name: branding.display_name,
    logo_url: branding.logo_url,
    primary_color: branding.primary_color,
    accent_color: branding.accent_color,
    brand_mode: branding.brand_mode,
  };
  try {
    window.localStorage.setItem(TENANT_BRANDING_STORAGE_KEY, JSON.stringify(persisted));
  } catch {
    // localStorage plein / indisponible : l'événement suffit pour l'onglet courant.
  }
  emitTenantBrandingUpdated(persisted);
}

/** Vide le cache (déconnexion) et notifie l'onglet (detail = null). */
export function clearTenantBrandingCache(): void {
  if (typeof window === 'undefined') {
    return;
  }
  try {
    window.localStorage.removeItem(TENANT_BRANDING_STORAGE_KEY);
  } catch {
    // Ignoré : au pire, le prochain login réécrira la clé.
  }
  emitTenantBrandingUpdated(null);
}
