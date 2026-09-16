import { expect, test } from '@playwright/test'

/**
 * #7557 — « Équipe plateforme » : comptes internes, rôles délégués et
 * filtrage du menu latéral sur les permissions du compte (#7553).
 *
 * Le contrat mocké est celui réellement exposé par l'API :
 *   GET   /platform/auth/me        → { id, name, email, role:'super_admin', platform_role, permissions[] }
 *   GET   /platform/team           → { data:[member], meta:{ total, active_super_admins, roles } }
 *   PATCH /platform/team/{id}/role → 200 { data:member } | 422 garde-fou
 *   POST  /platform/team/{id}/activate|deactivate
 *
 * Pattern des specs existantes (sidebar-unique-entries / platform-auth-smoke) :
 * session posée en sessionStorage + routes `/api/v1/**` mockées (aucun appel
 * réseau réel). Les libellés assertés sont français : la locale du SPA suit
 * `navigator.language`, forcé en fr-FR par playwright.config.js (et posé ici
 * explicitement pour rester déterministe).
 */

const AUTH_ME_URL = /\/api\/v1\/platform\/auth\/me(\?.*)?$/
const AUTH_LOGIN_URL = /\/api\/v1\/platform\/auth\/login$/
const TEAM_URL = /\/api\/v1\/platform\/team(\?.*)?$/
const TEAM_ROLE_URL = /\/api\/v1\/platform\/team\/(\d+)\/role(\?.*)?$/
const TEAM_STATUS_URL = /\/api\/v1\/platform\/team\/(\d+)\/(activate|deactivate)(\?.*)?$/

const json = (body, status = 200) => ({
  status,
  contentType: 'application/json',
  body: JSON.stringify(body),
})

/** Les GET du client portent un cache-buster `_t=<ts>` : tolérer la query. */
const WITH_QUERY = (path) => new RegExp(`\\/api\\/v1\\/${path}(?:\\?.*)?$`)

/** Matrice #7553 — rôle `support`. */
const SUPPORT_PERMISSIONS = [
  'companies.view',
  'users.view',
  'users.manage',
  'support.manage',
  'impersonate',
  'observability.view',
  'metrics.view',
]

const SUPER_ADMIN_USER = {
  id: 1,
  name: 'Super Administrateur',
  email: 'admin@leopardo-rh.com',
  role: 'super_admin',
  platform_role: 'super_admin',
  permissions: [
    'companies.view', 'companies.manage', 'companies.provision', 'billing.view', 'billing.manage',
    'plans.view', 'users.view', 'users.manage', 'impersonate', 'killswitch.manage',
    'observability.view', 'metrics.view', 'support.manage', 'announcements.manage', 'crm.view',
    'showcase.manage', 'edge.manage', 'team.manage',
  ],
  two_fa_enabled: false,
}

/**
 * `role` reste `'super_admin'` pour TOUS les comptes plateforme
 * (rétrocompatibilité #7553) : c'est `platform_role` + `permissions` qui
 * portent la délégation — le filtrage du menu ne doit donc PAS se fier à
 * `role`.
 */
const SUPPORT_USER = {
  id: 4,
  name: 'Amina Support',
  email: 'amina.sup@leopardo-rh.com',
  role: 'super_admin',
  platform_role: 'support',
  permissions: SUPPORT_PERMISSIONS,
  two_fa_enabled: false,
}

const TEAM_MEMBERS = [
  {
    id: 1,
    name: 'Super Administrateur',
    email: 'admin@leopardo-rh.com',
    status: 'active',
    platform_role: 'super_admin',
    platform_role_label: 'Super administrateur',
    permissions: SUPER_ADMIN_USER.permissions,
    is_self: true,
    last_login_at: '2026-09-15T09:12:00+00:00',
    created_at: '2026-01-02T08:00:00+00:00',
  },
  {
    id: 2,
    name: 'Amina Support',
    email: 'amina.sup@leopardo-rh.com',
    status: 'active',
    platform_role: 'support',
    platform_role_label: 'Support',
    permissions: SUPPORT_PERMISSIONS,
    is_self: false,
    last_login_at: null,
    created_at: '2026-02-01T08:00:00+00:00',
  },
  {
    id: 3,
    name: 'Karim Finance',
    email: 'karim.fin@leopardo-rh.com',
    status: 'deactivated',
    platform_role: 'finance',
    platform_role_label: 'Finance',
    permissions: ['companies.view', 'billing.view', 'billing.manage', 'plans.view', 'metrics.view'],
    is_self: false,
    last_login_at: '2026-08-30T10:05:00+00:00',
    created_at: '2026-03-01T08:00:00+00:00',
  },
]

