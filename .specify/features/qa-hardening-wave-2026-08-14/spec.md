# Feature Specification: Vague QA & Durcissement Plateforme 2026-08-14

**Feature Branch**: `qa-hardening-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Draft → Implementation

**Input**: Mission de test de la plateforme (workflows API, vues, boutons, logique) — session agent 2026-08-14. Constats issus de : reconnaissance statique (routes/controllers, frontends, mobile), builds frontend, suite de tests backend, smoke tests API.

## Contexte

La plateforme Leopardo RH (Laravel 12 / PHP 8.4 + Vue 3 + Next.js + Flutter) a fait l'objet d'une campagne de test complète. Cette vague regroupe les manquements fonctionnels constatés et leur correction, en respectant la Constitution (spec-first, multi-tenant inviolable, PHPStan strict vert, CHANGELOG obligatoire).

**Constat global** : 0 route cassée (655 références routes→controllers vérifiées), 0 import cassé côté web/admin, i18n cohérent. Les manquements sont concentrés sur : (a) des interactions UI orphelines (boutons décoratifs sans handler), (b) des stubs backend, (c) un module `user` sans aucun test, (d) des violations de patterns interdits côté mobile.

## User Scenarios & Testing

### User Story 1 - Backend : workflows API vérifiés et propres (Priority: P1)

Un utilisateur (employé, RH, manager, super-admin) appelle n'importe quel workflow API du produit et obtient une réponse correcte et testée : la suite de tests backend complète est verte, PHPStan strict (niveau 8) est vert, Pint est vert.

**Why this priority**: le backend est le cœur de la plateforme (369 chemins OpenAPI, 21 modules). Une suite verte est la preuve fonctionnelle de base.

**Independent Test**: `php artisan test` (Unit + Feature) vert ; `vendor/bin/phpstan analyse --configuration phpstan-strict.neon` = `[OK] No errors` ; `vendor/bin/pint --test` = OK.

**Acceptance Scenarios**:

1. **Given** le dépôt à jour, **When** on exécute la suite complète, **Then** 100 % des tests passent (0 échec, 0 erreur).
2. **Given** un module avec routes exposées, **When** on vérifie la couverture Feature, **Then** chaque module a au moins un test (le module `user` passe de 0 à ≥ 1 test Feature).
3. **Given** un export bancaire généré, **When** on inspecte le XML, **Then** aucun placeholder `PLACEHOLDER_*` n'apparaît (IBAN/BIC réels du tenant ou refus explicite si absents).
4. **Given** deux conventions de routes notifications (`PUT /notifications/read-all` vs `POST /notifications/mark-all-read`), **When** on consolide, **Then** une seule convention est documentée, l'autre reste en alias de compatibilité.

### User Story 2 - Web App : vues et boutons fonctionnels (Priority: P1)

Un RH qui ouvre la Web App (Next.js) voit un tableau de bord et des pages métier où **chaque bouton déclenche une action réelle** : recherche, notifications, activités, Leo IA, actions rapides, détail de bulletin.

**Why this priority**: des boutons décoratifs sont un manquement fonctionnel visible immédiatement par l'utilisateur.

**Independent Test**: revue de code (aucun `<button>` sans `onClick`/`Link` dans les pages dashboard et payroll) + build Next.js vert + e2e navigation existantes vertes.

**Acceptance Scenarios**:

1. **Given** le dashboard, **When** on clique sur « Voir toute l'activité » / la cloche / Leo IA / les actions rapides, **Then** une navigation ou une action observable se produit (pas de no-op).
2. **Given** la liste des bulletins, **When** on clique sur l'œil « détail », **Then** le détail du bulletin s'affiche (modal ou page).
3. **Given** le champ « Rechercher... », **When** on tape, **Then** une recherche réelle (filtrage client des cartes ou navigation vers une page de recherche) se produit.

### User Story 3 - Admin Dashboard : vues et boutons fonctionnels (Priority: P1)

Un super-admin utilise le dashboard Vue et chaque bouton déclenche une action réelle : widgets analytics, accès super-console, gestion partenaire, changement d'avatar.

**Why this priority**: même logique que US2, surface d'administration = promesse commerciale de la plateforme.

**Independent Test**: revue de code (aucun `@click` manquant sur boutons cliquables) + build Vite vert + lint ESLint vert.

**Acceptance Scenarios**:

1. **Given** la vue Analytics, **When** on clique sur les actions des widgets (churn, forecast, feature adoption), **Then** les événements sont écoutés et déclenchent navigation/export.
2. **Given** la fiche entreprise, **When** on clique « Accès Super-Console », **Then** navigation vers la console super-admin (ou handler explicite).
3. **Given** la ligne partenaire Growth, **When** on clique « Gérer », **Then** navigation vers la gestion du partenaire.
4. **Given** la modale utilisateur, **When** on clique « Changer l'avatar », **Then** un sélecteur de fichier s'ouvre ou le bouton est explicitement désactivé avec un libellé (pas de no-op silencieux).

### User Story 4 - Mobile : conformité patterns et compilabilité (Priority: P2)

Les apps Flutter respectent les patterns imposés par la carte du projet et **compilent toutes**, y compris `leopardo_marketing`.

**Why this priority**: les patterns interdits créent des bugs d'état global (headers d'API) ; une app non compilable casse la matrice mobile.

**Independent Test**: `grep -rn "apiClient.dio.options" front/mobile_apps` = 0 occurrence ; import `PrimaryButton` résolu dans `leopardo_marketing` (le widget existe dans core ou est remplacé par `PulseButton`).

**Acceptance Scenarios**:

1. **Given** `user_auth_repository.dart` (employee/manager/hr), **When** on change la langue, **Then** les headers ne sont pas mutés globalement via `apiClient.dio.options` (utilisation de la mécanique `requestWithRetry`).
2. **Given** `leopardo_marketing`, **When** on compile, **Then** l'import `PrimaryButton` résout (widget réel) — l'app n'est plus un prototype cassé.

### User Story 5 - Traçabilité : constats rédigés en tâches (Priority: P3)

Chaque manquement non corrigé dans cette vague (SSO stub, push FCM, magic link, drift OpenAPI, fériés placeholder) est **rédigé en tâche traçable** (issue GitHub ou entrée tasks.md de la vague) pour une vague ultérieure.

**Why this priority**: « pour tout manquement constaté, des nouvelles tâches doivent être rédigées » — rien ne doit se perdre.

**Independent Test**: chaque constat listé dans la section « Constats non traités » possède une issue GitHub ouverte ou une tâche `[ ]` dans tasks.md.

**Acceptance Scenarios**:

1. **Given** un constat non traité, **When** la vague se termine, **Then** il existe une issue ouverte (avec `spec/` référencée) ou une tâche cochée-non-faite explicite.
2. **Given** la vague, **When** elle est mergée, **Then** le CHANGELOG.md contient une entrée par changement de comportement.

## Requirements

### Functional Requirements

- **FR-001**: La suite de tests backend doit être 100 % verte sur la branche de la vague.
- **FR-002**: PHPStan strict (level 8) doit être `[OK] No errors` sur `api/`.
- **FR-003**: Le module `user` (12 routes) doit avoir au minimum des tests Feature de contrat (register/login/me/logout + employee-links).
- **FR-004**: `BankExportGenerator` ne doit plus émettre de placeholders `PLACEHOLDER_*` : IBAN/BIC depuis la configuration du tenant, avec erreur claire si absents.
- **FR-005**: Les routes notifications doivent avoir une convention unique (PUT `read-all` conservé, POST `mark-all-read` aliasé en compat) — sans breaking change pour les clients existants.
- **FR-006**: Aucun `<button>` sans handler dans `front/web/src/app/(dashboard)/**` et `front/admin-dashboard/src/views/**` (boutons réellement cliquables).
- **FR-007**: Le détail du bulletin (œil) dans `payroll/page.tsx` doit ouvrir un affichage détaillé.
- **FR-008**: Les événements des widgets Analytics doivent être écoutés par `AnalyticsView.vue`.
- **FR-009**: Aucune occurrence de `apiClient.dio.options` hors `dio.download` dans `front/mobile_apps`.
- **FR-010**: `leopardo_marketing` doit compiler (import `PrimaryButton` résolu ou remplacé).
- **FR-011**: Chaque changement de comportement doit avoir une entrée CHANGELOG.md et, le cas échéant, une issue GitHub de traçabilité.

### Key Entities

- **Bulletin de paie (payslip)** : affichage détail (modal/route) depuis la liste.
- **Utilisateur (user)** : routes register/login/me/logout/employee-links — contrat à tester.
- **Export bancaire** : IBAN/BIC tenant (configuration entreprise) injectés dans le XML.
- **Notifications** : convention de routes unifiée.
- **Widgets Analytics** : événements émis → handlers de vue.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Suite backend verte locale (Unit + Feature, ~1 900 tests) sur la branche de vague.
- **SC-002**: `phpstan-strict` et `pint --test` verts.
- **SC-003**: 0 bouton orphelin signalé dans le rapport de recon (web + admin) restant sans handler.
- **SC-004**: 0 occurrence `apiClient.dio.options` (hors download) et marketing compilable.
- **SC-005**: Tous les constats non traités tracés (issue GitHub ou tâche explicite).

## Assumptions

- Les PR en cours (#2147, #2156, #2157, #2159, #2160) sont hors périmètre : ne pas toucher à `openapi.yaml` ni aux zones compliance/web-badge/TG-onboarding pour éviter les conflits avec les agents actifs.
- SSO SAML/OIDC complet, push FCM/APNs, magic link démo, calendriers fériés officiels : hors périmètre d'implémentation de cette vague (tâches rédigées, vague ultérieure).
- La vérification mobile est statique (pas de Flutter SDK dans le sandbox) — la CI mobile validera.
