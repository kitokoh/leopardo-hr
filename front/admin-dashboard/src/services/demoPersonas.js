/**
 * demoPersonas — personas de l'accès démo, filtrés par SURFACE (issue #7402).
 *
 * `GET /api/v1/demo-users` qualifie chaque persona : `admin-platform`
 * (super-admin, guard Sanctum `super_admin_tokens`) ou `web-manager` /
 * `kiosk-supervisor` / `mobile-employee` (employés d'un tenant, servis par
 * `POST /auth/login` côté espace web).
 *
 * La console d'admin ne peut ouvrir une session QUE pour un persona
 * `admin-platform` : son `LoginView` poste vers `/platform/auth/login`, qui
 * ne connaît que la table `super_admins`. Rendre les personas `web-manager`
 * créait un faux espoir — tous échouaient en `INVALID_CREDENTIALS` (#7402).
 *
 * Fonctions pures (aucun accès réseau) pour être testables directement.
 */

export const ADMIN_PLATFORM_SURFACE = 'admin-platform'

/**
 * @param {{ surface?: unknown, email?: unknown, password?: unknown }} entity
 * @param {boolean} defaultForSuperAdmin surface par défaut si le champ est absent
 */
function matchesAdminSurface(entity, defaultForSuperAdmin) {
  const surface = typeof entity?.surface === 'string' ? entity.surface.trim() : ''

  if (surface === '') {
    // Rétro-compatibilité : une API antérieure à #7402 ne publie pas
    // `surface`. Le bloc `super_admin` est celui de la plateforme ; les
    // employés d'entreprise ne sont jamais connectables depuis l'admin.
    return defaultForSuperAdmin
  }

  return surface === ADMIN_PLATFORM_SURFACE
}

function hasCredentials(entity) {
  return typeof entity?.email === 'string' && typeof entity?.password === 'string'
}

/**
 * Construit la liste des personas connectables depuis l'admin dashboard.
 *
 * @param {unknown} responseBody corps JSON de `/demo-users`
 * @param {{ t?: (key: string, fallback?: string) => string }} [options]
 */
export function buildDemoPersonas(responseBody, options = {}) {
  const t = typeof options.t === 'function' ? options.t : (key, fallback = '') => fallback || key
  const root = responseBody?.data ?? responseBody ?? {}
  const personas = []

  const superAdmin = root.super_admin
  if (hasCredentials(superAdmin) && matchesAdminSurface(superAdmin, true)) {
    personas.push({
      label: superAdmin.label || t('auth.demo_super_admin_label', 'Super Administrateur'),
      email: superAdmin.email,
      password: superAdmin.password,
      badge: t('auth.demo_badge_platform', 'Plateforme'),
    })
  }

  const companies = Array.isArray(root.companies) ? root.companies : []
  for (const company of companies) {
    const users = Array.isArray(company?.users) ? company.users : []
    for (const user of users) {
      if (!hasCredentials(user) || !matchesAdminSurface(user, false)) continue
      const companyName = company?.name || 'Tenant'
      personas.push({
        label: user.name || user.email,
        email: user.email,
        password: user.password,
        badge: user.manager_role ? `${companyName} · ${user.manager_role}` : companyName,
      })
    }
  }

  return personas
}

/**
 * Nombre total de personas publiés (toutes surfaces confondues) — permet à
 * l'UI de distinguer « démo désactivée » de « personas hors surface admin »
 * et de l'expliquer au lieu de faire disparaître le panneau sans raison.
 */
export function countDemoPersonas(responseBody) {
  const root = responseBody?.data ?? responseBody ?? {}
  let total = hasCredentials(root.super_admin) ? 1 : 0
  const companies = Array.isArray(root.companies) ? root.companies : []
  for (const company of companies) {
    const users = Array.isArray(company?.users) ? company.users : []
    total += users.filter(hasCredentials).length
  }
  return total
}
