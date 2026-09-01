# Feature Specification: Vague QA Hardening 2 — Backend & Surfaces Web/Mobile (2026-08-14)

**Feature Branch**: `qa-hardening-wave-2-2026-08-14`

**Created**: 2026-08-14

**Status**: Draft

**Input**: Mission de test de la plateforme (session 2026-08-14, round 2) — audits
fonctionnels parallèles (backend, admin-dashboard, web, mobile) sur `main` (`0feb18a`).
Constats non couverts par la vague 1 (`qa-hardening-wave-2026-08-14`, issues #2175-#2188)
ni par l'intégrité contrats (#2181).

## User Scenarios & Testing

### User Story 1 — Les fonctionnalités SSO et exports bancaires annoncées fonctionnent vraiment (Priority: P1)

Le super-admin configure le SSO SAML/OIDC d'une entreprise puis un employé s'authentifie
via l'IdP : aujourd'hui le callback renvoie 501 (`SSOValidationNotImplementedException`)
alors que les routes publiques et l'OpenAPI présentent le flux comme fonctionnel. De même,
l'export bancaire SEPA (`POST /payroll-runs/{id}/bank-export`) génère un fichier avec un
IBAN/BIC placeholder (`PLACEHOLDER_COMPANY_IBAN`, `NOTPROVIDED`) — inutilisable en banque.

**Pourquoi P1** : une fonctionnalité de sécurité annoncée qui échoue toujours (501) et des
fichiers de virement avec des données fictives sont inacceptables (Constitution §V).

**Test indépendant** : tests Feature `SSOCallbackTest` (SAML + OIDC) verts avec
assertion valide et invalide ; `BankExportTest` vérifie que l'export 422 si IBAN
entreprise absent et utilise les vraies coordonnées si présentes.

**Acceptance Scenarios**:

1. **Given** une config SSO valide (`company_sso_configs`), **When** un callback SAML/OIDC
   arrive, **Then** l'assertion/ID-token est validé (signature, issuer, audience) et
   l'utilisateur est authentifié ou une erreur explicite 4xx est retournée — jamais 501.
2. **Given** une entreprise sans IBAN/BIC, **When** on génère un export SEPA, **Then** 422
   explicite (pas de placeholder dans le fichier).
3. **Given** une entreprise avec IBAN/BIC, **When** on génère l'export, **Then** le
   debtor account porte les vraies coordonnées de l'entreprise.

### User Story 2 — Les workflows backend restants sont réels, pas des stubs (Priority: P1)

Le cockpit admin affiche l'historique des exports (`GET /export/history`) : aujourd'hui
`ExportController::history` renvoie `['data' => []]` en dur. Les notifications in-app
créées par `NotificationDispatcher` ne déclenchent jamais de push FCM alors que
`PushNotificationService` existe. Le fichier `routes/modules/notification.php` est un
no-op mort et `routes/modules/payroll_engine.php` déclare deux fois la même route.

**Pourquoi P1** : des endpoints qui répondent vide sans erreur masquent des workflows
cassés ; un push jamais envoyé rend les notifications inutiles pour l'employé terrain.

**Test indépendant** : `ExportHistoryTest` retourne les vraies lignes (audit ou table
exports) ; `NotificationPushDispatchTest` vérifie l'appel à `PushNotificationService`
après création de la notification.

**Acceptance Scenarios**:

1. **Given** des exports passés, **When** on appelle `GET /export/history`, **Then** la
   liste reflète les exports réels (au minimum les métadonnées auditées), jamais vide en
   dur.
2. **Given** une notification in-app créée pour un employé avec device token, **When**
   `NotificationDispatcher::dispatch()` s'exécute, **Then** un push FCM est tenté via
   `PushNotificationService` (best-effort, erreur push non bloquante).
3. **Given** le fichier mort `routes/modules/notification.php`, **When** on audite les
   routes, **Then** le fichier et son `require` dans `api.php` sont supprimés.
4. **Given** la route dupliquée payment-documents, **When** on audite les routes, **Then**
   un seul chemin canonique subsiste (rétro-compat préservée par alias si nécessaire).

### User Story 3 — La vitrine web n'a plus de page morte ni de promesse non tenue (Priority: P1)

Le visiteur ouvre `/videos` et clique Play : aucun lecteur n'est monté, les IDs YouTube
sont factices, les thumbnails n'existent pas. `/mobile` propose des boutons de
téléchargement vers des ancres `#android-*`/`#ios-*` inexistantes. `/download` affiche
« Télécharger pour Windows » sans installateur. Les cartes « case study » mènent vers
`/case-studies/{slug}` qui n'existe pas (404). Le bouton PDF d'un contrat dashboard
appelle `/contracts/{id}/pdf` inexistant (le backend sert `/generate-pdf`). Le checkout
plan gratuit affiche « Compte créé ! » même si l'API a échoué.

**Pourquoi P1** : chaque lien mort/CTA trompeur fait perdre un prospect ou casse un
parcours client ; la Constitution exige des liens réels (AGENTS.md « jamais un lien mort »).

**Test indépendant** : Playwright web (`web/e2e/*`) : `/videos` n'affiche plus de lecteur
factice ; `/mobile` et `/download` n'ont plus d'ancre morte ni de CTA sans livrable ;
les cartes case study pointent vers une route existante ; le bouton PDF contrat appelle
`/generate-pdf` ; checkout ne redirige qu'en cas de succès réel.

**Acceptance Scenarios**:

1. **Given** la page `/videos`, **When** elle se charge, **Then** soit un lecteur réel
   (fichier vidéo présent dans `public/videos/` ou ID YouTube valide) soit un état
   « bientôt disponible » honnête — jamais un bouton Play qui affiche « chargement » à
   l'infini.
2. **Given** la page `/mobile`, **When** on clique un bouton de téléchargement, **Then**
   le lien pointe vers `/signup?source=download_<slug>_<platform>` ou un vrai lien
   store/Firebase — jamais une ancre morte.
3. **Given** la page `/download`, **When** on clique le CTA principal, **Then** il
   pointe vers un livrable réel (installateur/fichier présent) ou le libellé devient
   honnête (ex. « Être contacté »).
4. **Given** une carte case study, **When** on clique « Lire le cas d'usage », **Then**
   la cible existe (créer `case-studies/[slug]` ou pointer vers l'index).
5. **Given** le dashboard web, **When** on télécharge le PDF d'un contrat, **Then**
   l'appel passe par `/contracts/{id}/generate-pdf` (endpoint réel).
6. **Given** le checkout plan gratuit, **When** le signup API échoue, **Then** l'erreur
   est affichée et l'utilisateur n'est pas redirigé vers login avec un faux succès.

### User Story 4 — Le mobile RH/Manager ne crashe plus sur les quick actions (Priority: P1)

L'employé RH ouvre l'accueil et tape une quick action (« Employés », « Vue équipe »,
« Invitations ») : le manifeste backend (`MobileExperienceService`) sert des routes
(`/hr/employees`, `/hr/team-overview`, `/invitations`, `/company/team-roles`,
`/dashboard/admin`) absentes des routeurs GoRouter des apps → crash GoRouter au tap.

**Pourquoi P1** : crash dès l'accueil pour le profil RH/Manager = app inutilisable pour
ces rôles.

**Test indépendant** : `dev-hub/tools/validate-mobile-workflow-contracts.ps1` vert avec
le manifeste aligné ; script de contrôle manifeste→routeurs (toute route du manifeste
existe dans le GoRouter de l'app cible).

**Acceptance Scenarios**:

1. **Given** le manifeste `MobileExperienceService`, **When** un module/action est servi
   avec une `route:`, **Then** cette route existe dans le GoRouter de l'app cible
   (garde CI), sinon le module est servi sans route ou avec une route existante.
2. **Given** l'accueil de `leopardo_hr`, **When** on tape « Employés »/« Vue équipe »/
   « Invitations », **Then** l'écran correspondant s'ouvre (brancher sur les écrans
   existants `/team`, `/modules/rh`, `/organigramme`… ou créer les écrans) — jamais de
   crash.
3. **Given** l'accueil de `leopardo_manager`, **When** on tape les quick actions
   concernées, **Then** même garantie (routes existantes uniquement).

### User Story 5 — Propreté web et mobile : pas de placeholder ni de dead code (Priority: P2)

Pages web avec ancres mortes (`/docs#webhooks-security`, `/docs#security`,
`/docs#mobile-install`), helpers `lib/forms.ts` vers endpoints inexistants
(`/api/analytics/track`, `/api/csrf-token`), module `lib/constants.ts` mort avec 7 liens
footer 404, dead code (`dynamic-imports.tsx`, `SkeletonLoader`, `DemoForm`,
`ContactForm`, export dupliqué `HeroSection`), route `/api/downloads` jamais appelée,
FAQ incohérente avec le pricing, mojibake `OnboardingWizard` + flag `onboarding_completed`
écrit via `PATCH /company/branding`. Côté mobile : écran Voice IA = placeholder envoyant
`Uint8List(0)` à l'API, route `/manager/dashboard` → écran « emplacement réservé »,
`onTap` noop, TODO signature manifeste, route morte
`/smart-attendance/background-permission`.

**Pourquoi P2** : la Constitution §VII exige un repo propre ; les placeholders trompent
les testeurs.

**Test indépendant** : `rg` des patterns interdits → 0 ; lint/build web verts ;
Playwright vérifie les ancres corrigées.

**Acceptance Scenarios**:

1. **Given** les ancres mortes `/docs#*`, **When** on clique, **Then** elles pointent
   vers des ids existants ou sont retirées.
2. **Given** les helpers/liens vers endpoints inexistants, **When** on audite, **Then**
   ils sont supprimés ou branchés sur des routes réelles.
3. **Given** `OnboardingWizard`, **When** il se termine, **Then** la progression passe
   par `/onboarding-setup/*` (endpoint dédié) et la chaîne mojibake est corrigée.
4. **Given** les écrans mobiles placeholder, **When** on les ouvre, **Then** ils
   affichent un état explicite (feature en préparation) sans envoyer de payload vide à
   l'API, ou sont retirés du routeur.

## Edge Cases

- SSO : callback avec assertion invalide → 4xx explicite ; config SSO absente → 404/422 ;
  aucune fuite d'erreur interne (pas de 500).
- SEPA : entreprise sans IBAN → 422 `MISSING_COMPANY_IBAN` ; l'ancien format
  `sepa_xml` reste accepté.
- FCM push : erreur push ne bloque pas la création de la notification in-app (best-effort).
- Web : le checkout conserve les comportements existants pour les plans payants.
- Mobile : les écrans existants (`/team`, `/schedules`, `/organigramme`, `/tasks`) sont
  réutilisés quand possible ; les routes du manifeste sans écran sont soit branchées,
  soit servies sans `route` (module inactif honnête).
