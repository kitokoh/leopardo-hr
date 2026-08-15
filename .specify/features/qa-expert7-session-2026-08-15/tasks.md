# Tasks: Session QA Expert 7 2026-08-15

**Input**: spec.md + findings-registry.md (`.specify/features/qa-expert7-session-2026-08-15/`)

**Anti-duplication (#2400)** : avant chaque implémentation, vérifier branches + PRs ouvertes
contenant le numéro d'issue ; une seule branche `fix/<issue>-*` par issue ; pousser la branche
(claim) dès le self-assign ; `Closes #N` dans le body de la PR.

## Phase 1 — Registre + session docs (branche docs)

- [ ] T001 [P1] Issue vitrine DOWN (E7-01) : `[QA][P1][ops]` leopardo-rh.com NXDOMAIN — DNS
      à restaurer + code à ne plus référencer d'URL mortes.
- [ ] T002 [P3] PR `docs/qa-expert7-session-2026-08-15` (spec + findings-registry + tasks).

## Phase 2 — Implémentation issues ouvertes sans lock (par priorité)

- [ ] T010 [P1][admin] #3388 MarketingOAuthView — template string inline → composant jamais
      rendu (build Vue runtime-only).
- [ ] T011 [P2][web] #3434 Résidus « 30 jours » d'essai après arbitrage 14j (FAQ EN/TR +
      TrialWelcomeMail 30 j).
- [ ] T012 [P3][admin] #3393 KeyboardShortcutsModal annonce Alt+R → Recrutement retiré.
- [ ] T013 [P3][admin] #3391 realtime store — « Tout marquer comme lu » POST → 405 (PUT).
- [ ] T014 [P3][admin] #3394 GrowthDashboardView — affectation morte + fetch jamais consommé.
- [ ] T015 [P3][admin] #3395 ExportsView — fetchHistory sans catch.
- [ ] T016 [P3][admin] #3436 PayrollView — 3 exports CSV sans anti-injection de formule.
- [ ] T017 [P3][admin] #3437 formatFeatureLabel 0/11 + CompanyDetailView 2/5 libellés bruts.
- [ ] T018 [P3][tooling] #3414 openapi-coverage-allowlist.txt — 2 entrées mortes.
- [ ] T019 [P3][tooling] #3416 front/web-offline — NEXT_PUBLIC_EDGE_API documenté nulle part.
- [ ] T020 [P3][docs] #3412 RBAC_ROUTE_MATRIX.md — famille dupliquée 4× + F-10 journal 2×.
- [ ] T021 [P3][docs] #3411 FRONTEND_API_CONTRACT_MATRIX.md — lignes orphelines + header dupliqué.
- [ ] T022 [P3][docs] #3409 CHANGELOG.md — historique release dupliqué (lignes 1207-1656).
- [ ] T023 [P3][api] #3366 RateLimiter trial-status enregistré DEUX fois.
- [ ] T024 [P3][api] #3367 Limiteur kiosk-punch défini mais jamais appliqué.
- [ ] T025 [P3][api] #3370 PasswordResetMail dupliqué (2 classes + 2 fichiers de test).
- [ ] T026 [P3][api] #3429 SalaryAdvanceController::markPaid — TOCTOU double écriture.

## Phase 3 — Tests approfondis (pendant implémentation)

- [ ] T030 Vitrine : build `npm run build` + lint + jest ; vérifier sitemap/robots/i18n.
- [ ] T031 Admin : build Vite + ESLint ; vérifier les composants touchés.
- [ ] T032 API : PHPStan strict 0 erreur sur les modules touchés ; tests ciblés si possible.
- [ ] T033 Vérification `main` vert : attendre fin des checks CI des derniers merges.

## Convergence

- [ ] T040 Mettre à jour CHANGELOG.md (entrées `## [Unreleased]` par PR).
- [ ] T041 Merger les PRs vertes du backlog quand les checks le permettent (sans casser main).
- [ ] T042 Mettre à jour registre + mémoire `.specify/memory/project-state.md` si pertinent.
