# Tasks: Vague Durcissement QA — Audit Fonctionnel 2026-08-14

**Input**: spec.md + plan.md

**Prerequisites**: plan.md (required), spec.md (required)

## Phase 1 — Portail client web (`front/web`) — P1

- [ ] T001 [P1] US1 Actions rapides + « Voir toute l'activité » + carte Leo IA branchées sur de vraies actions
  - Quick actions → routes réelles (`/dashboard/employees`, `/dashboard/absences`, `/dashboard/reports`)
  - « Voir toute l'activité » → `/dashboard/reports`
  - « Oui, envoyer » → `POST /api/v1/announcements` (annonce équipe réelle, gestion d'erreur)
  - « Plus tard » → masque la carte (préférence locale)
  - Issue #2167 — branche `fix/2167-web-dashboard-dead-actions`
- [ ] T002 [P1] US1 Bouton œil détail bulletin (`/dashboard/payroll`) → panneau de détail fonctionnel
  - Issue #2168 — branche `fix/2168-payroll-slip-detail`

## Phase 2 — Portail super-admin (`front/admin-dashboard`) — P2

- [ ] T003 [P2] US2 LoginView : supprimer les 3 liens `href="#"` (Mot de passe oublié / Sécurité / Support) au profit de destinations réelles
  - Issue #2169 — branche `fix/2169-admin-login-dead-links`

## Phase 3 — Contrat mobile — P2

- [ ] T004 [P2] US3 Ajouter l'app `hr` à `dev-hub/tools/mobile-workflow-contracts.json` + vérifier `validate-mobile-workflow-contracts.ps1` vert
  - Issue #2170 — branche `fix/2170-mobile-contract-hr`

## Phase 4 — Hygiène spec-kit — P3

- [ ] T005 [P3] US4 Rafraîchir `.specify/features/multi-pays-wave-2026-08-14/tasks.md` : cocher T018/T021/T022 (livrés sur main), laisser T014/T015/T016-T020 ouverts avec leur issue
  - Issue #2171 — branche `docs/2171-speckit-tasks-refresh`

## Phase 5 — Convergence

- [ ] T006 Mettre à jour `.specify/memory/project-state.md` avec l'audit fonctionnel 2026-08-14
