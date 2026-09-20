/**
 * Session d'authentification du portail web (#8000 — extrait de i18n.ts).
 *
 * Ce module regroupe TOUT ce qui touche à la session côté client : clés de
 * stockage, lecture/écriture/purge de la session et type de l'utilisateur
 * stocké. Il vivait auparavant au milieu de `lib/i18n.ts` (3 662 lignes de
 * traductions) — invisible pour un audit auth qui greppe `auth`/`session`.
 *
 * Note architecture (#8000) : dépend de `./locale-core` (primitives
 * locale) — le graphe locale-core ← auth-session ← i18n est acyclique
 * (le cycle initial i18n ↔ auth-session cassait les suites Jest sous
 * transform CommonJS : TDZ « Cannot access before initialization »).
 *
 * Audit #1699 : le TOKEN n'est plus stocké côté JS (cookie httpOnly
 * `leopardo_token` géré par les route handlers) — seul le PROFIL public et
 * la locale préférée persistent en localStorage.
 */

import { normalizeLocale, storePreferredLocale, PREFERRED_LOCALE_KEY } from './locale-core';

export type StoredAuthUser = {
  id?: number | string;
  first_name?: string | null;
  last_name?: string | null;
  name?: string | null;
  email?: string | null;
  // #7861 — téléphone professionnel éditable depuis « Mon compte ».
  phone?: string | null;
  language?: string | null;
  is_rtl?: boolean;
  role?: string | null;
  manager_role?: string | null;
  // #7761/#7762 — grants de modules délégués (registre fermé ModuleKey) renvoyés
  // par /auth/me pour SA propre fiche : ['marketing', 'accounting', ...].
  module_grants?: string[] | null;
  capabilities?: Record<string, unknown> | null;
  // Features tenant (FeatureFlag::for) renvoyées au niveau racine par
  // /auth/me (EmployeeResource) : {rh, finance, cameras, muhasebe, leo_ai}.
  features?: Record<string, unknown> | null;
  company?: {
    id?: number | string | null;
    name?: string | null;
    language?: string | null;
    timezone?: string | null;
    currency?: string | null;
    features?: Record<string, unknown> | null;
    metadata?: Record<string, unknown> | null;
    // #7235 — profil d'activité déclaré à l'inscription : `company`
    // (entreprise) ou `solo` (indépendant, sans outils d'équipe).
    type?: string | null;
    // #7235 — secteur / métier vertical (ex. « restaurant »).
    sector?: string | null;
    // #7235 — sélection EXPLICITE des outils horizontaux faite à
    // l'inscription ({ employees: true, attendance: false, … }). `null` ou
    // absent = aucune sélection déclarée → comportement historique.
    modules?: Record<string, unknown> | null;
    // #7235 — essai : `subscription_end` alimente le compteur de jours
    // restants dans l'application (les CTA « essai 14 jours » de la vitrine
    // disparaissent, l'inscription est directe).
    status?: string | null;
    subscription_end?: string | null;
  } | null;
  plan?: {
    name?: string | null;
    features?: Record<string, unknown> | null;
  } | null;
};

export const AUTH_TOKEN_KEY = 'auth_token';
export const AUTH_USER_KEY = 'auth_user';

export function getStoredUser(): StoredAuthUser | null {
  if (typeof window === 'undefined') return null;

  const raw = window.localStorage.getItem(AUTH_USER_KEY);
  if (!raw) return null;

  try {
    return JSON.parse(raw) as StoredAuthUser;
  } catch {
    clearAuthSession();
    return null;
  }
}

// Audit #1699 : le token n'est plus stocké côté JS (cookie httpOnly
// `leopardo_token` géré par les route handlers). Le paramètre token est
// conservé pour la compatibilité d'appel mais jamais écrit au repos.
export function storeAuthSession(_token: string | null | undefined, user: StoredAuthUser): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.setItem(AUTH_USER_KEY, JSON.stringify(user));
  storePreferredLocale(normalizeLocale(user.language));
}

export function clearAuthSession(): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
  window.localStorage.removeItem(PREFERRED_LOCALE_KEY);
}

export function getDisplayName(user?: StoredAuthUser | null): string {
  if (!user) return 'Leopardo';

  const fullName = `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim();
  return fullName || user.name || user.email || 'Leopardo';
}
