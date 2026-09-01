# Plan: QA Expert #2 — Vitrine / Web (front/web) (2026-08-15)

**Input**: spec.md — 12 findings.

## Stratégie

1. Corriger par priorité : P1 d'abord (bloquants build/rendu), puis P2 (UX/contrats), puis P3 (hygiène).
2. Chaque correctif : branche `fix/<issue>-<slug>` depuis `origin/main` récent, PR avec `Closes #N`, CHANGELOG sous `## [Unreleased]`.
3. Vérifications : lint + build pour web/admin ; `flutter analyze`/contrats pour mobile (CI) ; Pint/PHPStan/tests via CI pour API.
4. Anti-régression : vérifier que les fichiers touchés ne réécrasent pas des fixes plus récents de main (`git diff origin/main...HEAD`).

## Phases

### Phase 1 — #3021 [P2] Vitrine — og:image 404 sur ~20 pages : fix #2752 appliqué dans seo-metadata.ts mort, seo.ts vivant r
- [ ] Branche `fix/3021-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3021`.

### Phase 2 — #3022 [P2] Vitrine — clés i18n brutes affichées dans le flux OTP du signup (c.otpInvalidLength, c.otpVerifyErro
- [ ] Branche `fix/3022-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3022`.

### Phase 3 — #3023 [P2] Vitrine — /pricing masque le surcoût par employé actif que la home affiche (+2 EUR/employé)
- [ ] Branche `fix/3023-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3023`.

### Phase 4 — #3024 [P2] Vitrine — tableau comparatif pricing incohérent avec les cartes de plans (Pilot, Operations, multi-p
- [ ] Branche `fix/3024-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3024`.

### Phase 5 — #3025 [P2] Vitrine — plan Pilot AR : features promises absentes des cartes fr/en/tr (bulletins PDF, portail cli
- [ ] Branche `fix/3025-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3025`.

### Phase 6 — #3026 [P2] Vitrine — stats fabriquées dans l'image OG générée (500+ entreprises, 50K+ employés, 99.9%)
- [ ] Branche `fix/3026-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3026`.

### Phase 7 — #3027 [P2] Dashboard client — carte « Leo IA » factice (retards -15%) + « Présence hebdo +12% » à barres codées
- [ ] Branche `fix/3027-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3027`.

### Phase 8 — #3028 [P3] Vitrine — tags background-sync PWA incompatibles : client enregistre sync-forms/sync-analytics, le S
- [ ] Branche `fix/3028-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3028`.

### Phase 9 — #3029 [P3] Vitrine — le service worker précache des routes dashboard authentifiées (login page cachée sous /das
- [ ] Branche `fix/3029-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3029`.

### Phase 10 — #3030 [P3] Dashboard client — page /edge-nodes toujours routée et protégée par middleware malgré #2602 fermée (
- [ ] Branche `fix/3030-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3030`.

### Phase 11 — #3031 [P3] Vitrine — SignupForm : étapes pending/success encore 100% FR malgré #2727 fermée
- [ ] Branche `fix/3031-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3031`.

### Phase 12 — #3032 [P3] Vitrine — contenu FAQ incohérent : paie « 6 pays (…Sénégal) » vs demo 5 pays ; Sage/QuickBooks vendu
- [ ] Branche `fix/3032-slug` depuis origin/main ; implémentation minimale ; tests/checks ; PR `Closes #3032`.

## Finalisation
- [ ] Mise à jour `docs/qa/QA_SESSION_2026-08-15-expert2.md` (bilan par surface).
- [ ] CHANGELOG.md : entrée `### Fixed` par PR.