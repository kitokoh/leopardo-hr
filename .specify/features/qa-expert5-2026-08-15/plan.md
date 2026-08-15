# Plan: QA Expert #5 — 2026-08-15

**Input**: spec.md — 8 user stories (issues créées au préalable, label `qa-expert5-2026-08-15`).

## Stratégie

1. Créer les issues GitHub (une par constat) puis les specs/plan/tasks (ce fichier).
2. Corriger par priorité : API sécurité (P1/P2) → admin (P2) → mobile (P2) → docs/web (P3).
3. Une branche `fix/<issue>-<slug>` par issue (ou groupée par surface si faible risque),
   PR `Closes #N`, CHANGELOG sous `## [Unreleased]`.
4. Vérifications : build/lint local web+admin ; `flutter analyze` via CI mobile ; Pint/PHPStan/
   tests via CI backend. Anti-doublon #2400 : vérifier branches/PRs avant push.
5. Merge campaign : merger les PRs vertes (miennes + vagues parallèles), garder main vert.

## Phases

### Phase 1 — API sécurité (payroll gates) [P1]
- [ ] Branche `fix/<issue>-payroll-gates` : `api.manager:principal,comptable` sur PUT/PATCH/
      DELETE `/payrolls/{id}` + `/payrolls/{id}/validate` dans rh.php ; garder la garde
      contrôleur (isManager) ; test 403 dept. PR `Closes #N`.

### Phase 2 — API honnêteté cockpit + erreurs correction [P2/P3]
- [ ] `LaunchReadinessController` : `communication_governance` exige `activeEmployees > 0`.
- [ ] `AttendanceController@requestCorrection` : erreur attachée au bon champ.

### Phase 3 — Admin santé entreprise + labels pays [P2/P3]
- [ ] `PlatformCompanyHealthService` : émettre `slug` + `created_at` dans le bloc company.
- [ ] Locales admin : clés `common.countries.{CG,CF,TD,GQ,NE,BJ,TG,...}` ×4.

### Phase 4 — Mobile devise + l10n stale [P2/P3]
- [ ] `company_create_screen.dart` : devise depuis `/platform/country-defaults` (pas DZD).
- [ ] 5 modèles partagés : fallback devise = valeur API/tenant, DZD dernier recours.
- [ ] Retirer `generate: true` sans `l10n.yaml` dans les 4 apps (ou ajouter l10n.yaml).

### Phase 5 — Docs + web [P3]
- [ ] AGENTS.md + README : cartographie apps corrigée (employee présent, kiosk = web).
- [ ] `sitemap.ts` : gate `/blog` sur `NEXT_PUBLIC_ENABLE_BLOG`.

### Phase 6 — Merge & validation finale
- [ ] PRs vertes mergées ; CHANGELOG ; main vert confirmé ; rapport QA_SESSION_2026-08-15-expert5.md
      poussé.

## Validation
- Local : `npm run build` + `npm run lint` (web + admin) ; scripts node dev-hub si pertinents.
- CI : checks requis (Backend Coverage, PHPStan Strict, Module Structure Validator, Frontend
  ESLint+TS, actionlint) verts avant merge.
