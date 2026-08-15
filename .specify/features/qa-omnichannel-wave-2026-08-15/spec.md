# Feature Specification: Vague QA Omnichannel — Vitrine, Admin, Mobile, API, Ops (2026-08-15)

**Feature Branch**: `qa-omnichannel-wave-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test exhaustif de la plateforme (session 2026-08-15) — audits
statiques 4 surfaces (web, admin, mobile, API) + tests runtime (build vitrine locale,
suite backend locale, API Render live, admin Pages.dev live) sur `main` (`80c034ff`).
Constats dans `findings-registry.md` (36 findings, dont 11 P1). Les manquements déjà
speccés (vague 2 SSO/SEPA/export/push, openapi-wave) et les issues ouvertes ne sont
pas dupliqués ici.

## User Scenarios & Testing

### User Story 1 — La vitrine web n'émet plus aucune URL morte ni promesse non tenue (Priority: P1)

Le sitemap public émet 10 URLs `/blog/*` alors que le blog est désactivé (`/blog` → 404) ;
toutes les images de partage social (`ogImage`) pointent vers `/og/<page>.png` inexistants ;
la page docs porte 3 ancres mortes ; la home du dashboard web pointe vers `/dashboard/reports`
(route inexistante) ; le service worker precache des routes inexistantes et son installation
échoue ; la page edge-nodes appelle `/edge` inexistant ; les canonicaux sont hardcodés sur un
domaine tiers.

**Pourquoi P1** : un sitemap avec 404 pénalise le SEO, des ogImages 404 cassent chaque
partage, un service worker qui ne s'installe pas casse l'offline (Constitution §X, mobile-first).

**Test indépendant** : build Next.js vert ; assertions sur `sitemap.ts` (blog exclu quand
désactivé) ; présence de `public/og-image.png` et zéro référence `/og/<page>.png` ;
ancres docs → ids existants ; lien dashboard → `/reports` ; `sw.js` ne precache que des
routes existantes ; edge-nodes → `/platform/edge/nodes` ; canonicaux = `NEXT_PUBLIC_SITE_URL`.

**Acceptance Scenarios**:

1. **Given** `NEXT_PUBLIC_ENABLE_BLOG=false`, **When** on génère `/sitemap.xml`, **Then**
   aucune URL `/blog/*` n'apparaît (et la variable `enableBlog` est réellement utilisée).
2. **Given** une page de la vitrine, **When** on la partage sur un réseau social, **Then**
   l'ogImage résolue retourne 200 (fichier réel dans `public/`).
3. **Given** la page docs, **When** on clique chaque ancre du sommaire, **Then** un élément
   `id` correspondant existe dans la page.
4. **Given** un utilisateur connecté au dashboard web, **When** il clique la carte
   « Rapports », **Then** il arrive sur `/reports` (200), jamais 404.
5. **Given** la PWA, **When** le service worker s'installe, **Then** tous les assets du
   precache existent (`cache.addAll` ne rejette pas).
6. **Given** la page edge-nodes, **When** elle charge, **Then** elle appelle
   `/platform/edge/nodes` (contrat backend réel).
7. **Given** n'importe quelle page vitrine, **When** on inspecte son canonical, **Then**
   il provient de `NEXT_PUBLIC_SITE_URL` (aucun domaine en dur).

### User Story 2 — La console super-admin ne crashe plus et n'a plus de boutons morts (Priority: P1)

Le header de l'admin plante dès que `/admin/dashboard/alerts` répond (enveloppe `{data:[…]}`
non déballée → `TypeError: filter is not a function`) ; l'impersonation poste vers
`/admin/impersonations` (404 ; le backend sert `/platform/impersonations`) ; le bouton
« alertes critiques » n'affiche aucun panneau ; la recherche globale ne fait que logger ;
le globe affiche des données codées en dur comme « activité temps réel ».

**Pourquoi P1** : un crash du header rend la console inutilisable ; l'impersonation est une
feature de sécurité annoncée (#2518) qui ne fonctionne jamais.

**Test indépendant** : tests unitaires du store (réponse enveloppée → `criticalAlerts`
filtrable) ; vérification statique du contrat d'URL (`/platform/impersonations`) ;
tests composants Header (clic alertes → panneau rendu) ; zéro `console.log` de recherche.

**Acceptance Scenarios**:

1. **Given** une réponse API `/admin/dashboard/alerts` avec enveloppe `{data:[…]}`,
   **When** le store charge le dashboard, **Then** `criticalAlerts` contient les alertes
   critiques (aucun TypeError, aucun crash header/sidebar).
2. **Given** un super-admin, **When** il lance une impersonation depuis la liste des
   utilisateurs, **Then** la requête part vers `POST /platform/impersonations` et aboutit.
3. **Given** le header, **When** on clique « alertes critiques », **Then** un panneau
   listant les alertes réelles s'affiche.
4. **Given** la recherche globale, **When** on tape une requête, **Then** elle filtre la
   navigation (routes accessibles) au lieu d'un `console.log`.
5. **Given** l'écran système, **When** il charge, **Then** les sections utilisent les
   endpoints réels existants (`/platform/observability/*`) — plus de « Non disponible » en dur.
6. **Given** l'écran des congés/fiscalité, **When** il liste les pays, **Then** tous les
   écrans consomment la même source (`GET /supported-countries`), cohérente.

### User Story 3 — Les apps mobiles ne crashent plus et l'onboarding fonctionne (Priority: P1)

Le manager crashe en ouvrant un dossier du Placard (`/cabinet/folder/{id}` vs
`/cabinet/:folderId`) ; l'onboarding Employee/HR poste `POST /onboarding-setup/{id}/complete`
(405+404+403 : le backend attend `PATCH …/{stepKey}/complete` et `api.manager`) ;
les apps Manager et HR bouclent en redirection infinie pour un employé non-manager/non-RH
authentifié (GoRouter `Infinite redirect` → crash).

**Pourquoi P1** : ce sont des crashs certains en conditions réelles (constitution : mobile
employee = app prioritaire, builds toujours verts).

**Test indépendant** : tests Dart des routeurs (redirect resolve pour chaque rôle) ;
tests de contrat onboarding (méthode + stepKey conformes backend) ; test widget
navigation Placard.

**Acceptance Scenarios**:

1. **Given** un dossier dans le Placard du manager, **When** on clique, **Then** la
   navigation utilise `/cabinet/:folderId` (route déclarée) — aucun `No route found`.
2. **Given** l'écran onboarding Employee, **When** on complète une étape, **Then** la
   requête est `PATCH /onboarding-setup/{stepKey}/complete` (clé string) avec une
   autorisation employé (pas 403/405).
3. **Given** un employé authentifié sans rôle manager dans l'app Manager, **When** il se
   connecte, **Then** il voit un écran explicite (403/redirection contrôlée) — aucune boucle.
4. **Given** l'app Employee, **When** l'onboarding est disponible, **Then** la route
   `/onboarding` est atteignable depuis l'UI (pas seulement déclarée).
5. **Given** le service `MobileExperienceService`, **When** l'employé utilise l'app,
   **Then** la source de vérité du stage est écrite par un vrai mécanisme
   (`app_actions_count` alimenté ou remplacé) — plus de stage `new` permanent par design.

### User Story 4 — Les endpoints API annoncés sont réels et les erreurs visibles (Priority: P2)

8 outils IA sont annoncés (`GET /ai/tools`) mais échouent systématiquement (« registered but
not yet implemented ») ; `POST /admin/ai/chat` répond 200 avec un message codé en dur ;
des `catch (\Throwable)` avalent les erreurs DB en listes vides sans log ; la route
`POST /webhooks/{webhookEndpoint}/test` est déclarée deux fois ; les routes Growth et les
callbacks SSO publics n'ont pas de throttle.

**Pourquoi P2** : pas de crash mais des promesses non tenues et une observabilité
dégradée pour le super-admin.

**Test indépendant** : tests Feature pour chaque correctif (outils IA, chat admin,
routes uniques, throttles présents) ; grep anti-`catch (\Throwable)` silencieux.

**Acceptance Scenarios**:

1. **Given** la liste des outils IA, **When** on invoque chaque outil annoncé, **Then**
   il répond avec des données réelles ou est retiré de la liste — jamais « not yet
   implemented ».
2. **Given** `POST /admin/ai/chat`, **When** un super-admin envoie un message, **Then**
   la réponse est un traitement réel ou une erreur explicite — jamais un 200 factice.
3. **Given** une erreur DB sur les listes super-admin, **When** l'endpoint échoue, **Then**
   l'erreur est loggée et retournée (4xx/5xx) — jamais `{data:[]}` silencieux.
4. **Given** l'audit des routes, **When** on liste les routes webhook test, **Then** une
   seule déclaration subsiste.
5. **Given** les routes publiques SSO/Growth, **When** on les audite, **Then** elles
   portent un throttle adapté et une validation d'URI cohérente.

### User Story 5 — Les déploiements live reflètent main (Priority: P1)

L'API Render sert v4.23.5 (main est au-delà) : `/i18n/catalog/fr` → 500,
`/supported-countries` → 404, `/admin/dashboard/*` et `/admin/impersonations` → 404.
La vitrine Vercel sert un build qui précède #2281 (sitemap avec slugs blog disparus,
`/case-studies/{slug}` → 404).

**Pourquoi P1** : les surfaces publiques et l'admin affichent des erreurs qui n'existent
plus dans main ; chaque prospect/admin voit une plateforme cassée.

**Test indépendant** : curl des endpoints live après déploiement (200/404 attendus) ;
comparaison sitemap live vs build main.

**Acceptance Scenarios**:

1. **Given** le déploiement Render, **When** on appelle `/api/v1/health`, **Then** la
   version servie est ≥ celle de main et `/supported-countries`/`/i18n/catalog/fr` → 200.
2. **Given** le déploiement Vercel, **When** on parcourt `/case-studies/{slug}` et le
   sitemap, **Then** aucune URL 404 (hors blog désactivé volontairement).
3. **Given** la console admin, **When** on clique « Accès Démo », **Then** le bouton n'est
   affiché que si la démo est activée (message explicite sinon).

## Success Criteria

- Sitemap vitrine : 0 URL 404 (vérifié par crawl).
- ogImages : 100 % des pages ont une ogImage résolvable (200).
- Header admin : 0 crash JavaScript sur les 3 écrans principaux (dashboard, users, system).
- Impersonation admin : fonctionne de bout en bout.
- Apps mobiles : 0 crash de navigation sur les parcours Placard, onboarding, login
  non-manager (tests Dart/widget).
- Outils IA : 0 outil annoncé non implémenté.
- Erreurs super-admin : aucune liste vide silencieuse (log + erreur explicite).
- API live : version déployée ≥ main, endpoints récents 200.

## Key Entities

- Vitrine : `sitemap.ts`, `lib/seo.ts`, `public/`, `sw.js`, `(landing)/docs`, `(dashboard)/*`.
- Admin : `stores/dashboard.js`, `router/index.js`, `views/`, `components/layout/Header.vue`.
- Mobile : routeurs GoRouter (3 apps), `onboarding_repository.dart` (3 apps),
  `MobileExperienceService.php`.
- API : `AIToolRegistrySeeder.php`, `IntentEngine.php`,
  `PlatformAdminAiConversationController.php`, `routes/modules/{growth,sso,hr_extended}.php`.
- Ops : déploiements Render/Vercel/Pages.

## Assumptions

- Les fixes Flutter sont écrits et vérifiés statiquement (pas de SDK Flutter dans la sandbox) ;
  la validation CI mobile reste la porte finale.
- Les tasks de la vague 2 (SSO SAML 501, SEPA, export history, push) restent en attente
  d'implémentation (hors périmètre de cette spec).
- Les issues ouvertes payroll/CI (#2590, #2587, #2586, #2583, #2580) sont hors périmètre.
- Les déploiements live nécessitent un accès Render/Vercel hors sandbox : l'implémentation
  est livrée sur main, le déploiement est à la charge de l'équipe (issue dédiée).
