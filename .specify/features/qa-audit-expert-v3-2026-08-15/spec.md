# Feature Specification: QA Audit Expert v3 2026-08-15 — manquements nouveaux

**Feature Branch**: `docs/qa-audit-expert-v3-2026-08-15`
**Created**: 2026-08-15
**Status**: Spec → Tasks → Issues → Implémentation
**Input**: Mission utilisateur — tester toutes les surfaces (vitrine, web, admin, mobiles,
workflows, APIs, logiques, onboarding, cohérence) ; tout manquement → spec + tasks +
issues (méthode Spec Kit) ; implémenter ensuite, puis implémenter le backlog et merger
les branches.

## Contexte

Campagne de test experte du 2026-08-15 sur kitokoh/leopardo-hr. Les constats complets
(preuves fichier:ligne, résultats runtime) sont dans [findings-registry.md](./findings-registry.md).
La campagne « expert #2 » parallèle couvre #2972→#3065 ; cette spec ne porte que sur les
manquements **nouveaux** (F-V3-01 → F-V3-18) et sur le P1 #3114 (build admin rouge,
corrigé dans cette vague — PR #3123).

## User Stories & Testing

### User Story 1 — La console super-admin retrouve Webhooks, Formations et Chat IA (P1)

Un super-admin ouvre `/webhooks`, `/training` ou `/chat` dans la console : la vue se charge
(sans redirection muette) et consomme les endpoints `/admin/*` cross-tenant créés en #2634.

**Independent Test**: navigation SPA vers `/webhooks` → pas de toast « Fonctionnalité
entreprise » ni redirection ; `GET /admin/webhooks` appelé et rendu.

**Acceptance Scenarios**:
1. **Given** un token super-admin, **When** navigation vers `/webhooks`, **Then** la vue
   Webhooks s'affiche (CRUD fonctionnel, pas de rebond vers `/`).
2. **Given** le même token, **When** navigation vers `/training`, **Then** la console
   Formations s'affiche ; l'onglet Catalogue ne ment pas (endpoint réel ou état vide honnête).
3. **Given** le même token, **When** navigation vers `/chat`, **Then** la console Chat IA
   s'affiche.

### User Story 2 — Le backend n'autorise jamais un employé non-manager à approuver une demande (P2)

`ApprovalController::approve/reject` invoquent la `ApprovalRequestPolicy` enregistrée
(manager-only). Tout employé authentifié reçoit 403 sauf manager légitime ; test de
régression dédié.

**Independent Test**: `POST /approvals/{id}/approve` avec un rôle employé → 403 ; avec un
manager du bon tenant → comportement existant.

**Acceptance Scenarios**:
1. **Given** une demande en attente, **When** un employé (non-manager) tente approve/reject,
   **Then** 403 (`AuthorizationException`), aucun changement de statut.
2. **Given** un manager, **When** approve/reject, **Then** flux existant inchangé (200).
3. **Given** une demande d'un autre tenant, **When** tentative, **Then** 404 (isolation).

### User Story 3 — Les notifications push mobiles ne peuvent pas échouer en silence (P2)

Plus aucune clé Firebase placeholder (`AIzaSyREPLACE…`, `REDACTED_GOOGLE_API_KEY`,
`mobilesdk_app_id` zéro) n'est commitée ; le bootstrap FCM échoue proprement (statut
visible) au lieu d'avaler l'erreur.

**Independent Test**: `rg "REPLACE|REDACTED|000000000000" front/mobile_apps` → vide hors
`.gitignore`/docs ; `flutter analyze` vert sur les apps tenant.

**Acceptance Scenarios**:
1. **Given** le repo, **When** scan des 6 plateformes, **Then** aucune clé placeholder.
2. **Given** une app sans Firebase configuré, **When** bootstrap, **Then** échec non bloquant
   journalisé, pas de crash.

### User Story 4 — `flutter analyze` est vert sur les 6 apps mobiles (P2)

Le cycle de providers (`top_level_cycle`) dans employee/manager/hr, les directives mal
placées et l'erreur `WidgetRef→Ref` de platform_admin, et les 44 erreurs marketing sont
corrigés. La CI mobile « Mobile Flutter (Stable Channel) » repasse verte sur main.

**Independent Test**: `flutter analyze` → 0 erreur dans les 6 apps (Flutter 3.47 stable).

**Acceptance Scenarios**:
1. **Given** le workspace, **When** `flutter analyze` (employee), **Then** 0 erreur.
2. **Given** idem (manager/hr/platform_admin/marketing), **Then** 0 erreur.

### User Story 5 — Le SSRF et les fuites d'URLs sont fermés côté backend (P3)

`POST /cameras/test-rtsp` refuse les cibles loopback/privées/link-local (réutilise la
garde `NotPrivateUrl` existante) et restreint le port ; les routes d'écriture `rh.php`
portent `api.manager` comme les autres modules.

**Independent Test**: `test-rtsp` vers `rtsp://127.0.0.1/…` → 422 ; `rtsp://192.168.x.x` → 422 ;
URL publique → comportement existant.

**Acceptance Scenarios**:
1. **Given** une URL privée, **When** test-rtsp, **Then** 422 (refus).
2. **Given** une URL publique RTSP valide, **When** test-rtsp, **Then** flux existant.

### User Story 6 — SEO vitrine : canonical par page, zéro orphelin (P3)

Les 8 layouts landing passent un `canonical` explicite (`${SITE_URL}${path}`) ; les 3
fichiers orphelins vitrine sont supprimés après vérification `rg`.

**Independent Test**: `rg "seo-metadata|caching-config|OptimizedImage|useIntersectionObserver"`
→ 0 hors usage attendu ; `curl -sI https://vitrine/employes | grep canonical` → URL de la page.

**Acceptance Scenarios**:
1. **Given** `/employes`, **When** lecture du `<link rel="canonical">`, **Then** `/employes` (pas la home).
2. **Given** le repo, **When** scan des orphelins, **Then** fichiers supprimés, lint/build verts.

### User Story 7 — Pas de N+1 ni de duplication de logique paie (P3)

`markPaid` eager-load `items.employee` ; `liveMap` agrège les positions Traccar en un
appel ; la logique d'agrégation paie de `SocialDeclarationController` est extraite dans
un service partagé (ou documentée comme dette avec issue dédiée).

**Acceptance Scenarios**:
1. **Given** un batch de paie de 500 items, **When** markPaid, **Then** requêtes ≤ O(items) constant.
2. **Given** le contrôleur social, **When** revue, **Then** une seule méthode d'agrégation partagée.

### Edge Cases

- Les 3 apps tenant partagent le même fichier de providers : le fix du cycle doit être
  appliqué dans les 3 (pas seulement employee).
- `useFocusTrap`/`useAnnouncer` : vérifier 0 référence avant suppression (rg).
- Le canonical : ne pas toucher aux pages qui passent déjà un canonical explicite.
- FCM : ne pas commiter de vraies clés — documenter le mécanisme d'injection CI/dart-define.
