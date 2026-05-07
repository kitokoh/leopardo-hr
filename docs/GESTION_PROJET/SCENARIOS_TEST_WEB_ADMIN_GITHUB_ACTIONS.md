# SCENARIOS DE TEST WEB ADMIN POUR GITHUB ACTIONS

## Objectif

Donner une base de scenarios stable pour le dashboard `admin-dashboard/`, avec une execution automatique dans Playwright et des artefacts exploitables en CI.

## Perimetre

- auth admin,
- navigation protegee,
- rendu des vues critiques,
- garde-fous UX/a11y,
- non-regression sur les parcours administratifs prioritaires.

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
- indicateurs de chargement announces
- contrastes et focus visibles sur les parcours critiques
- listes critiques lisibles au clavier et par lecteur d'ecran

### 5. Regressions de formulaires

- validation d'un formulaire de connexion
- prevention du double submit sur action critique
- messages d'erreur stables

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
- NO GO: echec auth critique, navigation protegee cassée, rendering blank, ou artefacts d'echec manquants

## Extension i18n enterprise

### 6. Locales, dictionnaires et direction

- Les dictionnaires generes dans dmin-dashboard/src/i18n/locales/ restent synchronises avec shared/i18n/locales/.
- Une locale variante (r-CA, en-GB, r-SA) est normalisee sans casser le rendu.
- La direction tl est resolue correctement pour l'arabe.
- Aucun import ou helper i18n ne doit casser le build quand la surface web change avec shared/i18n/**.
