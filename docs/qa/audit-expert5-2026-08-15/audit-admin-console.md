# Audit statique — Console admin Vue (Leopardo HR)

- **Cible** : `front/admin-dashboard` (Vue 3 + Vite + Pinia + vue-router 5)
- **Repo** : `/home/user/.workspace/leopardo-hr` (branche courante, commit vérifié au 2026-08-15)
- **Backend de référence** : `api/routes/api.php` + modules (`routes/modules/*.php`) — préfixe global `/api/v1`
- **Méthode** : lecture statique croisée front ↔ routes/contrôleurs backend. Aucune modification effectuée.
- **NB** : plusieurs points listés dans la commande (dashboard `request.name`/`manual[0]`, KPI « Leo IA » factices, clés manquantes entre locales) ont été **déjà corrigés** dans ce snapshot (vagues QA 2026-08-14/15) — vérifié, voir « Points de la commande déjà traités » en fin de rapport.

---

## P1 — Sévérité haute

### 1. 12 routes super-admin verrouillées par `requiresTenant`, y compris /chat, /training, /webhooks dont les endpoints `/admin/*` existent
- **Fichiers** : `src/router/index.js:394-398` (guard), `:156,193,203,213,223,233,243,253,263,273,283,293` (meta), `src/components/layout/Sidebar.vue:240-333` (navigation)
- **Snippet** :
  ```js
  if (to.matched.some((record) => record.meta.requiresTenant)) {
    const toast = useToast()
    toast.warning('Fonctionnalité entreprise — réservée aux espaces client')
    next('/')
    return
  }
  ```
- **Constat** : le guard redirige **tout** accès (y compris super-admin) vers `/`. Or le backend expose depuis #2634 des équivalents super-admin **fonctionnels** : `GET /admin/webhooks*` (api.php:340-356), `GET /admin/training/{sessions,enrollments}` (api.php:337-338), `GET/POST /admin/ai/*` (api.php:330-332), et le front les consomme déjà (`ChatView.vue:129,139,168` → `/v1/admin/ai/*`, `WebhooksView.vue:158,192,207` → `/admin/webhooks`, `TrainingView.vue:249-250` → `/admin/training/*`). Résultat : **vues écrites, endpoints existants, accès impossible** — ChatView, WebhooksView, TrainingView (+ 9 autres) sont du code mort à l'exécution.
- **Sévérité** : P1

### 2. Recherche header cassée : `useRouter()` appelé dans un event handler (hors setup)
- **Fichier** : `src/components/layout/Header.vue:272-291`
- **Snippet** :
  ```js
  function handleSearch() {
    const router = useRouter()          // inject() hors setup → undefined
    const matches = router.getRoutes().filter(...)  // TypeError
  ```
- **Constat** : `useRouter()` = `inject(routerKey)` (vue-router 5.2.0, vérifié) ; hors composant actif, `inject()` renvoie `undefined` → `router.getRoutes()` lève une TypeError non catchée : **la recherche header ne fonctionne jamais**. Si elle était réparée, elle naviguerait vers des routes gardées (`/recruitment`, `/training`, `/chat`…) → dead-end (constat 1).
- **Sévérité** : P1