const TEAM_RESPONSE = {
  data: TEAM_MEMBERS,
  meta: {
    total: 3,
    active_super_admins: 1,
    roles: {
      super_admin: SUPER_ADMIN_USER.permissions,
      support: SUPPORT_PERMISSIONS,
      finance: ['companies.view', 'billing.view', 'billing.manage', 'plans.view', 'metrics.view'],
    },
  },
}

/**
 * Pose une session plateforme et neutralise tout egress réseau réel.
 * Le catch-all est enregistré EN PREMIER : les mocks spécifiques, enregistrés
 * ensuite, ont la priorité (Playwright évalue les routes en ordre inverse).
 */
async function stubPlatform(page, { user, teamStatus = 200, teamBody = TEAM_RESPONSE } = {}) {
  await page.addInitScript(() => {
    sessionStorage.setItem('admin_token', 'e2e-platform-team-token')
    localStorage.setItem('admin_locale', 'fr')
  })

  await page.route('**/api/v1/**', (route) => route.fulfill(json({ data: {} })))
  await page.route(AUTH_LOGIN_URL, (route) =>
    route.fulfill(json({ data: user, token: 'e2e-platform-team-token', token_type: 'Bearer' })))
  await page.route(AUTH_ME_URL, (route) => route.fulfill(json({ data: user })))
  // Super-admin hors contexte tenant : la sonde travel répond 401 (menu masqué).
  await page.route(/\/api\/v1\/travel\/ping(\?.*)?$/, (route) =>
    route.fulfill(json({ error: 'NO_TENANT_CONTEXT' }, 401)))
  await page.route(TEAM_URL, (route) =>
    teamStatus === 200
      ? route.fulfill(json(teamBody))
      : route.fulfill(json({
        error: 'PLATFORM_PERMISSION_REQUIRED',
        message: 'Your role does not allow managing the platform team.',
        required_permissions: ['team.manage'],
        platform_role: 'support',
      }, teamStatus)))

  // Cockpit plateforme (DashboardView, monté sur `/`) : sans ces shapes
  // réelles, un KPI `undefined` fait échouer le rendu de StatsCard et casse
  // toute la navigation suivante (constaté : /team rendu vide).
  await page.route(WITH_QUERY('platform/companies/health'), (route) =>
    route.fulfill(json({
      data: {
        summary: { active_companies: 2, companies: 3, mrr: 12345, risk: { high: 0, medium: 0, low: 0 } },
        items: [],
      },
    })))
  await page.route(WITH_QUERY('platform/metrics/overview'), (route) =>
    route.fulfill(json({
      data: {
        revenue: { currency: 'EUR', mrr: 12345, arr: 148140 },
        companies: { total: 3, active: 2, trial: 1, suspended: 0, expired: 0 },
        subscriptions: { total: 2, active: 2 },
      },
    })))
  await page.route(WITH_QUERY('platform/company-requests'), (route) =>
    route.fulfill(json({ data: [], meta: { total: 0 } })))
  await page.route(WITH_QUERY('admin/dashboard/stats'), (route) =>
    route.fulfill(json({
      totalUsers: 4,
      totalCompanies: 3,
      activeSubscriptions: 2,
      monthlyRevenue: 12345,
      newUsersToday: 0,
      newCompaniesToday: 0,
      supportTickets: 0,
      systemHealth: 'good',
    })))
  await page.route(WITH_QUERY('admin/dashboard/activities'), (route) =>
    route.fulfill(json({ data: [] })))
  await page.route(WITH_QUERY('admin/dashboard/alerts'), (route) =>
    route.fulfill(json({ data: [] })))
  await page.route(WITH_QUERY('notifications'), (route) =>
    route.fulfill(json({ data: [], meta: { total: 0 } })))
}

