# Tasks: Audit expert Admin — 2026-08-15

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

> Conversion en issues GitHub : label `qa-audit-2026-08-15`, méthode Spec Kit taskstoissues.
> **Sessions précédentes (canoniques, à ne pas dupliquer)** : série web/admin #2602-#2613 — edge-nodes page, quick-action links, script accents, i18n pages, lint/build, SITE_URL (#2607), robots (#2643), blog mort (#2609), EditUserModal (#2610), header search (#2611), composants orphelins (#2612), clés i18n users (#2613). Mes T032/T034/T042/T049 (doublons) fermés en faveur de #2624/#2610/#2611/#2612.

## Phase 1 — P1 (US B1, B2, B3)

- [x] T032 [P1] B1 Impersonation — **doublon fermé** : canonique #2624 (T011 backend : endpoints /admin/impersonations). (issue #2692)
- [x] T033 [P1] B2 `SystemAlertsOverlay.vue:206-210` : retirer la simulation de maintenance (état « non disponible » honnête). (issue #2693)
- [x] T034 [P1] B2 EditUserModal — **doublon fermé** : canonique #2610 (T009 web). (issue #2694)
- [x] T035 [P1] B3 `LoginView.vue:207` : retirer les identifiants démo en dur. (issue #2695)
- [x] T036 [P1] B2 `MiniGlobe.vue:51-73` : état vide honnête sans données socket (plus de points fictifs). (issue #2696)

## Phase 2 — P2 (US B4, B5, B6)

- [x] T037 [P2] B4 `UserTable.vue:135` : bouton Éditer mort → retirer (avec `showEditModal`/modales). (issue #2697)
- [x] T038 [P2] B4 `UsersView.vue` : pagination réelle (`page`/`per_page` server-side). (issue #2698)
- [x] T039 [P2] B4 `UsersView.vue:306-309` : filtre statut « En attente » mappé ou retiré. (issue #2699)
- [x] T040 [P2] B4 `UsersView.vue:485` : export CSV — champs mappés (`createdAt`/`lastLoginAt`) + échappement anti-injection de formule. (issue #2700)
- [x] T041 [P2] B4 `UsersView.vue:319-321` : mapping rôle/entreprise réels depuis le payload. (issue #2701)
- [x] T042 [P2] B5 Header search — **doublon fermé** : canonique #2611 (T010 web). (issue #2702)
- [x] T043 [P2] B5 `CommandPalette.vue` : filtrer par routes accessibles + corriger/retirer `/vehicles`. (issue #2703)
- [x] T044 [P2] B5 `DashboardView.vue:154-160` : carte « intégrations partenaires » → cible réelle ou retrait. (issue #2704)
- [x] T045 [P2] B6 `stores/realtime.js:330-357` : `_skipAuthRedirect` + avaler les 401 (pattern `pollNotifications`). (issue #2705)
- [x] T046 [P2] B6 `MetricCard.vue`/`AnalyticsView.vue` : prop `trend` — valeurs numériques + slot libellé. (issue #2706)
- [x] T047 [P2] B6 Notifications push : propager l'id serveur du payload socket (supprimer l'id synthétique). (issue #2707)

## Phase 3 — P3 (US B7)

- [x] T048 [P3] B7 Titres d'onglets : traduire `meta.title` (`marketing.oauth.nav_title`, `holidays.nav.title`) dans le garde. (issue #2708)
- [x] T049 [P3] B7 Composants morts — **doublon fermé** : canonique #2612 (T011 web). (issue #2709)
- [x] T050 [P3] B7 `ExportsView` : état de téléchargement réel + erreurs affichées (plus de `setTimeout` ni catch silencieux). (issue #2710)
- [x] T051 [P3] B7 `GrowthDashboardView` : `alert()` → toast. (issue #2711)
- [x] T052 [P3] B7 `SocialContributionsView.runSimulate` : try/catch + état de chargement. (issue #2712)
- [ ] T053 [P3] B7 Sélecteur de langue + router les chaînes codées en dur via le catalogue i18n (défaut : sélecteur + échantillon des vues principales). (issue #2713)
- [x] T054 [P3] B7 `UsersView.deleteUser` : `POST /platform/users/{id}/deactivate` (désactivation réelle) + confirmation explicite. (issue #2714)
- [x] T055 [P3] B7 `money()` → locale active (`toIntlLocale`). (issue #2715)
- [x] T056 [P3] B7 Real-time : watcher d'état (`watch(notifications[0])`) au lieu de `$subscribe(events)`. (issue #2716)

## Convergence

- [ ] T057 Mettre à jour `.specify/memory/project-state.md`, `CHANGELOG.md`, `AGENTS.md`, cocher les tâches après merge.
