# Plan: QA Expert #2 — Admin (front/admin-dashboard) (2026-08-15)

**Input**: spec.md — 12 findings.

## Stratégie

1. Corriger par priorité : P1 d'abord (bloquants build/rendu), puis P2 (UX/contrats), puis P3 (hygiène).
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis `origin/main` récent, PR avec `Closes #N`, CHANGELOG sous `## [Unreleased]`.
3. Vérifications : lint + build pour web/admin ; `flutter analyze`/contrats pour mobile (CI) ; Pint/PHPStan/tests via CI pour API.
4. Anti-régression : vérifier que les fichiers touchés ne réécrasent pas des fixes plus récents de main (`git diff origin/main...HEAD`).

## Phases

### Phase 1 — #3033 [P1] Admin — build prod cassé : DocumentReportIcon inexistant dans @heroicons/vue → vite build échoue (de
- [ ] Branche `fix/3033-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3033`.

### Phase 2 — #3034 [P1] Admin — CompanyDetailView crashe : lit health.adoption.kiosk.active jamais renvoyé par le backend → 
- [ ] Branche `fix/3034-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3034`.

### Phase 3 — #3036 [P2] Admin — DashboardView « Priorités Portefeuille » : lit item.name/slug/mrr_eur au lieu de company.*/s
- [ ] Branche `fix/3036-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3036`.

### Phase 4 — #3037 [P2] Admin — DashboardView « Inscriptions en attente » : lit request.name/manager_email au lieu de compan
- [ ] Branche `fix/3037-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3037`.

### Phase 5 — #3038 [P2] Admin — UsersView : colonne « Inscription » toujours « - » (mapping createdAt vs created_at) alors q
- [ ] Branche `fix/3038-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3038`.

### Phase 6 — #3039 [P3] Admin — 16 clés i18n manquantes dans les 4 locales (users.impersonation.*, users.toast.bulkDone) → f
- [ ] Branche `fix/3039-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3039`.

### Phase 7 — #3041 [P3] Admin — raccourci Alt+R pointe /recruitment (route tenant gardée) → rebond systématique + toast
- [ ] Branche `fix/3041-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3041`.

### Phase 8 — #3042 [P3] Admin — recherche header peut naviguer vers des routes tenant gardées (Paie, Congés…) → rebond muet
- [ ] Branche `fix/3042-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3042`.

### Phase 9 — #3043 [P3] Admin — bandeau « Mode maintenance » jamais déclenchable : setMaintenanceMode exposé mais aucun appe
- [ ] Branche `fix/3043-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3043`.

### Phase 10 — #3044 [P3] Admin — boutons d'action des notifications jamais affichés (aucune notification ne porte le champ ac
- [ ] Branche `fix/3044-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3044`.

### Phase 11 — #3045 [P3] Admin — export CSV AnalyticsView sans échappement anti-injection de formule (incohérent avec UsersVi
- [ ] Branche `fix/3045-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3045`.

### Phase 12 — #3046 [P3] Admin — 6 imports d'icônes inutilisés dans CommandPalette (résidu du retrait des entrées tenant)
- [ ] Branche `fix/3046-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3046`.

## Finalisation
- [ ] Mise à jour `docs/qa/QA_SESSION_2026-08-15-expert2.md` (bilan par surface).
- [ ] CHANGELOG.md : entrée `### Fixed` par PR.