test.describe('Équipe plateforme (#7557)', () => {
  test('un compte support ne voit pas l’entrée ni l’écran « Équipe plateforme »', async ({ page }) => {
    await stubPlatform(page, { user: SUPPORT_USER, teamStatus: 403 })

    await page.goto('/')
    const nav = page.getByRole('navigation')
    await expect(nav).toBeVisible({ timeout: 15_000 })

    // `team.manage` n'est porté que par `super_admin` : l'entrée est absente.
    await expect(nav.getByRole('link', { name: /Équipe plateforme/i })).toHaveCount(0)

    // Accès direct : l'API refuse (403 PLATFORM_PERMISSION_REQUIRED) et
    // l'écran affiche l'erreur au lieu d'un tableau vide trompeur.
    await page.goto('/team')
    await expect(page.getByText(/ne permet pas de gérer/i).first()).toBeVisible({ timeout: 15_000 })
  })

  test('un super administrateur consulte l’équipe, crée un compte, change un rôle et active un compte', async ({ page }) => {
    await stubPlatform(page, { user: SUPER_ADMIN_USER })

    const rolePatches = []
    await page.route(TEAM_ROLE_URL, (route) => {
      const id = Number(route.request().url().match(TEAM_ROLE_URL)[1])
      rolePatches.push({ method: route.request().method(), id, body: route.request().postDataJSON() })
      const target = TEAM_MEMBERS.find((member) => member.id === id)
      route.fulfill(json({ data: { ...target, platform_role: route.request().postDataJSON().platform_role } }))
    })

    const statusPosts = []
    await page.route(TEAM_STATUS_URL, (route) => {
      const match = route.request().url().match(TEAM_STATUS_URL)
      const id = Number(match[1])
      statusPosts.push({ method: route.request().method(), id, action: match[2] })
      const target = TEAM_MEMBERS.find((member) => member.id === id)
      route.fulfill(json({ data: { ...target, status: match[2] === 'activate' ? 'active' : 'deactivated' } }))
    })

    await page.goto('/')
    const nav = page.getByRole('navigation')
    await expect(nav).toBeVisible({ timeout: 15_000 })

    // Le super admin voit l'entrée (et les entrées protégées par une permission).
    const teamLink = nav.getByRole('link', { name: /Équipe plateforme/i })
    await expect(teamLink).toBeVisible()
    await expect(nav.getByRole('link', { name: /Abonnements/i })).toBeVisible()

    await teamLink.click()
    await expect(page).toHaveURL(/\/team$/)

    // Liste des comptes internes + badge « vous » sur son propre compte.
    await expect(page.getByText('amina.sup@leopardo-rh.com')).toBeVisible({ timeout: 15_000 })
    await expect(page.getByText('karim.fin@leopardo-rh.com')).toBeVisible()
    await expect(page.getByText('vous', { exact: true })).toBeVisible()
    // On ne peut pas modifier son propre rôle : le select est désactivé.
    await expect(page.locator('[data-testid="team-role-1"]')).toBeDisabled()

    // Modale de création (nom, e-mail, mot de passe ≥ 12, rôle).
    await page.locator('[data-testid="team-create-button"]').click()
    const modal = page.locator('[data-testid="team-create-modal"]')
    await expect(modal).toBeVisible()
    await expect(modal.locator('#team-name')).toBeVisible()
    await expect(modal.locator('#team-email')).toBeVisible()
    await expect(modal.locator('#team-password')).toHaveAttribute('minlength', '12')
    await expect(modal.locator('#team-role')).toBeVisible()
    await modal.getByRole('button', { name: /Annuler/i }).click()
    await expect(modal).toHaveCount(0)

    // Changement de rôle → PATCH /platform/team/{id}/role.
    await page.locator('[data-testid="team-role-2"]').selectOption('finance')
    await expect.poll(() => rolePatches.length).toBe(1)
    expect(rolePatches[0]).toEqual({
      method: 'PATCH',
      id: 2,
      body: { platform_role: 'finance' },
    })
    await expect(page.getByText(/Rôle mis à jour/i).first()).toBeVisible()

    // Activation d'un compte désactivé : confirmation in-app (jamais window.confirm).
    await page.locator('[data-testid="team-activate-3"]').click()
    await expect(page.locator('[data-testid="team-confirm-dialog"]')).toBeVisible()
    await page.locator('[data-testid="team-confirm-action"]').click()
    await expect.poll(() => statusPosts.length).toBe(1)
    expect(statusPosts[0]).toEqual({ method: 'POST', id: 3, action: 'activate' })
  })

  test('le menu est filtré par permission : entrée masquée et entrée visible pour un compte support', async ({ page }) => {
    await stubPlatform(page, { user: SUPPORT_USER })

    await page.goto('/')
    const nav = page.getByRole('navigation')
    await expect(nav).toBeVisible({ timeout: 15_000 })

    // Masquées : permissions absentes de la matrice « support ».
    await expect(nav.getByRole('link', { name: /Abonnements/i })).toHaveCount(0) // billing.view
    await expect(nav.getByRole('link', { name: /Équipe plateforme/i })).toHaveCount(0) // team.manage

    // Visibles : permissions portées par « support ».
    await expect(nav.getByRole('link', { name: /Entreprises/i })).toBeVisible() // companies.view
    await expect(nav.getByRole('link', { name: /Utilisateurs/i })).toBeVisible() // users.view
    await expect(nav.getByRole('link', { name: /^Support$/i })).toBeVisible() // support.manage
  })
})