### 3. Export CSV sans protection anti-injection de formule (PayrollView, LeavesView)
- **Fichiers** : `src/views/payroll/PayrollView.vue:460-465`, `src/views/leaves/LeavesView.vue:279-285`
- **Snippet** :
  ```js
  function escapeCsvCell(value) {
    const s = String(value ?? '')
    if (/[;"'\n]/.test(s)) { return `"${s.replace(/"/g, '""')}"` }
    return s                                   // '=HYPERLINK(...)' passe tel quel
  }
  ```
- **Constat** : contrairement à `UsersView.vue:485-490` et `AnalyticsView.vue:244-249` (corrigés #2700/#3045), ces deux exports n'échappent **pas** les cellules commençant par `= + - @` → injection de formule Excel si un libellé employé/politique est malveillant. (Vues actuellement inaccessibles via le guard, mais code livré et réactivé dès qu'on lève le constat 1.)
- **Sévérité** : P1

---

## P2 — Sévérité moyenne

### 4. Composables orphelins : useFocusTrap, useAnnouncer, useNotificationStream — zéro import
- **Fichiers** : `src/composables/useFocusTrap.js`, `src/composables/useAnnouncer.js`, `src/composables/useNotificationStream.js`
- **Constat** : `rg -l` sur tout `src/` : aucun import de ces trois fichiers (seul `useKeyboardShortcuts` est consommé, `DashboardLayout.vue:102`). Le flux SSE notifications (endpoint `/v1/notifications/stream` existant, `rh.php:177`) a été supplanté par le store realtime sans nettoyage ; le focus trap et l'annonceur a11y n'ont jamais été branchés sur les modales. Code mort livré en prod.
- **Sévérité** : P2

### 5. Modal « historique » de TaxRatesView morte : `historyOpen` jamais vrai, `historyItems` jamais chargé
- **Fichier** : `src/views/settings/TaxRatesView.vue:185-214` (template), `:248` (`const historyOpen = ref(false)`), `:373` (`closeHistory` la met à false)
- **Constat** : aucune ligne ne fait `historyOpen.value = true` (seule la fermeture existe) ; aucun `loadHistory()` ne remplit `historyItems` (vide à vie). Le modal et son i18n (`tax_rates.history_*`) sont inatteignables.
- **Sévérité** : P2

### 6. Bandeau « mode maintenance » jamais déclenchable
- **Fichiers** : `src/components/alerts/SystemAlertsOverlay.vue:215-222` (defineExpose) et `src/layouts/DashboardLayout.vue:77` (`<SystemAlertsOverlay />` sans ref)
- **Snippet** :
  ```js
  defineExpose({ ..., setMaintenanceMode: (enabled) => { isMaintenanceMode.value = enabled } })
  ```
- **Constat** : `setMaintenanceMode` n'est appelé nulle part (aucun `ref=` sur le composant) → `isMaintenanceMode` reste `false` à vie ; le bandeau jaune et le bouton « Désactiver » (`SystemAlertsOverlay.vue:69-93`, `:196-203`) sont du code inerte. Le bouton Désactiver affiche d'ailleurs un toast « désactivation non disponible » — incohérence de surface assumée.
- **Sévérité** : P2

### 7. i18n : `document.title` et titres de page restent en français pour ar/en/tr
- **Fichiers** : `src/router/index.js:400-410`, `src/layouts/DashboardLayout.vue:33`
- **Snippet** :
  ```js
  const raw = String(to.meta.title)
  const title = raw.includes('.') ? translate(useLocaleStore().current, raw, raw) : raw
  document.title = `${title} - Leopardo RH Admin`
  ```
- **Constat** : seuls 2 `meta.title` sont des clés i18n (`marketing.oauth.nav_title`, `holidays.nav.title`). Les ~35 autres (`'Tableau de bord'`, `'Utilisateurs'`, `'Connexion'`…) sont des chaînes FR en dur : `document.title` et le `<h1>` (`$t($route.meta.title, $route.meta.title)` avec fallback = la chaîne FR) restent en français dans les 3 autres locales. Les clés i18n elles-mêmes existent dans les 4 locales (vérifié : ensembles de clés identiques), le problème est l'absence de clés.
- **Sévérité** : P2

### 8. Chaînes FR en dur massives dans les composants (Header, Sidebar, vues)
- **Fichiers** : `src/components/layout/Header.vue:107-109,138-143` (`'Connecté'`, `'Mode secours (polling)'`, `'Aucune notification'`, `'Alertes critiques'`…), `src/components/layout/Sidebar.vue:205-335` (~25 titres FR en dur, seul `marketing-oauth` passe par `t()`), `src/views/DashboardView.vue:395` (`labels = { high: 'Risque eleve', ... }` — en dur **et** accent manquant), `:400` (`'Non renseigné'`), `src/views/chat/ChatView.vue:34`, `src/views/analytics/AnalyticsView.vue:20-22,39-59`…
- **Constat** : l'i18n (`$t`) n'est appliquée qu'aux vues settings récentes ; le shell applicatif (header, sidebar, notifications) et la plupart des vues sont monolingues FR.
- **Sévérité** : P2

### 9. « Tout marquer comme lu » : POST sur un endpoint inexistant (verbe faux), échec silencieux
- **Fichiers** : `src/stores/realtime.js:350-353` (front), `api/routes/modules/rh.php:175` (PUT alias), `api/routes/modules/dashboard.php:35` (canonique)
- **Snippet** :
  ```js
  await api.post('/v1/notifications/read-all', null, { _skipAuthRedirect: true })
  ```
- **Constat** : le backend expose `PUT /notifications/read-all` (alias rétro-compat, rh.php:175) et `POST /notifications/mark-all-read` (dashboard.php:35) — **pas** de `POST /notifications/read-all` → 405/404, avalé par `catch (err) { console.warn(...) }` : le bouton du header (`Header.vue:90`) ne persiste jamais rien (et de toute façon 401 tenant pour un super-admin).
- **Sévérité** : P2

### 10. `prompt()` / `confirm()` natifs (UX bloquante, non stylée, XSS-safe mais hostile)
- **Fichier** : `src/views/growth/GrowthDashboardView.vue:178` et `:166`
- **Snippet** :
  ```js
  const reason = prompt("Note de paiement (Audit) :");
  if (!confirm(`Approuver la candidature de ${partner.user.email} ?`)) return;
  ```
- **Constat** : aucune modale applicative ; le `prompt()` bloque le rendu et casse la cohérence visuelle (thème sombre). Mineur mais réel.
- **Sévérité** : P2

### 11. Erreurs API avalées silencieusement (données vides présentées comme réelles)
- **Fichiers** : `src/views/training/TrainingView.vue:248-250`, `src/views/fleet/FleetView.vue:180`, `src/views/recruitment/RecruitmentView.vue:404`
- **Snippet** :
  ```js
  api.get('/v1/admin/fleet/alerts').catch(() => ({ data: { data: [] } }))
  ```
- **Constat** : tout échec (réseau, 500, 401) devient « liste vide » sans message ni indicateur d'erreur — l'utilisateur voit un tableau vide crédible alors que le backend est injoignable. Le pattern est documenté comme « cold-start » mais s'applique à toutes les erreurs.
- **Sévérité** : P2

### 12. Panneau notifications super-admin structurellement vide (push localhost par défaut + polling désactivé sur 401)
- **Fichiers** : `src/stores/realtime.js:58` (`io(import.meta.env.VITE_WEBSOCKET_URL || 'ws://localhost:6001', ...)`), `:146-179` (polling `/notifications` → 401 super-admin → `stopPolling()`)
- **Constat** : en prod, sans `VITE_WEBSOCKET_URL`, le socket pointe vers `localhost:6001` (aucune chance de se connecter) ; le fallback polling `/notifications` répond 401 pour le guard `super_admin_api` et est désactivé. L'inbox du header (`Header.vue:96-125`) et `NotificationPanel` ne montreront donc jamais rien à un super-admin — UI cosmétique sans signal d'erreur.
- **Sévérité** : P2

---

## P3 — Sévérité basse

### 13. ExportsView : contrat d'API incohérent (endpoint admin + endpoints tenant mélangés)
- **Fichiers** : `src/views/exports/ExportsView.vue:146-151` (endpoints `/v1/export/*` — tenant, `dashboard.php:57-63`), `:185` (`/v1/admin/hr-reports` — existe pour super-admin), `:200` (`/v1/export/history` — tenant)
- **Constat** : même si le guard sautait, 6 des 7 exports 401 pour un super-admin. La vue a été partiellement migrée vers le contrat admin sans cohérence d'ensemble.
- **Sévérité** : P3

### 14. Conflit de raccourci Ctrl+K (palette de commandes + focus champ recherche)
- **Fichiers** : `src/composables/useKeyboardShortcuts.js:36-38` (Ctrl+K → `document.getElementById('search')?.focus()`), `src/components/common/CommandPalette.vue:130-140` (Ctrl+K → toggle palette)
- **Constat** : les deux écoutent `keydown` sur `window` : Ctrl+K déclenche la palette ET le focus de la recherche simultanément (comportement non déterministe selon l'ordre d'enregistrement).
- **Sévérité** : P3

### 15. Export groupé UsersView : le bouton « Exporter » du panneau de sélection ignore la sélection
- **Fichier** : `src/views/users/UsersView.vue:257-259` et `:370`
- **Snippet** :
  ```js
  function exportSelectedUsers() { exportUsers() }  // exporte TOUTE la page courante
  ```
- **Constat** : le libellé (`users.bulkPanel.export`, ligne 106) laisse croire à un export des lignes cochées ; il exporte la page entière. Comportement trompeur, documenté en commentaire mais non signalé à l'utilisateur.
- **Sévérité** : P3

### 16. Commande palette : libellés FR en dur + routes gardées absentes (incohérence mineure)
- **Fichiers** : `src/components/common/CommandPalette.vue:101-116`
- **Constat** : libellés FR en dur (rejoint le constat 8) ; la palette n'offre pas les vues gardées (contrairement à la sidebar) — navigation incohérente entre les deux surfaces.
- **Sévérité** : P3

### 17. `useLocaleStore`/`toIntlLocale` utilisés dans Header mais `defineEmits` sans référence
- **Fichier** : `src/components/layout/Header.vue:241` (`defineEmits(['toggle-sidebar'])` sans variable) — style, pas de bug. Reporté pour complétude mineure.
- **Sévérité** : P3

---

## Points de la commande vérifiés et DÉJÀ traités dans ce snapshot (pas de constat)

- **Dashboard `request.name` / `manual[0]`** : `DashboardView.vue` lit désormais `/platform/companies/health`, `/platform/metrics/overview`, `/platform/company-requests` (contrats backend vérifiés api.php:241,250,260 ; shapes `data.summary`/`data.items` alignées).
- **KPI « Leo IA » factices** : plus aucun KPI factice ; `'Leo IA'` n'est plus qu'un label de feature dans `SubscriptionsView.vue:216`. AnalyticsView affiche un état honnête « pas de backend » (AnalyticsView.vue:145).
- **Clés manquantes entre les 4 locales** : ensembles de clés **identiques** ar/en/fr/tr (script de comparaison, 0 clé manquante ; seules ~18 valeurs identiques au FR sont des noms propres/pays).
- **`useToast()` dans le guard router** (router/index.js:395) et dans l'intercepteur axios (api.js:79) : vérifié dans vue-toastification@2.0.0-rc.5 — `useToast()` hors setup retombe sur `createToastInterface(globalEventBus)` → fonctionne (pas un bug).
- **EditUserModal** : aucun fichier ni référence dans le repo (mention de la commande non reproductible).
- **Export CSV UsersView/AnalyticsView** : échappement anti-formule déjà présent (#2700/#3045) — le manque ne subsiste que sur PayrollView/LeavesView (constat 3).

## Échantillon de lignes clés (repères)
| Sujet | Fichier:ligne |
|---|---|
| Guard requiresTenant | router/index.js:394-398 |
| Route /chat (requiresTenant) | router/index.js:243 |
| Route /training | router/index.js:223 |
| Route /webhooks | router/index.js:253 |
| Sidebar (12 entrées gardées) | Sidebar.vue:240-333 |
| handleSearch (useRouter hors setup) | Header.vue:277 |
| useNotificationStream (0 import) | composables/useNotificationStream.js:1 |
| Modal historique morte | TaxRatesView.vue:185 / :248 / :373 |
| setMaintenanceMode jamais appelé | SystemAlertsOverlay.vue:220 |
| document.title FR en dur | router/index.js:404-410 |
| escapeCsvCell sans garde formule | PayrollView.vue:460-465 |
| POST read-all inexistant | realtime.js:352 |
| prompt() natif | GrowthDashboardView.vue:178 |
| catch silencieux | FleetView.vue:180, TrainingView.vue:248-250 |
| ws localhost par défaut | realtime.js:58 |
