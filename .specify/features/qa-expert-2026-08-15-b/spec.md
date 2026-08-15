# Feature Specification: QA Expert 2026-08-15 (session B) — cohérence prix/essai, OpenAPI, CI main

**Feature Branch**: `fix/<issue>-<slug>` (une PR par issue, Closes #N)

**Created**: 2026-08-15

**Status**: Spec → Tasks → Issues → Implémentation (PRs #2982, #3115, #3121, #3133)

**Input**: Mission utilisateur — tester toutes les surfaces (vitrine, web, admin, mobiles,
workflows, APIs, logiques, onboarding, cohérence) ; tout manquement → spec + tasks + issues
(méthode Spec Kit) ; implémenter ensuite ; merger le max de branches ; main vert.

## Contexte

Session de test experte du 2026-08-15 (après-midi) sur kitokoh/leopardo-hr. Les campagnes QA
du matin (#2600–#2813) sont exclues (anti-doublon §I). Constats **nouveaux** de cette session :

| ID | Surface | Constat | Statut |
|----|---------|---------|--------|
| F-01 | Vitrine/API | Durée d'essai incohérente : backend = 14 j (`PlanSeeder`, `ProvisionGuidedTrial`, migration default) mais vitrine mélangeait 14 et 30 j (metas SEO, manifest PWA, FAQ pages, trialNotes pricing) | ✅ Corrigé — PR #2982 |
| F-02 | Vitrine SEO | Meta description pricing citait des plans fantômes « Starter 29€ / Business 79€ » vs plans réels Free/Pilot/Operations/Enterprise | ✅ Corrigé — PR #2982 |
| F-03 | API/OpenAPI | 20 routes mergées sans doc OpenAPI (#2836/#2861/#2862/#2867) → garde `check-openapi-route-coverage.py` ROUGE sur main ; + drift inverse 4 (expense-claims submit/reject, notifications read) | ✅ Corrigé — PR #3121 |
| F-04 | API/Notifications | 405 runtime : clients (mobile employee/manager/hr, web dashboard, admin console) appellent `PUT /notifications/read-all` + `PUT /notifications/{id}/read` mais routes POST/PATCH ; mobile manager/hr appelaient `/notifications/mark-all-read` inexistant (404) | ✅ Corrigé — PRs #3121 + #3133 |
| F-05 | API/Migrations | Collision de basenames `2026_08_15_000001` (public ×2, tenant ×2) → `architecture-check` ROUGE sur main | ✅ Corrigé — PR #3133 |
| F-06 | i18n | Catalogues admin/web/mobile périmés vs shared (clés admin-only jamais mergées dans la source canonique) → `validate-and-sync` ROUGE sur main | ✅ Corrigé — PR #3133 |
| F-07 | CI/LFS | 5 PNG vitrine commités en contenu réel mais trackés LFS → git-lfs CI les réécrivait → garde i18n ROUGE sur main | ✅ Corrigé — PR #3133 |
| F-08 | Backend/Commercial | Plans backend `Starter/Business/Enterprise` (29/79/199€, 20/200/∞ emp) vs vitrine `Free/Pilot/Operations/Enterprise` (0/29/99€/devis, 5/30/250 emp) — noms, prix (99 vs 79) et limites divergents | 🔴 À arbitrer produit |
| F-09 | Backend/Trial | `VerifyTrialSignup` fallback `trial_days=30` vs `ProvisionGuidedTrial` fallback 14 vs `PlanSeeder` 14/14/30 | 🔴 À arbitrer produit |
| F-10 | Docs/Changelog | `## [Unreleased]` : headers dupliqués/intercalés (### Fixed ×3, Added ×2, Changed ×2, Chore ×2) + catégorie non standard `### Chore` | 🟡 P3 — consolidation |
| F-11 | API/Routes | `POST /notifications/mark-all-read` (dashboard.php) + `PATCH /notifications/{id}/read` : routes legacy sans appelant après #3133 (sauf tests) | 🟡 P3 — retrait/documentation |

## User Stories & Testing

### User Story 1 — La vitrine promet ce que le backend livre (P0, F-01/F-02)

Un prospect lit la même durée d'essai et les mêmes plans partout (vitrine, metas SEO, PWA,
FAQ) — 14 jours, plans Free/Pilot/Operations/Enterprise.

**Independent Test**: `grep -r "30 jours\|30-day" front/web/src front/web/public` → 0 résultat
hors mentions de rétention post-essai ; `grep "Starter 29\|Business 79" front/web/src` → 0.

**Acceptance**: (1) metas SEO landing+pricing en 14 jours avec vrais plans ; (2) manifest PWA
14 jours ; (3) FAQ pages essai 14 jours FR/EN/TR/AR ; (4) catalogues partagés + copies syncées.

### User Story 2 — Les contrats API sont documentés et routés (P0, F-03/F-04)

`check-openapi-route-coverage.py` passe sur main (0 drift nouveau, 0 drift inverse) ; les
clients de notifications appellent des routes qui existent avec les bons verbes.

**Independent Test**: `python3 dev-hub/tools/check-openapi-route-coverage.py` → exit 0 ;
`grep -rn "mark-all-read" front/` → 0.

**Acceptance**: (1) 20 routes documentées dans `api/openapi.yaml` ; (2) `read-all`/`read` en PUT
côté routes (rh.php) et clients (mobile ×3, web, admin) ; (3) doublon de route `hierarchy`
supprimé ; (4) méthodes expense-claims réalignées.

### User Story 3 — Les gardes CI de main sont vertes (P0, F-05/F-06/F-07)

`architecture-check`, `i18n-enterprise` (validate-and-sync) et `mobile-apps-ci` (notifications
proof) passent sur main.

**Independent Test**: runs GitHub Actions sur main → conclusions `success`.

**Acceptance**: (1) migrations renumérotées (collision 000001 → 000004/000005) ; (2) clés
admin-only mergées dans shared + sync complet committé ; (3) PNG exclus du filtre LFS ;
(4) repository notifications manager/hr contient le marqueur `/notifications/read-all`.

### User Story 4 — Cohérence commerciale backend/vitrine (P1, F-08/F-09 — À ARBITRER)

Un client Operations paie ce que la vitrine affiche. Décision produit requise : aligner
`PlanSeeder` (noms/prix/limites) sur la vitrine ou l'inverse.

**Independent Test**: après arbitrage, `GET /api/v1/plans` (ou seeder) vs `data/pricing.ts`
→ mêmes noms/prix/limites.

**Acceptance**: (1) arbitrage documenté (issue) ; (2) application sur seeders + données
existantes (migration de données si nécessaire) ; (3) `trial_days` uniforme 14.

### User Story 5 — Changelog propre (P3, F-10/F-11)

`## [Unreleased]` suit Keep a Changelog : un header par catégorie, catégories canoniques
(Added/Changed/Fixed/Removed), routes legacy notifiées.

**Independent Test**: scan des headers sous `[Unreleased]` → chaque catégorie unique.

## Requirements

### Functional Requirements

- **FR-001**: Toute copie vitrine mentionnant la durée d'essai doit dire 14 jours (backend truth).
- **FR-002**: Les metas SEO pricing doivent citer les vrais plans (Free/Pilot/Operations/Enterprise).
- **FR-003**: Toute route API nouvelle doit être documentée dans `api/openapi.yaml` avant merge.
- **FR-004**: Les routes notifications doivent accepter PUT sur `read-all` et `{id}/read`.
- **FR-005**: Aucune collision de basenames de migrations.
- **FR-006**: `shared/i18n/locales` reste la source unique des catalogues.
- **FR-007**: Les fichiers non-LFS ne doivent pas être trackés LFS.
- **FR-008**: PlanSeeder et pricing vitrine doivent converger (arbitrage produit requis).
- **FR-009**: `trial_days` doit être uniforme entre les chemins de provisionnement.

## Success Criteria

- **SC-001**: `check-openapi-route-coverage.py` vert sur main (0/0 drift).
- **SC-002**: architecture-check, i18n-enterprise, mobile-apps-ci verts sur main.
- **SC-003**: 0 occurrence « 30 jours » de durée d'essai dans la vitrine.
- **SC-004**: 0 appelant de `/notifications/mark-all-read` dans les frontends.
- **SC-005**: Arbitrage produit F-08/F-09 documenté dans l'issue dédiée.

## Edge Cases

- Renommage de migrations déjà exécutées : idempotence requise (vérifiée : `CREATE TABLE IF NOT EXISTS`, `hasColumn`).
- Garde i18n diff (PA2-I18N-014) : les corrections de littéraux existants sont signalées (heuristique « added lines ») — pas de nouveaux littéraux introduits.
- Cloudflare « Workers Builds: gestionemploye » : statut externe hors code (cf. leçon Vercel AGENTS.md) — ne bloque pas le merge si les checks requis sont verts.

## Assumptions

- 14 jours = vérité backend (décision #2944, PlanSeeder, migration default).
- Les clés checkout canoniques restent `free/starter/business/enterprise` (labels Pilot/Operations/Enterprise).
