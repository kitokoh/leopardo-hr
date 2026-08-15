# Feature Specification: Audit Expert Mobile — Applications Flutter — 2026-08-15

**Feature Branch**: `qa-audit-expert-mobile-2026-08-15`

**Created**: 2026-08-15

**Status**: In progress

**Input**: Mission propriétaire — audit expert complet ; ce feature couvre **front/mobile_apps** (leopardo_employee, leopardo_manager, leopardo_hr, leopardo_platform_admin, leopardo_marketing + leopardo_core). Audit statique (pas de toolchain Flutter dans l'environnement d'audit — les tasks Dart sont livrées avec preuve statique + instructions d'implémentation, à valider avec `flutter analyze` côté CI).

## Contexte

L'audit mobile 2026-08-15 a comparé les 109 chemins API consommés par les apps aux 589 routes backend : **1 seul 404 réel** (`/departments/{id}/hierarchy`), mais plusieurs surfaces de mauvaise qualité : app marketing = scaffold Flutter par défaut avec stats 100 % fake, écrans AI Voice « Bientôt disponible », submit de note de frais sans gestion d'erreur (try/finally sans catch), URL de base production sur un domaine Render de dev, mot de passe démo en dur dans le bundle, double chemin offline (Hive legacy vs drift), et duplication leopardo_hr ≈ leopardo_manager.

## User Scenarios & Testing

### User Story 1 — L'organigramme par département ne 404 plus (Priority: P1)

Un manager qui ouvre l'organigramme d'un département obtient l'arbre hiérarchique — aujourd'hui `GET /departments/{id}/hierarchy` n'existe pas côté backend (404) : `leopardo_manager/lib/features/organigramme/data/organigramme_repository.dart:61` (+ leopardo_hr).

**Why this priority**: C'est la seule incompatibilité de contrat API confirmée entre mobile et backend — feature visible en production.

**Independent Test**: `php artisan test --filter=OrganigrammeTest` — endpoint retourne l'arbre scopé company + 404 cross-tenant ; `rg 'hierarchy'` mobile ↔ route backend.

**Acceptance Scenarios**:

1. **Given** un département existant, **When** `GET /api/v1/departments/{department}/hierarchy`, **Then** 200 avec l'arbre (managers/employés), scopé `company_id`.
2. **Given** un département d'un autre tenant, **Then** 404.

### User Story 2 — App marketing : stats réelles (Priority: P2)

Le dashboard de stats marketing affiche des données agrégées réelles depuis l'API (`/marketing/posts`) au lieu de chiffres codés en dur (`stats_dashboard_screen.dart:39-54` : 24 310 impressions, 800 ms fake).

**Why this priority**: Une app livrée avec le scaffold « A new Flutter project » et des stats inventées nuit à la crédibilité produit et à la décision métier.

**Independent Test**: `flutter analyze` vert ; le repository `MarketingRepository` expose `fetchStats()` (agrégation posts) ; aucun littéral fake (24_310…) dans `lib/`.

**Acceptance Scenarios**:

1. **Given** l'app marketing, **When** on ouvre Stats, **Then** les chiffres viennent de `/marketing/posts` (agrégation client ou endpoint dédié).
2. **Given** l'API en échec, **Then** état d'erreur affiché (pas de données factices).

### User Story 3 — Hygiène mobile : erreurs gérées, URLs propres, zéro secret démo (Priority: P3)

Toute action de soumission gère l'échec réseau (note de frais), les écrans placeholder AI Voice sont retirés ou câblés, les URLs de base sont configurables (pas de domaine Render/leopardo.local en prod), le mot de passe démo n'est pas dans les builds release, et l'offline a un seul propriétaire.

**Why this priority**: Erreurs silencieuses, URLs dev en prod et secrets démo dans le bundle sont des risques d'exploitation et de support.

**Independent Test**: `flutter analyze` vert sur les 5 apps ; `rg 'leopardo.local|gestionemployerbackend.onrender.com|password123'` → 0 dans `lib/` (hors builds debug) ; `rg 'try.*finally'` expense → avec catch.

**Acceptance Scenarios**:

1. **Given** une panne réseau, **When** on soumet une note de frais, **Then** message d'erreur visible (pas d'exception silencieuse) — `expense_list_screen.dart:38` (+ manager/hr).
2. **Given** un build release, **Then** aucune URL `leopardo.local`/`onrender.com` par défaut ni `password123` (`core_providers.dart:85`, `api_client.dart:13-16`, `demo_user_bottom_sheet.dart:13`).
3. **Given** les écrans AI Voice, **Then** supprimés ou câblés au backend `/ai/voice` (aujourd'hui « Bientôt disponible »).
4. **Given** l'offline, **Then** un seul mécanisme (drift `AttendanceOfflineService` OU Hive legacy) — décision documentée.

### Edge Cases

- Le endpoint `hierarchy` doit gérer les départements sans sous-équipe (arbre vide 200).
- La suppression des placeholders AI Voice ne casse pas la navigation (routes GoRouter retirées aussi).
- L'agrégation stats ne doit pas exposer de données cross-tenant.

## Requirements

### Functional Requirements

- **FR-001**: L'API DOIT exposer `GET /departments/{department}/hierarchy` (arbre scopé tenant).
- **FR-002**: Le dashboard stats marketing DOIT consommer l'API réelle (aucune donnée codée en dur).
- **FR-003**: Les soumissions de note de frais DOIVENT gérer l'erreur (catch + retour visuel).
- **FR-004**: Les écrans AI Voice placeholder DOIVENT être retirés ou implémentés.
- **FR-005**: Les URLs de base par défaut DOIVENT être configurables et sans domaine de dev en release.
- **FR-006**: Aucun mot de passe démo dans les builds release.
- **FR-007**: L'offline DOIT avoir un mécanisme unique (décision documentée).
- **FR-008**: `leopardo_hr` et `leopardo_manager` DOIVENT partager le code commun (dé-duplication).

### Key Entities

- **DepartmentHierarchy**: arbre départements→managers→employés (scopé company).
- **MarketingStats**: agrégation posts par plateforme.
- **ExpenseSubmission**: soumission note de frais avec états erreur.
- **OfflinePunch**: file de pointages offline (un seul propriétaire).

## Success Criteria

### Measurable Outcomes

- **SC-001**: 0 incompatibilité de contrat API mobile↔backend (matrice rejouable).
- **SC-002**: 0 donnée fake dans l'app marketing.
- **SC-003**: 0 exception non gérée sur les parcours de soumission audités.
- **SC-004**: `flutter analyze` vert (CI) sur les apps modifiées.

## Assumptions

- Pas de toolchain Flutter dans l'environnement : les tasks Dart sont livrées avec instructions précises et devront être validées par `flutter analyze` en CI ; les changes Dart ne sont **pas** poussés depuis cet environnement sans validation (risque de casser le build).
- Le endpoint backend `hierarchy` (T001) est implémentable et testable ici (PHP).
- La dé-duplication leopardo_hr/manager est un chantier structurel (migration vers leopardo_core) — documentée, pas exécutée dans ce run.
