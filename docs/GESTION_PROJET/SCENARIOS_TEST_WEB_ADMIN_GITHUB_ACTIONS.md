# SCENARIOS DE TEST WEB ADMIN POUR GITHUB ACTIONS

## Objectif

Donner une base de scenarios stable pour le dashboard `front/admin-dashboard/`, avec une execution automatique dans Playwright et des artefacts exploitables en CI.

## Perimetre

- auth admin
- navigation protegee
- rendu des vues critiques
- garde-fous UX/a11y
- non-regression sur les parcours administratifs prioritaires

## Niveaux de test

1. `Lint` pour hygiene statique
2. `Build` pour integrite du bundle
3. `Playwright E2E` pour les parcours critiques visibles

## Matrice des scenarios

### 1. Auth et session

- page login accessible
- soumission login avec champs invalides
- feedback de chargement lisible
- redirection post-login vers la vue attendue
- echec d'authentification sans crash visuel
- login plateforme branche sur `/api/v1/platform/auth/login` et non sur des routes `/admin/auth/*` inexistantes
- un retour `202 TWO_FA_REQUIRED` affiche un champ 2FA exploitable au lieu de marquer la session comme connectee
- un `401` API nettoie la session locale et renvoie proprement vers `/login`
- **garde de navigation (#7305)** : acces anonyme a une route protegee => `/login` ; utilisateur connecte sur `/login` => redirection vers `/` ; **aucun avertissement `VUE_ROUTER_R0025`** en console (les guards retournent la route cible au lieu d'appeler `next()`)

### 2. Navigation protegee

- acces anonyme a une route protegee => redirection login
- acces authentifie a la home admin
- menu et breadcrumbs visibles quand attendus
- retour arriere coherent depuis une vue detail

### 3. Etats critiques UI

- loading state visible et annonce par semantics/labels utiles
- empty state lisible
- error state actionnable
- aucun chevauchement evident dans les vues prioritaires
- **temps reel (#7303)** : sans `VITE_WEBSOCKET_URL` configure, aucune tentative de handshake Socket.IO (pas de `404` en console) ; l'etat degrade est affiche (`Mode secours (polling)` / `Push non configure`) et les notifications continuent d'arriver via le polling REST (couvert par `e2e/notification-fallback-polling.spec.js`, assertion `socketAttempts === 0`)

### 4. Accessibilite minimum

- labels/tooltip sur actions icon-only
- indicateurs de chargement annonces
- contrastes et focus visibles sur les parcours critiques
- listes critiques lisibles au clavier et par lecteur d'ecran

### 5. Regressions de formulaires

- validation d'un formulaire de connexion
- prevention du double submit sur action critique
- messages d'erreur stables
- saisie du code 2FA sans perdre l'email deja renseigne
- toggle afficher / masquer le mot de passe visible, accessible au clavier et coherent avec les labels ARIA

### 6. Cockpit plateforme v5.0

- la page d'accueil charge une synthese via `/api/v1/platform/companies/health`, `/api/v1/platform/metrics/overview` et `/api/v1/platform/company-requests?status=pending`
- les priorites clients, MRR, ARR, ARPA, encaissements 30 jours, impayes, adoption terrain et demandes entrantes remplacent les anciens widgets mockes
- la vue Entreprises charge l'**annuaire** via `/api/v1/platform/companies` (< 1 s) et affiche les lignes **sans attendre** le scoring ; les scores (`/api/v1/platform/companies/health`, coûteux) sont hydratés **en tâche de fond** avec l'indicateur « Calcul des scores en cours… » (#7302, couvert par `e2e/companies-progressive-portfolio.spec.js`)
- colonnes de score en placeholder « — » tant que le scoring n'est pas revenu (jamais `null%` ni `SANS PLAN` trompeur)
- repli sur l'ancien chemin (`companies/health` seul) si l'annuaire est indisponible
- le detail Entreprise charge health client, abonnement et catalogue plans
- le formulaire abonnement met a jour plan, statut, dates et notes sans hardcoder les `plan_id`
- la vue Abonnements affiche le catalogue `/api/v1/platform/plans`, les metriques `/api/v1/platform/metrics/overview`, le MRR portefeuille, les impayes et les clients prioritaires
- les etats loading/error restent lisibles si l'API plateforme est indisponible

### 7. Intake demandes clients

- la vue Support charge les demandes via `/api/v1/platform/company-requests`
- les filtres pending/approved/rejected mettent a jour la file de qualification
- les compteurs statut restent visibles pour suivre le pipe commercial
- les actions approuver/rejeter envoient `PATCH /api/v1/platform/company-requests/{id}` avec notes internes
- une demande deja traitee ne propose plus d'action de decision

### 8. Paie et conges (tenant manager)

- La vue `/payroll` charge les runs via `GET /api/v1/payroll-runs` (pagination absorbee cote SPA), les bulletins via `GET /api/v1/pay-slips` (pagination absorbee cote SPA ; evite un `pay-slips` par run), actions Calculer/Valider via POST calculate/validate
- Le resume run utilise `GET /api/v1/payroll-runs/{id}/summary`
- Le telechargement PDF bulletin passe par la session axios (`Authorization`) avec `responseType: blob`, pas par lien nu `/api/...`
- Les exports CSV paie sont generes cote navigateur depuis les lignes chargees (pas de routes `/export/*` inventees)
- La vue `/leaves` charge `GET /api/v1/absences`, `GET /api/v1/leave-balances`, `GET /api/v1/leave-policies`
- Approbation / refus utilisent `PUT /api/v1/absences/{id}/approve` et `PUT .../reject` avec corps `{ rejected_reason }`

### 9. Fuel — stations-service (tenant manager, bc15)

- La console d'exploitation `/fuel-station/operations` charge la vue d'ensemble multi-stations (stats stations/caisses/incidents/rapprochements) et pilote les onglets stations, équipements, shifts, incidents, rapprochements via les endpoints `GET /api/v1/fuel-station/*` réels
- Arbitrage du doublon de path (audit 2026-09-10) : `/fuel-station` reste le hub `FuelManagerView` (cible de la Sidebar et des e2e), la console `FuelStationView` est déplacée sur `/fuel-station/operations` — l'URL directe et la navigation par nom rendent désormais la même vue
- Les 4 rapports utilisent les segments de route réels (`daily-volumes`, `sales`, `stock`, `variances`) et non les identifiants internes (`daily_volumes`, `sales_summary`, `stock_status`, `variance_summary`) qui répondaient 404
- Le téléchargement d'un export passe par la session axios (`downloadApiFile`, baseURL + `Authorization`, `responseType: blob`), pas par un lien nu `/api/...` relatif au domaine de l'admin (404 systématique), avec un état de chargement par ligne
- Rendu vérifié après restauration de la version bc15 (608 l) : template SFC valide, clés i18n `fuel.*` cohérentes avec `FuelManagerView` et les catalogues fr/en/ar/tr

### 10. Dashboard — véracité des indicateurs (audit 2026-09-10)

- Les KPI (entreprises actives, tenants, MRR, alertes, demandes) reflètent la réponse de `/api/v1/platform/metrics/overview` ; un `0` renvoyé par l'API est affiché comme `0` et ne doit jamais retomber sur une valeur de `summary` (`/platform/companies/health`)
- Non-régression : remplacer la donnée par `0` ne doit pas ressusciter un compteur périmé (garde sur la distinction `null`/`undefined` vs `0`)

### 11. Fiche Entreprise — Onboarding vs Adoption (#7300)

Le back-office affichait « ONBOARDING 40 % — RISK HIGH » pour un client dont
l'assistant affichait « Configuration terminée ». Les trois surfaces de
progression sont désormais alignées sur une source de vérité unique (table
`onboarding_steps`, via `/onboarding-setup/checklist`) :

- La carte **Onboarding** de `/companies/:id` affiche la progression canonique —
  **la même valeur que celle vue par le client**, pas une échelle parallèle.
- La valeur est **pilotée par `adoption.onboarding.progress_percent`** ; le
  champ `source` vaut `onboarding_steps`.
- Une société dont la checklist n'a jamais été amorcée affiche **« — »**
  (`initialized: false`), **jamais 0 %** — un 0 % se lirait « mauvais élève »
  alors que rien n'a été demandé au client.
- La mention **« Adoption terrain »** (`adoption.onboarding.observed.progress_percent`)
  reste affichée sous la carte : « onboarding » (ce que le client a configuré)
  et « adoption » (ce que le serveur observe) sont deux notions distinctes et ne
  doivent jamais être confondues à l'écran.
- Non-régression : pour un tenant dont toutes les étapes setup sont complétées,
  la fiche Entreprise et l'assistant client affichent le **même pourcentage**.
  Côté API, `OnboardingProgressAlignmentTest` verrouille cette égalité (setup
  complet **et** incomplet) — le test échoue si une surface réintroduit une
  échelle parallèle.
- Le risque client ne doit plus être déclenché par un onboarding « inachevé »
  calculé autrement : le malus de score se juge sur `go_live_ready`.

### 12. Portefeuille clients — l'API ne recalcule plus société par société (#7302)

La vue `/companies` restait sur « Syncing portfolio… » pendant de longues secondes.
Deux chantiers distincts :

- **Affichage** (déjà livré) : la vue charge d'abord **l'annuaire**
  (`GET /platform/companies?per_page=100`) pour rendre la page utilisable tout de suite, puis
  les **scores** en tâche de fond et fusionne les deux (`isScoring` / `scoringFailed`).
- **Cause racine** (ce lot) : l'API recalculait la santé **société par société** — ~15 requêtes
  par tenant, mesurées à **674 requêtes** pour 45 sociétés, soit les ~27 s constatées en
  production. Le calcul est désormais **groupé** (une poignée de requêtes, indépendantes du
  nombre de sociétés) et mis en cache.

À vérifier :

- La liste s'affiche **sans attendre** les scores (colonnes de score à « — » le temps du calcul).
- Le compteur de requêtes de `GET /platform/companies/health` **ne dépend pas** du nombre de
  sociétés : c'est ce que verrouille
  `test_portfolio_query_count_does_not_grow_with_company_count` (avec l'implémentation
  précédente, 2 sociétés coûtaient déjà 33 requêtes).
- Le clic sur **« Actualiser »** envoie `?refresh=1` et déclenche un **recalcul réel** : le
  portefeuille est mis en cache 60 s (`PORTFOLIO_CACHE_TTL_SECONDS`, donnée dérivée,
  invalidation temporelle), donc un rafraîchissement explicite ne doit pas resservir une valeur
  périmée. Un simple rechargement de page, lui, peut être instantané (cache).
- **Cohérence portefeuille ↔ fiche société** : les deux doivent annoncer les mêmes chiffres
  (score, risque, employés actifs, pointages 30 j, anomalies critiques, MRR, plan, prochaine
  action) — `test_portfolio_and_company_detail_agree_on_shared_metrics`. Deux chemins de calcul
  pour une même donnée sont exactement ce qui avait produit les trois progressions d'onboarding
  divergentes (#7300).

### 13. Console propre du back-office — plus d'attribut perdu sur `<Sidebar>` (#7305)

`DashboardLayout.vue` passait `class="fixed inset-y-0 left-0 z-50"` au composant `<Sidebar>`.
Or `Sidebar.vue` a une **racine fragmentaire** (l'overlay mobile `<transition>` et la sidebar
sont deux nœuds frères) : Vue ne pouvait pas hériter l'attribut et le **perdait silencieusement**
en émettant à chaque montage

```
[Vue warn]: Extraneous non-props attributes (class) were passed to component but could not be
automatically inherited because component renders fragment or text or teleport root nodes. at <Sidebar …>
```

Correctif : le composant déclare `inheritAttrs: false` et rebranche explicitement `v-bind="$attrs"`
sur la racine « sidebar » ; le `class` redondant du layout (déjà porté par cette racine) est retiré.

À vérifier :

- Le dashboard se monte **sans aucun avertissement Vue** de ce type —
  `e2e/sidebar-attrs-console-clean.spec.js` (le test **échoue** si l'avertissement réapparaît :
  vérifié en réintroduisant le défaut).
- La sidebar reste **en position fixe**, calée à gauche et au-dessus du contenu
  (`position: fixed`, `left: 0`, `z-index >= 50` mesurés sur l'élément) : retirer le `class` du
  layout ne doit pas casser la mise en page.

### 14. Menu plateforme — modules d'entreprise cliente regroupés (#7329)

Les écrans « Formations », « Flotte véhicules », « Stations-service » et
« Agence de voyage » ne s'adressent pas à la plateforme mais au périmètre d'une
**entreprise cliente** : ce ne sont plus des entrées de premier niveau du menu
superadmin.

- « Entreprises » reste le rail de premier niveau ; les quatre écrans sont
  regroupés sous un titre de section **« Modules des entreprises clientes »**,
  rendu juste après l'entrée « Entreprises ».
- La section est repliable, mais **ouverte par défaut** : une section repliée
  par défaut masquerait des écrans existants. Le repli est mémorisé
  (`localStorage`) et la section contenant la route courante est toujours
  dépliée.
- Non-régression : les quatre écrans restent atteignables en un clic et présents
  dans l'arbre d'accessibilité — `travel-navigation.spec.js` échoue si l'entrée
  « Agence de voyage » disparaît du menu.
- `e2e/sidebar-unique-entries.spec.js` vérifie le regroupement par la géométrie
  (les 4 entrées sont **sous** le titre, lui-même **sous** « Entreprises ») puis
  le repli/dépli réel. Ce bloc n'est **pas** conditionné à
  `PLAYWRIGHT_AUTH_TOKEN` (absent du job `web-ci.yml`) : la session y est
  simulée, sinon la garde ne s'exécuterait jamais en CI.
- Convention : toute nouvelle entrée d'un module d'entreprise cliente rejoint ce
  groupe, pas le rail principal.

### 16. Portefeuille clients — l'endpoint de scoring est paginé (#7339)

**MAJ 2026-09-14 (#7339) — l'endpoint est désormais paginé, sans changement pour la vue.**
La réponse gagne un bloc `meta` (`current_page`, `per_page`, `total`, `last_page`,
`from`/`to`) et accepte `?page=&per_page=` (`limit` reste un alias). C'est **additif** :
`CompaniesView` / `DashboardView` / `SubscriptionsView` continuent de lire
`data.items` / `data.summary` sans modification, et un appel **sans paramètre** rend
exactement la même page qu'avant (page 1, 50 sociétés). À vérifier : la vue Entreprises
reste fonctionnelle telle quelle (aucun paramètre de pagination ajouté au scoring côté
admin) ; `summary` décrit la page — le total réel du portefeuille est dans `meta.total`.
Spécification : `docs/specifications/ISSUE_7339_PLATFORM_COMPANIES_HEALTH_PAGINATION.md`.
Limite connue (suivie hors de ce lot) : au-delà de 50 sociétés, les lignes de l'annuaire
(chargé sur 100) situées après la page 1 restent sans score (« — »).

## Artefacts obligatoires

- rapport HTML Playwright
- `test-results/junit.xml`
- screenshots en cas d'echec
- traces au premier retry
- videos retenues en echec

## Politique video

Les videos ne sont pas exigees pour chaque run afin d'eviter un cout de stockage inutile.
En revanche, elles doivent etre conservees automatiquement en cas d'echec Playwright.

## Criteres GO / NO GO

- GO: lint + build + Playwright verts
- NO GO: echec auth critique, navigation protegee cassee, rendering blank, ou artefacts d'echec manquants

## Extension i18n enterprise

### Locales enterprise (extension)

- Les dictionnaires generes dans `front/admin-dashboard/src/i18n/locales/` restent synchronises avec `shared/i18n/locales/`
- Une locale variante (`fr-CA`, `en-GB`, `ar-SA`) est normalisee sans casser le rendu
- La direction `rtl` est resolue correctement pour l'arabe
- Aucun import ou helper i18n ne doit casser le build quand la surface web change avec `shared/i18n/**`
