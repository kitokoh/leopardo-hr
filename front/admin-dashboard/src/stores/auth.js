import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/services/api'
import { useLocaleStore } from '@/stores/locale.js'
import { translate } from '@/i18n/index.js'

const PLATFORM_AUTH_BASE = '/platform/auth'
const PLATFORM_DEVICE_NAME = 'leo-admin-dashboard'

// Security fix (#1299 → #7695): le token super-admin ne vit plus dans un
// stockage DOM pendant la session — il est tenu en mémoire volatile par
// src/services/token-storage.js (hand-off sessionStorage éphémère au seul
// rechargement de page). La migration complète cookie httpOnly + BFF reste
// trackée côté #1299 (le SPA statique Cloudflare Pages n'a pas de serveur
// pour poser le cookie).
// See also: docs/security/AUDIT_API_2026-07-19.md
// Stockage centralisé : src/services/token-storage.js. Ne pas réintroduire
// localStorage/sessionStorage ici (#1575, #7695).
import { getAuthToken, setAuthToken, removeAuthToken } from '@/services/token-storage'
const storage = {
  getToken: getAuthToken,
  setToken: setAuthToken,
  removeToken: removeAuthToken,
};

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(storage.getToken())
  const isLoading = ref(false)
  // #7553/#7557 — permissions effectives du compte plateforme (contrat
  // `permissions[]` de `/platform/auth/me`). Elles pilotent le filtrage des
  // écrans (menu latéral, palette) ; la garde API reste la source de vérité.
  const permissions = ref([])

  /** Traduit une clé i18n dans la locale courante (#4712). */
  function t(key, fallback = '') {
    try {
      const locale = useLocaleStore().current
      return translate(locale, key, fallback)
    } catch {
      return fallback
    }
  }

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const userRole = computed(() => user.value?.role || null)
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')

  /**
   * Rôle interne plateforme (#7553). `user.role` reste `'super_admin'` pour
   * tous les comptes plateforme (rétrocompatibilité) : c'est ce champ qui
   * porte la délégation (`super_admin`, `admin`, `support`, `finance`, `ops`,
   * `marketing`). `null` quand l'API ne l'expose pas (session héritée).
   */
  const platformRole = computed(() => user.value?.platform_role || null)

  /**
   * `super_admin` porte TOUTES les permissions (matrice #7553). Sans
   * `platform_role` exposé (réponse d'API antérieure au déploiement), seul
   * `role === 'super_admin'` est disponible — même sémantique permissive.
   */
  const isSuperAdmin = computed(() => {
    if (platformRole.value) {
      return platformRole.value === 'super_admin'
    }

    return user.value?.role === 'super_admin'
  })

  /**
   * Permission effective du compte courant. Une entrée sans permission
   * (`null`) reste visible ; les autres exigent la permission correspondante.
   */
  function hasPermission(permission) {
    if (!permission) return true
    if (isSuperAdmin.value) return true

    return permissions.value.includes(permission)
  }

  /**
   * Applique l'identité plateforme renvoyée par `/platform/auth/login` et
   * `/platform/auth/me` (id, name, email, role, platform_role, permissions…).
   */
  function applyPlatformIdentity(payload) {
    user.value = payload || null
    permissions.value = Array.isArray(payload?.permissions) ? [...payload.permissions] : []
  }

  async function login(credentials) {
    isLoading.value = true

    try {
      const response = await api.post(`${PLATFORM_AUTH_BASE}/login`, {
        ...credentials,
        device_name: credentials.device_name || PLATFORM_DEVICE_NAME,
      })

      if (response.status === 202 || response.data?.error === 'TWO_FA_REQUIRED') {
        return {
          success: false,
          requiresTwoFactor: true,
          message: response.data?.localized_message || response.data?.message || t('auth.two_fa_required_msg', 'Un code de vérification est requis.'),
        }
      }

      const authToken = response.data?.token
      const userData = response.data?.data

      if (!authToken || !userData) {
        return {
          success: false,
          requiresTwoFactor: false,
          message: t('auth.incomplete_response', 'La réponse de connexion est incomplète.'),
        }
      }

      // #7553/#7557 — l'admin plateforme peut désormais déléguer une partie de
      // son périmètre (support, finance, ops, marketing) : le compte reste un
      // compte PLATEFORME (table `super_admins`, garde `super_admin_api`),
      // mais `role` n'est plus le seul signal. `role` reste `'super_admin'`
      // pour la rétrocompatibilité, `platform_role` porte le rôle interne et
      // `permissions` le périmètre effectif : on accepte donc tout compte
      // renvoyé par l'API plateforme (un compte tenant n'y a pas accès) et on
      // filtre les écrans sur `hasPermission`. La destruction de session sur
      // 401/403/410 reste inchangée (checkAuth, #4515).
      token.value = authToken
      applyPlatformIdentity(userData)
      storage.setToken(authToken)
      api.defaults.headers.common.Authorization = `Bearer ${authToken}`

      // Synchronise la locale avec la préférence de l'utilisateur
      try { useLocaleStore().initFromUser(userData) } catch (e) { console.warn('[admin] locale store not mounted yet', e) }

      return { success: true }
    } catch (error) {
      console.error('Erreur de connexion:', error)

      return {
        success: false,
        requiresTwoFactor: false,
        message: error.response?.data?.localized_message
          || error.response?.data?.message
          || t('auth.connection_error', 'Erreur de connexion.'),
      }
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Réinitialisation synchrone de la session (issue #3929).
   *
   * Un 401 en cours de session (token expiré/révoqué) doit rendre /login
   * accessible : sans cette remise à zéro du store, `isAuthenticated`
   * restait vrai (refs `token`/`user` périmées) et le guard rebondissait
   * systématiquement vers `/` — SPA figée jusqu'au reload complet.
   */
  function clearSession() {
    token.value = null
    user.value = null
    permissions.value = []
    storage.removeToken()
    delete api.defaults.headers.common.Authorization
  }

  async function logout() {
    try {
      if (token.value) {
        await api.post(`${PLATFORM_AUTH_BASE}/logout`)
      }
    } catch (error) {
      console.error('Erreur lors de la deconnexion:', error)
    } finally {
      clearSession()
    }
  }

  async function checkAuth() {
    if (!token.value) {
      return false
    }

    try {
      api.defaults.headers.common.Authorization = `Bearer ${token.value}`
      const response = await api.get(`${PLATFORM_AUTH_BASE}/me`)
      const me = response.data?.data || null

      // #7553/#7557 — plus de garde `role !== 'super_admin'` : le contrat
      // `/platform/auth/me` ne renvoie QUE des comptes plateforme et porte
      // désormais `platform_role` + `permissions`. Le filtrage des écrans se
      // fait sur `hasPermission`, l'API reste autoritaire (403).
      applyPlatformIdentity(me)

      return !!user.value
    } catch (error) {
      // #4515 : ne détruire la session que sur une vraie invalidation (401/403)
      // — un blip réseau ou un 5xx transitoire au démarrage (App.vue, garde du
      // router) déconnectait l'admin et supprimait son token pour rien.
      const status = error?.response?.status
      if (status === 401 || status === 403 || status === 410) {
        console.error('Token invalide:', error)
        await logout()
        return false
      }
      // Erreur transitoire : conserver la session courante (l'utilisateur
      // pourra retenter via le rafraîchissement / les appels suivants).
      console.warn('checkAuth: erreur transitoire, session conservée', error?.response?.status ?? error?.message)
      return !!user.value
    }
  }

  async function updateProfile(payload) {
    try {
      const response = await api.patch(`${PLATFORM_AUTH_BASE}/profile`, payload)
      // Le profil renvoyé porte aussi role/platform_role/permissions (#7553).
      applyPlatformIdentity(response.data?.data || user.value)

      return { success: true, data: response.data?.data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.profile_update_failed', 'La mise à jour du profil a échoué.'),
      }
    }
  }

  async function changePassword(payload) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/change-password`, payload)

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.password_change_failed', 'Le changement de mot de passe a échoué.'),
      }
    }
  }

  async function setup2fa() {
    try {
      const response = await api.post(`${PLATFORM_AUTH_BASE}/2fa/setup`)

      return { success: true, data: response.data?.data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.two_fa_setup_failed', 'La génération du secret 2FA a échoué.'),
      }
    }
  }

  async function enable2fa(code) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/2fa/enable`, { code })
      if (user.value) {
        user.value = { ...user.value, two_fa_enabled: true }
      }

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.two_fa_invalid', 'Le code 2FA fourni est invalide.'),
      }
    }
  }

  async function disable2fa(password) {
    try {
      await api.post(`${PLATFORM_AUTH_BASE}/2fa/disable`, { password })
      if (user.value) {
        user.value = { ...user.value, two_fa_enabled: false }
      }

      return { success: true }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.error,
        message: error.response?.data?.localized_message || error.response?.data?.message || t('auth.disable_two_fa_failed', 'La désactivation du 2FA a échoué.'),
      }
    }
  }

  return {
    user,
    token,
    isLoading,
    isAuthenticated,
    userRole,
    userName,
    userEmail,
    platformRole,
    permissions,
    isSuperAdmin,
    hasPermission,
    login,
    logout,
    clearSession,
    checkAuth,
    updateProfile,
    changePassword,
    setup2fa,
    enable2fa,
    disable2fa,
  }
})
