# Feature Specification: Vague Durcissement QA — Audit Fonctionnel 2026-08-14

**Feature Branch**: `qa-hardening-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Draft — tâches issues d'un audit fonctionnel complet (API workflows, vues, boutons, logique)

**Input**: Constitution `.specify/constitution.md` + AGENTS.md — audit de la plateforme Leopardo RH (backend Laravel, web Next.js, admin-dashboard Vue, mobile Flutter, kiosk) à la recherche de tout manquement fonctionnel (boutons morts, liens `#`, contrats non couverts, docs spec-kit en retard).

## User Scenarios & Testing

### User Story 1 — Aucun bouton mort dans le portail client web (Priority: P1)

Un manager connecté au portail web (`front/web`) ne doit rencontrer aucun bouton qui ne fait rien : chaque action visible déclenche une navigation réelle ou une action API réelle.

**Independent Test**: tests Playwright `front/web/e2e/manager-workday-smoke.spec.ts` étendu + nouveau spec vérifiant que les Actions rapides naviguent, que le bouton "Voir toute l'activité" navigue, que "Oui, envoyer" (Leo IA) crée une vraie annonce et que "Plus tard" ferme la carte.

**Acceptance Scenarios**:

1. **Given** un manager connecté sur `/dashboard`, **When** il clique sur « Nouvel employé », **Then** il est redirigé vers la liste des employés (`/dashboard/employees`) — jamais vers un `#`.
2. **Given** le même écran, **When** il clique sur « Congés », **Then** il est redirigé vers `/dashboard/absences`.
3. **Given** le même écran, **When** il clique sur « Rapports » ou « Export », **Then** il est redirigé vers `/dashboard/reports`.
4. **Given** la carte Leo IA, **When** il clique sur « Oui, envoyer », **Then** une annonce d'équipe réelle est créée via `POST /api/v1/announcements` (statut de succès affiché) — pas un simple toast local.
5. **Given** la carte Leo IA, **When** il clique sur « Plus tard », **Then** la carte est masquée durablement (préférence locale), sans navigation morte.
6. **Given** la liste des bulletins de paie (`/dashboard/payroll`), **When** il clique sur l'icône œil (détail), **Then** un panneau de détail du bulletin s'ouvre avec les informations de la ligne (net, brut, période, statut).

### User Story 2 — Aucun lien mort dans le portail super-admin (Priority: P2)

La page de connexion admin (`front/admin-dashboard/src/views/auth/LoginView.vue`) ne doit plus contenir de liens `href="#"`.

**Independent Test**: Playwright admin-dashboard `e2e/login-smoke.spec.js` étendu : les liens « Mot de passe oublié ? », « Sécurité » et « Support » pointent vers des destinations réelles (route interne, page publique ou mailto support).

**Acceptance Scenarios**:

1. **Given** la page `/login` admin, **When** on inspecte les liens, **Then** aucun `href="#"` n'existe.
2. **Given** le lien « Support », **When** on clique, **Then** on atteint une destination réelle (page support publique ou mailto de l'équipe).
3. **Given** le lien « Mot de passe oublié ? », **When** on clique, **Then** on atteint le canal de support approprié (aucun flux reset n'existant pour le super-admin, le lien doit être honnête : support/mailto, pas un formulaire fantôme).

### User Story 3 — Les 5 apps mobiles sont couvertes par le contrat de workflows (Priority: P2)

Le fichier `dev-hub/tools/mobile-workflow-contracts.json` et `validate-mobile-workflow-contracts.ps1` couvrent aussi l'app `leopardo_hr`, qui est livrée avec les autres mais absente du contrat.

**Independent Test**: `dev-hub/tools/validate-mobile-workflow-contracts.ps1` passe avec une entrée `hr` déclarée (routes, endpoints, tokens d'écran).

**Acceptance Scenarios**:

1. **Given** le contrat mobile, **When** on liste les apps déclarées, **Then** `employee`, `manager`, `platform_admin` **et** `hr` sont présents.
2. **Given** l'app `leopardo_hr`, **When** on exécute le validateur, **Then** toutes ses routes statiques et endpoints API sont déclarés et existent (0 route fantôme).

### User Story 4 — L'état spec-kit reflète la réalité de main (Priority: P3)

`/speckit-converge` doit produire des conclusions exactes : les tâches déjà livrées sur `main` sont cochées dans `.specify/features/*/tasks.md`.

**Independent Test**: `.specify/features/multi-pays-wave-2026-08-14/tasks.md` — T018/T021/T022 cochés (livrés sur main) ; T014/T015/T016-T020 laissés ouverts avec leur issue de suivi.

**Acceptance Scenarios**:

1. **Given** la vague multi-pays, **When** on audite `tasks.md`, **Then** les tâches mergées sur main sont `[x]` avec leur PR de référence.
2. **Given** une tâche encore ouverte, **When** on audite, **Then** elle reste `[ ]` avec l'issue GitHub de suivi.

## Edge Cases

- Le bouton « Oui, envoyer » Leo IA doit gérer l'échec API (annulation → message d'erreur, pas de mensonge).
- Les liens admin « Sécurité »/« Support » : s'il n'existe pas de page publique dédiée, privilégier `mailto:` du canal support réel — jamais un `#`.
- Le contrat mobile `hr` : vérifier que `leopardo_hr` ne déclare pas de routes interdites (employee forbidden routes) — cohérence avec les autres apps.

---

## User Stories mapping (résumé pour plan.md)

| US | Surface | Tâche | Priorité |
|----|---------|-------|----------|
| US1 | `front/web` dashboard + payroll | T001-T002 | P1 |
| US2 | `front/admin-dashboard` LoginView | T003 | P2 |
| US3 | `dev-hub/tools` + `leopardo_hr` | T004 | P2 |
| US4 | `.specify/features/*` | T005 | P3 |
