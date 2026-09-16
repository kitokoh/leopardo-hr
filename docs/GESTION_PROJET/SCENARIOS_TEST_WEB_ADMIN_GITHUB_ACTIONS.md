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
- **attributs herites (#7305)** : charger une vue du back-office (le `<Sidebar>` de `DashboardLayout`) et verifier qu'aucun avertissement
  `[Vue warn]: Extraneous non-props attributes (class)` n'est emis — le composant a deux noeuds racines, ses attributs sont donc lies
  explicitement au panneau (`inheritAttrs: false` + `v-bind="$attrs"`)
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

### 15. Paramètres › E-mails — contenu des e-mails éditable (#7347)

Nouvel écran `/settings/emails` : le super-admin modifie l'**objet**, le **titre**,
le **corps** et le **libellé du bouton** des e-mails transactionnels, **par langue**
(fr/en/ar/tr), avec **aperçu rendu dans le vrai layout** et **retour au défaut**.

- **Sans surcharge, rien ne change** : les valeurs par défaut restent celles du
  catalogue `api/lang/*/emails.php` ; l'écran affiche la valeur *effective*
  (surcharge si elle existe, sinon défaut).
- Le corps est du **texte** : retours à la ligne conservés, HTML **non interprété**,
  et seules les variables déclarées par le registre sont substituées.
- Les deux badges à vérifier : « personnalisé » dans la liste dès qu'**une** langue
  a été modifiée, et la remise à zéro qui fait disparaître la surcharge.

À vérifier (spec `e2e/email-templates-editor.spec.js`) :

- l'écran charge la liste des modèles et affiche la valeur effective ;
- « Enregistrer » envoie `(template_key, locale)` + les champs ;
- « Revenir au défaut » supprime la surcharge ;
- « Aperçu » affiche le HTML renvoyé par l'API dans l'iframe.

Côté API, `tests/Feature/Mail/EmailTemplateEditingTest.php` verrouille la résolution
(surcharge > défaut), l'échappement du corps, l'ignorance des variables non
déclarées, la séparation des langues et la traçabilité (`updated_by`).
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

### 17. Actions destructives confirmées et échecs visibles (#7433)

Audit d'ergonomie destructrice de la console admin : cinq actions partaient au
premier clic (sans confirmation) et plusieurs échecs d'API étaient avalés sans
message (l'écran restait « normal »). Aucune action destructive ne doit plus
partir sans confirmation, et aucun échec ne doit rester silencieux.

Confirmations (composant existant `ConfirmDialog`, aucun `window.confirm`) :

- **Agence de voyage › Contenu** : « Supprimer » sur un référentiel (types,
  positions, tarifs) et sur un site touristique ouvre la confirmation en nommant
  l'élément ; l'erreur de suppression est remontée en toast (message de l'API).
- **Agence de voyage › Contenu › Contacts** : la bascule d'un consentement
  (email/SMS/WhatsApp) qui échoue affiche un toast **et remet la case à l'état
  réel** — jamais un consentement affiché comme enregistré.
- **Agence de voyage › Contenu** : les actions du cycle de vie des annonces
  (payer, valider, renouveler) affichent l'échec en toast.
- **Agence de voyage › Contenu** : enregistrer un formulaire qui échoue **laisse
  la modale ouverte et la saisie intacte**, avec l'erreur de l'API en toast.
- **Agence de voyage › Catalogue** : la croix de suppression d'une image de
  véhicule passe par la confirmation (elle supprimait au premier clic).
- **Agence de voyage › Annonces / Quiz / Sites** : plus aucun dialogue natif du
  navigateur (les 12 appels `confirm`/`alert` des trois écrans sont remplacés par
  `ConfirmDialog` + toast), y compris l'échec du paiement/renouvellement d'annonce.
- **Vitrine** (`/showcase`) : la suppression d'une section est confirmée.
- **Utilisateurs** : l'action groupée « Désactiver » demande confirmation **en
  annonçant le nombre de comptes concernés** (`:count`) ; « Activer » et
  « Exporter » restent immédiats (non destructifs).

Véracité des états (jamais un état faux affiché comme réel) :

- **Paramètres › Assistant IA** : si `GET /admin/platform/ai/health` échoue,
  l'en-tête affiche « état de l'assistant indisponible » — **jamais
  « assistant désactivé »** ; si `GET .../monitoring` échoue, l'onglet Suivi
  affiche « suivi indisponible » — **jamais « 0 requête »** (le zéro trompeur
  reste réservé à une réponse API réelle valant zéro).
- **Paramètres › E-mails** : une liste vide affiche un état vide explicite
  (« aucun modèle d'e-mail disponible »), distinct du bandeau « chargement
  impossible ».

À vérifier (recette) :

- Les 4 locales (fr/en/ar/tr) rendent les nouveaux libellés, y compris en RTL.
- `eslint .` et `vite build` restent verts ; `grep -rn "window.confirm\|window.alert" src` est vide.
- Les nouveaux libellés passent par le catalogue (`check-i18n-diff.js` vert).

### 18. Actions de ligne — une seule convention, icône accessible, en-tête traduit (#7434)

La console mélangeait deux conventions d'action de ligne (icônes dans la table
des utilisateurs, boutons texte « Modifier »/« Supprimer » dans une vingtaine
d'autres vues) et écrivait l'en-tête de colonne « Actions » en clair — donc en
français même en locale `en`/`ar`/`tr`.

Règle (propriétaire, 2026-09-14) : « si tout est icône, pourquoi lui reste-t-il
son texte ? Les seules choses qui peuvent rester icône **et** texte, c'est le
menu. »

À vérifier en recette :

- **Partout où une table porte des actions de ligne** (Annonces, Catalogue et
  hôtels, Réseau, Réservations, Quiz, Référentiel, Sites, Contacts, Billets,
  Fériés, Cotisations, Webhooks, Fuel, Flotte, Exports, Utilisateurs) :
  l'action est une **icône seule**, jamais un bouton texte.
- **Nom accessible** : chaque icône-action porte un `title` ET un `aria-label`
  (composant `RowActionButton.vue`) — une icône seule sans nom est invisible au
  lecteur d'écran.
- **En-tête de colonne** « Actions » traduit dans les 4 locales (fr/en/ar/tr),
  y compris en RTL.
- **Aucun changement de comportement** : mêmes actions, mêmes états `disabled`,
  mêmes confirmations (`ConfirmDialog`) pour les actions destructives.
- **Couleurs conservées** : les tons (danger/succès/avertissement) restent
  distincts à l'écran — la sémantique ne doit pas se perdre avec le libellé.
- **Garde CI** : `python3 dev-hub/tools/check-admin-action-labels.py` doit sortir 0
  (**bloquant** dans `web-ci.yml`, job `web-lint`) — la garde #7434 unique, autotestée par
  `dev-hub/tools/check-admin-action-labels-test.sh` (registre `docs/GOUVERNANCE/REGISTRE_GARDES.md`).
  La garde de branche `check-admin-row-actions.py` de #7461 est **supprimée** : redondante.

### 19. Fiche Entreprise — les libellés de modules sont localisés et la Formation est un vrai switch (#7432)

`CompanyDetailView.vue` affichait les features connues via
`t('companyDetail.features.<clé>', '<libellé français en dur>')` — or **aucune**
des 4 locales ne portait l'objet `companyDetail.features.*` : tous les libellés
venaient donc du repli codé en dur dans le composant (`Centre de Formation`,
`Ressources Humaines`…), non traduisibles. Les 8 clés réellement référencées
(`rh`, `finance`, `ai`, `cameras`, `tracking`, `planning`, `training`, `cabinet`)
sont désormais dans la source de vérité `shared/i18n/locales/{fr,en,ar,tr}.json`
puis propagées à `front/admin-dashboard/src/i18n/locales/` par
`node shared/i18n/sync/sync-web.js`.

Côté back-office, l'interrupteur « Formation » de la fiche entreprise était un
**switch fantôme** : `training` était absent de `Company::KNOWN_MODULES`, donc
`PATCH /platform/companies/{id}/features` reconstruisait `features` sans la clé
et jetait silencieusement toute bascule. Le module est maintenant connu et
enregistré (`config/feature-flags.php`).

À vérifier (recette) :

- Fiche Entreprise › « Modules » : « Centre de Formation » s'affiche depuis le
  catalogue dans les 4 locales (fr/en/ar/tr, RTL compris) — plus de repli en dur.
- Basculer la Formation ON/OFF puis **recharger** : l'état revient conforme (la
  bascule persiste réellement, `GET /platform/companies/{id}/features`).
- Un module laissé « non mentionné » par un client d'API n'est plus éteint par
  surprise (les deux boucles de mise à jour préservent la valeur effective) ;
  depuis le formulaire Blade, un module décoché est bien désactivé (champ caché
  `features[x]=0`).
- `npx eslint src --max-warnings 0` et `npx vite build` restent verts ;
  `check-i18n-diff.js` vert (aucun libellé français en dur sur les lignes ajoutées).
### 19. Paramétrage des offres & métier rattaché à l'entreprise (#7429, #7430)

Deux retours du propriétaire, traités ensemble parce qu'ils touchent la même
navigation admin :

- « les verticales sont liées à **company**, puisque company représente notre
  terrain » — on pouvait ouvrir « Stations-service » sans savoir de quelle
  entreprise on parlait ;
- « tout ce qui relève du paramétrage doit aller » sous Paramètres, et « la
  partie souscription où on est censé être capable de paramétrer nos offres »
  n'était pas paramétrable du tout (table `plans` alimentée par un seeder,
  lecture seule dans l'admin).

**A — Aucune verticale à la racine (#7429)**

- La barre latérale ne propose plus Formations / Flotte / Agence de voyage /
  Stations-service comme entrées globales.
- Ouvrir une entreprise (Portefeuille clients → une entreprise) donne accès à
  l'onglet **« Modules & verticales »**, qui liste les verticales **activées**
  de CE client avec un lien portant le contexte (`?company=<id>`).
- Une verticale sans surface admin (Restauration, Établissement scolaire,
  Caméras) affiche explicitement « aucune surface admin dédiée » — absence
  documentée, jamais un écran vide.
- Les routes `/travel`, `/fleet`, `/fuel-station`, `/training` restent
  déclarées (les écrans et les e2e existants les utilisent) mais ne sont plus
  des entrées de navigation globales.
- Écrans de paramétrage paie (`/settings/payroll/*`) et sondages solutions
  (`/solutions/survey-stats`) : plus aucune route accessible uniquement par
  URL — ils sont rangés dans le menu.

**B — Groupe Paramètres (#7430)**

- Un groupe « Paramètres » regroupe : Offres & tarifs, Abonnements, Assistant
  IA, Modèles d'e-mails, Webhooks, OAuth marketing, paramétrage comptable et
  paramétrage paie. La racine garde l'usage (Chat IA) et la supervision.
- Fiche entreprise → onglet « Modules & verticales » : la **dotation de
  l'entreprise** et les **capacités plateforme (kill switches)** sont deux
  blocs distincts, avec libellé et couleur propres — on ne confond plus un
  interrupteur global avec un droit accordé au client.

**C — Offres & tarifs : CRUD réel**

- « Nouvelle offre » ouvre un formulaire (nom, prix mensuel/annuel, employés
  inclus, jours d'essai, matrice offre × features, publication).
- Une offre se **modifie** (dont son nom, tant qu'il reste unique), se
  **duplique** (la copie naît **archivée** : elle n'apparaît pas dans le tunnel
  de souscription), s'**archive**.
- **Supprimer une offre utilisée par un client est refusé** : l'API répond 409
  et le message affiché oriente vers l'archivage. Aucune suppression sèche.
- Chaque écriture est auditée (`AuditLog`, société nulle : décision plateforme).

À vérifier en recette :

- Les 4 locales (fr/en/ar/tr) rendent les libellés, y compris en RTL.
- `eslint .` et `vite build` verts ; garde `check-admin-action-labels.py` verte.
- Les actions de ligne de l'écran Offres restent des icônes avec nom accessible.

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

## Ecran « Assistant IA » (Parametres) — configuration et suivi

Nouvel ecran `front/admin-dashboard/src/views/settings/AiAssistantView.vue`, route
`/settings/ai` (nom `settings-ai-assistant`), entree de menu « Parametres ».

### Onglet Configuration

- Etat reel en tete de page : actif, driver, modele, cle fournisseur configuree
- Un bloc par groupe du catalogue renvoye par l'API
- Les champs secrets ne sont **jamais pre-remplis** : un champ vide signifie « conserver la
  cle enregistree », rappel explicite affiche sous le champ
- Bouton « Tester la connexion » : le message du fournisseur est affiche tel quel
  (401 / 429 / timeout), jamais un simple « erreur »

### Onglet Suivi

- Indicateurs : requetes, tokens, cout, erreurs, taux d'erreur, p95
- Tableaux par entreprise et par outil, erreurs recentes
- Periode 7 / 30 / 90 jours

### Scenarios de recette

- Etat vide honnete : « aucune activite sur la periode » plutot qu'un zero trompeur
- Une cle enregistree doit afficher « cle enregistree » **sans jamais la reveler**
- Enregistrer un changement de modele **sans** toucher au champ cle ne doit PAS effacer la
  cle (verifier ensuite l'etat « cle enregistree »)
- Les 4 locales (fr/en/ar/tr) doivent rendre l'ecran, y compris le RTL arabe
- `eslint --max-warnings 0` et `vite build` doivent rester verts
## Note de conservation — propagation i18n (PR #7350, Refs #7351, 2026-09-14)

**Aucun scénario de l'admin plateforme n'est modifié.** Le diff touche
`front/admin-dashboard/src/i18n/locales/{fr,en,tr,ar}.json` uniquement parce que
ces fichiers sont **générés** par `shared/i18n/sync/sync-web.js` : trois clés de
l'écran « Mon compte » du **client web** (`settingsPage.company`,
`settingsPage.manage2fa`, `settingsPage.tenantSubtitle`) sont propagées
mécaniquement à tous les targets du catalogue. Aucun écran de l'admin
plateforme ne consomme ces clés ; les scénarios listés ci-dessus restent valides
et inchangés. Même situation que la note mobile du 2026-09-13 (PR #7333).
>

## Note de conservation — acces demo (#7402, 2026-09-14)

Le panneau « ACCES DEMO — CHOISIR UN PROFIL » de `/login` change de **contenu**, pas de parcours.

- Le panneau ne rend plus que les personas de **sa** surface (`surface === 'admin-platform'`). Les personas
  `web-manager` / `kiosk-supervisor` / `mobile-employee` appartiennent aux surfaces web client, kiosque et mobile :
  elles n'apparaissaient ici que pour echouer, puisque `POST /platform/auth/login` ne connait que `super_admins`.
- Le scenario « un clic sur une persona connecte reellement » devient donc **verifiable** : c'est une garde de fumee
  ajoutee cote API (`DemoUserControllerTest::test_demo_users_personas_can_actually_log_into_the_platform`), qui.
  verifie que le mot de passe annonce par `/demo-users` authentifie le super admin seede.
- Aucun autre scenario de cette matrice n'est modifie : navigation, guards, vues critiques et garde-fous a11y
  restent inchanges.

> Les personas des autres surfaces restent visibles depuis **leur** application (web client / kiosque / mobile) ;
> leur suppression ici est un correctif, pas une perte de fonctionnalite.

## Scenario — Liste des entreprises : recherche, filtre, pagination et actions rapides (#7431)

Ecran `front/admin-dashboard/src/views/companies/CompaniesView.vue` (route `/companies`).
Le contrat d'API est **inchange** : `GET /platform/companies` accepte deja `search`, `status`,
`per_page` et pagine via `page` (`PlatformCompanyController::index`). Ce qui change, c'est que la
vue **lit enfin `meta`** et n'est plus limitee a une page demandee en dur (`per_page=100`), donc
plus de troncature silencieuse au-dela de 100 societes.

### Recherche (nom / e-mail / pays / ville)

- La saisie part au **serveur** avec un **debounce de 300 ms** (parametre `search`) : ce sont les
  champs filtres par l'API, pas un filtrage en memoire de la page courante.
- Une recherche **repart page 1** et conserve le filtre de statut en cours.
- Aucun resultat => **etat vide explicite** « Aucune societe ne correspond a cette recherche » +
  bouton de reinitialisation (jamais un tableau blanc).

### Filtre de statut

- `Tous` / `Actif` / `Essai` / `Suspendu` / `Expire` => parametre `status`, filtre **cote serveur**.
- Changer de filtre repart page 1 ; la recherche en cours est conservee.

### Pagination serveur (plus de troncature muette)

- La vue demande une page explicite (`page` + `per_page=25`) et lit `meta` (`current_page`,
  `last_page`, `per_page`, `total`).
- Des que `meta.last_page > 1` : navigation **Precedent / Suivant** + « **Page X sur Y** » + total
  visibles ; `Precedent` desactive en page 1, `Suivant` desactive en derniere page.
- Un portefeuille de plus de 100 societes n'est plus tronque : la derniere page est atteignable.
- Page hors bornes (filtre restrictif) => retour sur la **derniere page existante**, pas de tableau vide.
- Les scores du portefeuille (#7302) ne sont **pas rejoues** a chaque frappe / changement de page :
  ils restent un chargement de fond, reaffiches sur la page courante.
- Arbitrage assume : le tri par score de sante (`sortedItems`) s'applique desormais a la **page
  courante** (l'API ne trie pas par score — elle ordonne par `created_at`) ; le tri global du
  portefeuille n'est plus possible des lors que la pagination est serveur.

### Actions rapides de ligne (sans ouvrir la fiche)

- « **Suspendre** » (statuts `active` / `trial`) : **confirmation obligatoire** (dialogue in-app
  `ConfirmDialog`) ; **annuler n'ecrit rien** (aucun appel API).
- « **Activer** » (statuts `suspended` / `expired`) : action directe, sans confirmation.
- Les deux passent par `RowActionButton` (icone seule + infobulle + nom accessible, convention #7434).
- **Preservation de l'abonnement** : l'action relit `GET /platform/companies/{id}/subscription`
  puis renvoie **tout l'etat** (`plan_id`, `status`, `subscription_start`, `subscription_end`,
  `notes`). A verifier apres une suspension : la **date de fin d'essai** et les **notes** sont
  intactes (le `PATCH` ecrit `null` pour tout champ non envoye).
- Un echec affiche un **toast d'erreur** (jamais d'echec silencieux) et la liste reste utilisable.

### Hors perimetre (assume et documente)

- La **suppression de tenant** n'est pas livree : aucune route `DELETE /platform/companies/{id}`
  n'existe, et la purge des donnees d'un tenant (schema, invitations, journal d'audit) est un
  parcours en deux temps a part entiere. Elle reste a traiter separement.

### Verification

- `e2e/companies-list-quick-actions.spec.js` : recherche / filtre / pagination cote serveur,
  confirmation de suspension, annulation sans ecriture, preservation de
  `subscription_end` + `notes`, activation directe.
- `e2e/companies-progressive-portfolio.spec.js` : la liste reste utilisable avant la fin du
  scoring du portefeuille (contrat de pagination mis a jour).
- `eslint` et `vite build` (avec `VITE_API_URL`) restent verts ; les **4 locales** (fr/en/ar/tr)
  doivent rendre l'ecran, RTL arabe compris.

## Note de conservation — propagation i18n du module Caméras (#7425, 2026-09-15)

**Aucun scénario de l'admin plateforme n'est modifié.** Le diff touche
`front/admin-dashboard/src/i18n/locales/{fr,en,tr,ar}.json` uniquement parce que
ces fichiers sont **générés** par `shared/i18n/sync/sync-web.js` : le nouveau
bloc `cameras.*` (mur de caméras, détail, permissions, jetons tiers, viewer
public) est propagé mécaniquement du catalogue partagé vers tous ses targets.
Aucun écran de l'admin plateforme ne consomme ces clés — le seul affichage
existant reste le toggle de flag « Surveillance Vidéo » de
`CompanyDetailView.vue`, inchangé. Les scénarios listés ci-dessus restent
valides et inchangés. Même situation que la note de conservation de la
propagation i18n du 2026-09-14 (PR #7350).
## Scenario — nommage produit dans le back-office : « Leopardo — suite metier » (#7518, issue #7428)

### Perimetre du changement

- Seule la **copie** du back-office change : le titre applicatif `app.title` passe de
  « Leopardo RH » a « **Leopardo — suite metier** » (en : « Leopardo — Business Suite » ;
  ar : « ليوباردو — حزمة الأعمال » ; tr : « Leopardo — İşletme Yönetimi Paketi »).
- Le namespace partage **`seoRoot`** (5 cles : titre / description canoniques de la racine, du
  manifeste PWA et de l'image OG) arrive dans le dashboard par la **synchronisation** du catalogue
  partage : `front/admin-dashboard/src/i18n/locales/*.json` sont des fichiers **generes**
  (`shared/i18n/sync/sync-web.js`, cible 1, union semantique #3853) — jamais edites a la main.
- **Aucun** changement de composant, de route, d'etat ni de contrat d'API : ni le `vite build`,
  ni les parcours Playwright existants ne sont touches par ce lot.

### Scenario de recette

1. Charger une vue **connectee** du back-office dans chacune des **4 locales** (fr / en / ar / tr,
   RTL arabe compris) : le titre applicatif affiche le libelle « suite metier » localise, et
   **jamais** « logiciel RH » (decision :
   `docs/REFERENTIEL_PRODUIT/POSITIONNEMENT_SUITE_METIER.md`).
2. Verifier que « **Leopardo RH** » reste le **nom d'une application de la suite** (RH & paie) —
   libelle d'app / de module — et non la categorie du produit ; les noms d'ecrans metier
   (Paie, Conges, Portefeuille clients, ...) sont inchanges.
3. Charger la racine et le manifeste PWA : les phrases canoniques proviennent de `seoRoot`
   (une phrase canonique vit dans le **catalogue partage**, non dupliquee par cible).

### Verification

- `eslint` + `vite build` verts ; parite i18n **x4** (`check-i18n-catalog-parity.sh`) verte ;
  synchronisation `I18N_SYNC_WEB_OK` / `I18N_VALIDATION_OK (4 locales)`.
- Garde de derive du nommage : `dev-hub/tools/check-naming-drift.sh` (baseline
  `dev-hub/tools/naming-baseline.json`) — « suite metier » autorise, « logiciel RH » proscrit
  hors baseline de dette gelee.

> Note de conservation (2026-09-16) : ce lot ne modifie **aucun comportement** du back-office. Il
> est consigne ici parce que le **libelle produit** est une surface visible d'administration (donc
> une attente de recette) et parce que la garde de gouvernance
> (`dev-hub/tools/check-governance.ps1`) exige qu'une modification de
> `front/admin-dashboard/src/**` soit accompagnee de la mise a jour de ce fichier **ou** de
> `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md`.
