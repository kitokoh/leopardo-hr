/**
 * Guards de navigation du router admin-dashboard.
 *
 * ## Pourquoi ce module existe
 *
 * Le guard vivait en ligne dans `router/index.js`, dans un `router.beforeEach`
 * qui n'était atteignable qu'en montant un router complet avec un vrai
 * historique de navigateur. Résultat : **aucun test unitaire** ne couvrait la
 * décision d'accès, alors que c'est elle qui décide qui voit quoi.
 *
 * ## Le manquement corrigé ici
 *
 * Les routes déclarent `meta.permission` (`recruitment.view`,
 * `search_intel.view`…) depuis #7553, et `stores/auth.js` expose
 * `hasPermission()`. Mais le guard **ne lisait jamais `meta.permission`** : il
 * ne vérifiait que `requiresAuth`. Un compte plateforme connecté sans
 * `search_intel.view` atteignait donc `/search-intel` par URL directe, et
 * l'écran ne se fermait qu'au premier 403 de l'API — après montage du
 * composant, après ses appels, et avec un écran vide plutôt qu'un refus clair.
 *
 * Le filtrage du menu masquait le lien, ce qui rendait le défaut invisible en
 * navigation normale : il ne se voyait qu'en tapant l'URL ou en suivant un
 * favori.
 *
 * ## Ce que le guard ne fait pas
 *
 * Il ne remplace pas l'autorisation serveur. L'API reste autoritaire et répond
 * 403 ; ce guard évite seulement de monter un écran qu'on sait déjà interdit.
 * Un contrôle côté client n'est jamais une frontière de sécurité.
 */

/** Route de repli quand l'utilisateur n'a pas la permission demandée. */
export const FORBIDDEN_PATH = '/forbidden'

/** Route de connexion. */
export const LOGIN_PATH = '/login'

/**
 * Construit le guard `beforeEach`.
 *
 * Les dépendances sont injectées pour que le test n'ait besoin ni de Pinia ni
 * d'un router : on appelle la fonction avec des doubles et on lit sa valeur de
 * retour, exactement comme Vue Router le fait.
 *
 * @param {object} deps
 * @param {() => object} deps.useAuthStore  Fabrique du store d'auth (Pinia).
 * @param {() => object} [deps.useLocaleStore] Fabrique du store de locale.
 * @param {(locale: string, key: string, fallback: string) => string} [deps.translate]
 * @param {object} [deps.nprogress]  Barre de progression (facultative).
 * @param {(title: string) => void} [deps.setTitle] Écriture du titre du document.
 * @returns {(to: object) => Promise<true | string>} Guard Vue Router.
 */
export function createAuthGuard({
  useAuthStore,
  useLocaleStore,
  translate,
  nprogress,
  setTitle,
}) {
  return async function authGuard(to) {
    nprogress?.start?.()

    const authStore = useAuthStore()

    // 1. Authentification.
    if (to.meta?.requiresAuth && !authStore.isAuthenticated) {
      if (authStore.token) {
        const isValid = await authStore.checkAuth()
        if (!isValid) {
          return LOGIN_PATH
        }
      } else {
        return LOGIN_PATH
      }
    }

    // 2. Autorisation — le contrôle qui manquait.
    //
    // On ne l'évalue que sur une route authentifiée : une route publique qui
    // déclarerait une permission par erreur ne doit pas renvoyer un visiteur
    // anonyme vers `/forbidden` au lieu de `/login`.
    const required = to.meta?.permission
    if (required && to.meta?.requiresAuth && !authStore.hasPermission(required)) {
      return FORBIDDEN_PATH
    }

    // 3. Un utilisateur déjà connecté n'a rien à faire sur `/login`.
    if (to.name === 'login' && authStore.isAuthenticated) {
      return '/'
    }

    // 4. Titre de la page dans la locale active.
    if (to.meta?.title && translate && useLocaleStore) {
      const localeStore = useLocaleStore()
      const title = translate(localeStore.current, to.meta.title, to.meta.title)
      setTitle?.(`${title} - Leopardo Admin`)
    }

    return true
  }
}
