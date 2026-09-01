# Feature Specification: Vague Mission QA 2026-08-15 (sessions parallèles — findings nouveaux)

**Feature Branch**: `qa-mission-wave-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission du propriétaire (session 2026-08-15) — tester la plateforme dans tous les sens (vitrine, web, admin, mobile, workflows, APIs, logiques, onboarding, cohérence) et documenter chaque manquement en spec/tâches/incidents (méthode Spec Kit), puis implémenter.

## Contexte

Audit réalisé sur `main` (`d30b52da`, 2026-08-15) + prod live (API Render v4.23.5 stale, vitrine Vercel, admin Pages). Les vagues précédentes (qa-audit-expert-*, qa-omnichannel-*) ont déjà documenté T001-T134 ; cette vague couvre les findings NOUVEAUX (T135+) détectés par 4 audits parallèles par surface (web/admin/api/mobile) + tests live. Les issues #2802-#2811, #2794-#2795, #2594 sont **corrigées sur main** mais restent ouvertes (fermeture avec preuve code dans cette vague).

## User Scenarios & Testing

### User Story 1 — La vitrine web est honnête et cohérente (Priority: P1)

**Pourquoi P1** : le CTA « Commencer gratuitement » de la home mène au checkout payant Pilot (29 €) — défaut commercial critique ; les og:image pointent vers 19 fichiers 404.

**Independent Test** : crawl des CTA home → `plan=free` ; HEAD sur chaque og:image → 200 ; aucune métrique fabriquée dans opengraph-image.

**Acceptance Scenarios**:
1. **Given** la home, **When** clic « Commencer gratuitement » (section pricing), **Then** `/checkout?plan=free` (0 €), jamais `plan=starter`.
2. **Given** les pages landing, **When** extraction des og:image, **Then** 0 URL 404 (fichiers présents dans `public/` ou `next.config` rewrite).
3. **Given** `opengraph-image.tsx`, **When** inspection, **Then** plus de métriques mensongères (« 500+ entreprises »…).
4. **Given** `/blog/<slug>`, **When** clic sur un tag, **Then** filtre effectif ou lien retiré.
5. **Given** les pages `/download` et `/branding`, **When** recherche « Starter », **Then** aucune occurrence de plans fantômes.

### User Story 2 — Le cockpit admin n'affiche que du réel et de l'utilisable (Priority: P2)

**Pourquoi P2** : colonnes utilisateurs vides, états loading/erreur morts, interpolations i18n cassées = cockpit trompeur.

**Independent Test** : montage des vues concernées (UsersView, UserDetailView, HolidaysView) avec payloads réels → colonnes remplies, spinner/erreur fonctionnels, libellés interpolés.

**Acceptance Scenarios**:
1. **Given** `/platform/users` (payload snake_case), **When** UsersView + UserTable, **Then** colonnes Entreprise/Inscription remplies.
2. **Given** un échec API sur UserDetailView, **Then** bandeau d'erreur + bouton Réessayer visibles (isLoading/errorMessage déclarés).
3. **Given** HolidaysView FR, **Then** « Aucun jour férié pour l'Algérie / 2026. » (interpolation réelle, pas `{country}`).
4. **Given** GrowthDashboardView partenaire rejeté, **Then** statut « Rejeté » (pas « Approuvé ») ; plus de `prompt()` natif.

### User Story 3 — L'API backend est résistante aux races et aux silences (Priority: P1)

**Pourquoi P1** : double-provisioning trial (2 tenants pour 1 email) et double-dispatch bulk-pay (doubles documents de paiement) = pertes financières/données.

**Independent Test** : tests de concurrence (2 POST simultanés `/trial/verify` → 1 seule CompanyRequest approuvée ; 2 dispatches bulk-pay → 1 lot) ; `rg` des catches silencieux → 0.

**Acceptance Scenarios**:
1. **Given** 2 requêtes concurrentes `/trial/verify` même OTP, **Then** 1 seul tenant/manager créé (lock atomique).
2. **Given** double dispatch `ProcessBulkPaymentJob`, **Then** un seul traitement par slip (transition de statut atomique).
3. **Given** OAuth Google avec email inconnu, **Then** refus explicite (jamais d'employé tenantless) — aligné #2636.
4. **Given** routes publiques SSO/growth, **Then** throttles présents ; catches cockpit → log + 5xx explicite.

### User Story 4 — Les apps mobiles compilent et respectent les contrats API (Priority: P1)

**Pourquoi P1** : l'app HR ne compile pas (onboarding), l'app manager crashe sur deep-link non numérique, l'app marketing est inutilisable (401 systématique).

**Independent Test** : `flutter analyze` sur les 5 apps → 0 erreur ; tests widget ; croisement endpoints Dart vs `php artisan route:list`.

**Acceptance Scenarios**:
1. **Given** le code HR onboarding, **When** `flutter analyze`, **Then** 0 erreur de type (String vs int).
2. **Given** un deep-link `/cabinet/folder/abc` (manager), **When** ouverture, **Then** écran vide contrôlé (pas de crash) — route dupliquée supprimée.
3. **Given** ModulesScreen « Marquer comme lu », **When** tap, **Then** PATCH (pas PUT) → 2xx.
4. **Given** l'app marketing sans session, **When** démarrage, **Then** écran de connexion (pas de 401 systématique).

### User Story 5 — Cohérence transversale : durée d'essai unique (Priority: P1)

**Pourquoi P1** : 14 vs 30 jours selon la surface — mensonge commercial.

**Independent Test** : grep `trial` dans api/config, api/app/Modules/Billing, plans seeder, front/web pricing/manifest → une seule valeur.

**Acceptance Scenarios**:
1. **Given** PlanSeeder (14 j canonique), **When** comparaison surfaces, **Then** 14 jours partout (fallback 30 de VerifyTrialSignup corrigé).
2. **Given** le manifest PWA et les docs, **When** grep « 30 jours », **Then** alignés sur 14.

### Edge Cases

- Une PR parallèle (#2972) défend 30 jours : arbitrage par le code (PlanSeeder = 14) — commentaire sur la PR, pas de conflit.
- Les issues T123-T132 (#2802-#2811) : fermées avec preuve code (commentaire + état closed), pas de nouvelle implémentation.
- Branches `fix/<issue>-*` existantes = lock (protocole #2400) : avant toute implémentation, vérifier branches + PRs.
