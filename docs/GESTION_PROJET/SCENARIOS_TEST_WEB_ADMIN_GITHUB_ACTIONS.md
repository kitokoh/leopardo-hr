# SCENARIOS DE TEST WEB ADMIN POUR GITHUB ACTIONS

## Objectif

Donner une base de scenarios stable pour le dashboard `admin-dashboard/`, avec une execution automatique dans Playwright et des artefacts exploitables en CI.

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

- la page d'accueil charge une synthese via `/api/v1/platform/companies/health`, `/api/v1/platform/plans` et `/api/v1/platform/company-requests?status=pending`
- les priorites clients, MRR, ARPA, adoption terrain et demandes entrantes remplacent les anciens widgets mockes
- la vue Entreprises charge le portefeuille via `/api/v1/platform/companies/health`
- le detail Entreprise charge health client, abonnement et catalogue plans
- le formulaire abonnement met a jour plan, statut, dates et notes sans hardcoder les `plan_id`
- la vue Abonnements affiche le catalogue `/api/v1/platform/plans`, le MRR portefeuille et les clients prioritaires
- les etats loading/error restent lisibles si l'API plateforme est indisponible

### 7. Intake demandes clients

- la vue Support charge les demandes via `/api/v1/platform/company-requests`
- les filtres pending/approved/rejected mettent a jour la file de qualification
- les compteurs statut restent visibles pour suivre le pipe commercial
- les actions approuver/rejeter envoient `PATCH /api/v1/platform/company-requests/{id}` avec notes internes
- une demande deja traitee ne propose plus d'action de decision

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

### 8. Locales, dictionnaires et direction

- Les dictionnaires generes dans `admin-dashboard/src/i18n/locales/` restent synchronises avec `shared/i18n/locales/`
- Une locale variante (`fr-CA`, `en-GB`, `ar-SA`) est normalisee sans casser le rendu
- La direction `rtl` est resolue correctement pour l'arabe
- Aucun import ou helper i18n ne doit casser le build quand la surface web change avec `shared/i18n/**`
