# Plan: QA Expert #2 — Mobile (front/mobile_apps) (2026-08-15)

**Input**: spec.md — 8 findings.

## Stratégie

1. Corriger par priorité : P1 d'abord (bloquants build/rendu), puis P2 (UX/contrats), puis P3 (hygiène).
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis `origin/main` récent, PR avec `Closes #N`, CHANGELOG sous `## [Unreleased]`.
3. Vérifications : lint + build pour web/admin ; `flutter analyze`/contrats pour mobile (CI) ; Pint/PHPStan/tests via CI pour API.
4. Anti-régression : vérifier que les fichiers touchés ne réécrasent pas des fixes plus récents de main (`git diff origin/main...HEAD`).

## Phases

### Phase 1 — #3047 [P2] Mobile — notifications « marquer lu/lues » : PUT au lieu de POST/PATCH → 405 garanti (employee, mana
- [ ] Branche `fix/3047-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3047`.

### Phase 2 — #3048 [P2] Mobile — POST /user/company-requests sans maxRetriesOverride → demande doublée sur timeout (classe #
- [ ] Branche `fix/3048-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3048`.

### Phase 3 — #3049 [P3] Mobile manager — GoRoute /cabinet/folder/:folderId déclarée 2× (résidu fix #2748)
- [ ] Branche `fix/3049-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3049`.

### Phase 4 — #3050 [P3] Mobile — écran mort PersonalSpaceScreen (« Créer mon entreprise » inaccessible) dans employee/manage
- [ ] Branche `fix/3050-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3050`.

### Phase 5 — #3051 [P3] Mobile — leopardo_hr sans ShellRoute/bottom-nav (employee/manager en ont une)
- [ ] Branche `fix/3051-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3051`.

### Phase 6 — #3052 [P3] Mobile — cast direct data['data']['id'] as String alors que le backend renvoie un int (AttendanceLog
- [ ] Branche `fix/3052-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3052`.

### Phase 7 — #3053 [P3] Mobile — ThemeMode.dark forcé dans les 5 apps → lightTheme mort
- [ ] Branche `fix/3053-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3053`.

### Phase 8 — #3054 [P3] Mobile — DateTime.parse non gardés résiduels : hr attendance_repository:552 + cabinet/monthly_summar
- [ ] Branche `fix/3054-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3054`.

## Finalisation
- [ ] Mise à jour `docs/qa/QA_SESSION_2026-08-15-expert2.md` (bilan par surface).
- [ ] CHANGELOG.md : entrée `### Fixed` par PR.