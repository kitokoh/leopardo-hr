# Feature Specification: QA Audit Expert 2026-08-15 — manquements nouveaux

**Feature Branch**: `docs/qa-audit-expert-2026-08-15`
**Created**: 2026-08-15
**Status**: Spec → Tasks → Issues → Implémentation
**Input**: Mission utilisateur — tester toutes les surfaces (vitrine, web, admin, mobiles,
workflows, APIs, logiques, onboarding, cohérence) ; tout manquement → spec + tasks +
issues (méthode Spec Kit) ; implémenter ensuite.

## Contexte

Campagne de test experte du 2026-08-15 sur le repo kitokoh/leopardo-hr. Les constats
complets (preuves fichier:ligne, vérifications runtime) sont dans
[findings-registry.md](./findings-registry.md). La campagne QA omnichannel du même jour
couvre déjà ~90 issues (#2646→#2813) ; cette spec ne porte que sur les manquements
**nouveaux** (F-01→F-09) et sur l'implémentation du P0 #2652 (login 500), non assigné.

## User Stories & Testing

### User Story 1 — Le login API ne renvoie plus jamais 500 sur un compte existant (P0)

Un employé (ou un compte démo) dont l'environnement a perdu son schéma tenant ou dont le
lookup pointe vers un schéma absent obtient une **erreur propre et explicite** (401
identifiants invalides ou message métier clair), jamais un 500 « Server Error ».

**Independent Test**: `POST /api/v1/auth/login` avec un email dont le
`public.user_lookups.schema_name` pointe vers un schéma inexistant → 401 JSON propre
(et non 500) ; le flux nominal (schéma existant) reste inchangé (200 + token).

**Acceptance Scenarios**:
1. **Given** un lookup `user_lookups` pointant vers un schéma sans table `employees`,
   **When** login avec ces identifiants, **Then** réponse 401 structurée (pas de 500).
2. **Given** un compte valide sur un schéma sain, **When** login, **Then** 200 + token
   (aucune régression).
3. **Given** un compte verrouillé/suspendu, **When** login, **Then** le comportement
   existant (423/403 métier) est préservé.

---

### User Story 2 — Le CHANGELOG respecte sa propre garde de gouvernance (P1)

Un contributeur qui ouvre une PR voit la CI Governance verte : plus de headers de section
dupliqués (`### Added` ×2, `### Fixed` ×5) sous `[Unreleased]`.

**Independent Test**: la commande de garde `check-governance.ps1` (ou le scan équivalent
des headers) passe sur `CHANGELOG.md` ; `git diff` ne montre que la réorganisation des
headers, aucun contenu supprimé.

**Acceptance Scenarios**:
1. **Given** le CHANGELOG courant, **When** on vérifie la structure `[Unreleased]`,
   **Then** chaque catégorie (`Added`/`Changed`/`Fixed`/`Removed`) apparaît une seule fois,
   regroupant toutes les entrées.
2. **Given** la garde maison, **When** on la lance, **Then** elle passe sans erreur.

---

### User Story 3 — Les alertes plateforme admin sont lisibles (P2)

Un super-admin ouvre Analytics → « Alertes plateforme » : chaque alerte affiche son texte
(`message` du backend), pas un bloc vide. Les actions « Ignorer » restent fonctionnelles.

**Independent Test**: `AnalyticsView.vue` mappe `message` (et non `title`/`description`) ;
le rendu avec le payload réel `/admin/dashboard/alerts` montre le texte de l'alerte.

**Acceptance Scenarios**:
1. **Given** une alerte critique renvoyée par l'API, **When** AnalyticsView la rend,
   **Then** le texte de l'alerte est visible avec son niveau (critical/warning/info).
2. **Given** aucune alerte, **When** rendu, **Then** l'état vide « Aucune alerte active »
   est conservé.

---

### User Story 4 — L'attribution des leads vitrine est conservée de bout en bout (P2)

Un prospect cliqué depuis `/download` (ou un guide) avec `source=download_*` arrive sur
`/signup` et soumet le formulaire : la soumission `/api/forms/signup` transmet la même
`source` (et non `signup_form` codée en dur).

**Independent Test**: navigation vers `/signup?source=download_employee_android` +
soumission → payload envoyé avec `source=download_employee_android`.

**Acceptance Scenarios**:
1. **Given** `/signup?source=download_employee_android`, **When** soumission,
   **Then** le payload contient `source: 'download_employee_android'`.
2. **Given** `/signup` sans paramètre, **When** soumission, **Then** `source: 'signup_form'`
   (défaut conservé).

---

### User Story 5 — Assainissements rapides (P3) — hygiène web/mobile/admin

Un développeur peut builder et tester sans fragilités : `zod` déclaré dans `package.json`,
`/api/forms/verify` derrière la même gate que les autres routes forms, `CompanyDetailView`
affiche les erreurs API, `leopardo_hr` initialise Sentry comme les autres apps, et les
specs e2e web reflètent le formulaire sans mot de passe.

**Independent Test**: `npm run build` vitrine vert (zod résolu) ; gate forms homogène ;
lint admin vert ; grep Sentry sur `leopardo_hr` non vide ; specs e2e à jour.

**Acceptance Scenarios**:
1. **Given** package.json, **When** `npm ls zod`, **Then** `zod` est présent et déclaré.
2. **Given** `/api/forms/verify`, **When** la gate `areFormsEnabled()` est fermée,
   **Then** l'endpoint répond comme les autres routes forms (état désactivé).
3. **Given** une erreur API sur la fiche entreprise, **When** chargement, **Then** un
   message d'erreur visible s'affiche (toast), pas seulement `console.error`.
4. **Given** `leopardo_hr`, **When** démarrage, **Then** Sentry est initialisé
   (même pattern que `leopardo_employee`).
5. **Given** les specs e2e vitrine, **When** rejouées, **Then** elles correspondent au
   formulaire courant (sans champ mot de passe).

## Functional Requirements

- FR-1 (US1) : `AuthService::login()` ne lève jamais de `QueryException` sur la résolution
  d'employé : toute erreur de résolution (table absente, schéma absent) → pas d'employé →
  `InvalidCredentialsException` (401) ou erreur métier documentée.
- FR-2 (US1) : le chemin nominal (schéma sain) est inchangé ; les abilities tenant,
  verrouillage et statuts restent appliqués.
- FR-3 (US1) : un test de régression couvre le cas lookup→schéma absent (401, pas 500).
- FR-4 (US2) : `CHANGELOG.md` restructuré : une seule occurrence de chaque header de
  catégorie sous `[Unreleased]`, toutes les entrées conservées.
- FR-5 (US3) : `AnalyticsView.vue` lit `alert.message` (avec fallback `title` pour
  compatibilité) ; les clés `id`/`level` inchangées.
- FR-6 (US4) : `SignupForm` propage `source` depuis la query string (défaut `signup_form`).
- FR-7 (US5) : `zod` ajouté aux dépendances de `front/web` ; `api/forms/verify` branché sur
  `areFormsEnabled()` ; toasts d'erreur dans `CompanyDetailView` ; `SentryFlutter.init`
  dans `leopardo_hr` ; specs e2e `conversion-funnel` + `forms-and-submissions` réalignées.

## Success Criteria

- 0 cas de login 500 sur compte existant (probe : login démo → 401 propre) — US1.
- `check-governance.ps1` vert sur le CHANGELOG — US2.
- Alerte plateforme rendue avec texte visible sur le payload réel — US3.
- `source=download_*` présent dans le payload signup quand présent dans l'URL — US4.
- Builds vitrine/admin verts, Sentry présent dans les 4 apps, specs e2e réalignées — US5.

## Key Entities

- `AuthService` (login), `public.user_lookups`, `CHANGELOG.md`, `AnalyticsView.vue`,
  `SignupForm.tsx`, `package.json` (front/web), `lead-capture.ts`, `CompanyDetailView.vue`,
  `main.dart` (leopardo_hr), specs e2e web.

## Assumptions

- Les déploiements Render/Vercel (#2812/#2813) sont hors périmètre d'implémentation de
  cette vague (traités en issues ops).
- Les constats déjà couverts par la vague omnichannel ne sont PAS ré-implémentés ici
  (voir registre §C).
- La vérification runtime du login (prod) restera 500 tant que le déploiement #2812 n'est
  pas fait ; la validation du fix se fait par tests locaux + le futur déploiement.
