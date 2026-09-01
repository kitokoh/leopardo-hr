# Tasks: Session QA Expert 8 2026-08-15 — infrastructure & périphérie

**Input**: spec.md + plan.md (`.specify/features/qa-expert8-infra-2026-08-15/`)

**Prerequisites**: plan.md (required), spec.md (required)

**Tests**: tests d'échec explicites pour les P1 ; lint/build web ; actionlint ; shellcheck sur scripts.

## Format: `[ID] [P?] [Story] Description`

- **[P]** = parallélisable
- **[Story]** = US1…US7 (voir spec.md)

---

## Phase 1: US1 — Guard backup_drill (#3518, P1)

- [ ] T001 [US1] Marker branch `fix/3518-backup-drill-guard` (claim vide) + self-assign #3518.
- [ ] T002 [US1] Ajouter garde : `RESTORE_DB_URL` vide ou == `DATABASE_URL` (normaliser hôte/port/db) → exit 1 + message.
- [ ] T003 [US1] Exiger `CONFIRM_RESTORE=YES` avant tout `DROP SCHEMA` ; logger la cible (sans credentials).
- [ ] T004 [US1] Test d'échec : script refuse avec cible == source ; `bash -n` + shellcheck verts ; CHANGELOG.

## Phase 2: US2 — Paths CI jobs & queues (#3519, P1)

- [ ] T005 [US2] Marker branch `fix/3519-jobs-ci-paths` + self-assign #3519.
- [ ] T006 [US2] Corriger `paths:` vers `api/app/Modules/{Billing,Payroll}/**` réels dans backend-jobs-ci.yml.
- [ ] T007 [P] [US2] Ajouter une garde (script dev-hub/tools) qui échoue si un path de workflow ne matche aucun fichier ; câbler dans actionlint.yml ou workflow dédié.
- [ ] T008 [US2] Vérifier déclenchement (`gh run list`) post-merge ; actionlint + CHANGELOG.

## Phase 3: US3 — Credentials artefacts (#3520, #3521, P2)

- [ ] T009 [US3] Marker branches `fix/3520-postman-credentials` + self-assign #3520.
- [ ] T010 [US3] Remplacer bodies auth par `{{admin_email}}`/`{{admin_password}}` ; ajouter `postman/README.md` (environment local non commité) ; note rotation prod.
- [ ] T011 [P] [US3] `staging-demo-auth-smoke.sh` : `: ${DEMO_PASSWORD:?required}` sans défaut (#3521, branche `fix/3521-smoke-password-fallback`).
- [ ] T012 [US3] `grep -rn password123 postman/ dev-hub/ examples/` propre ; CHANGELOG ×2.

## Phase 4: US4 — Robustesse web (#3522, #3523, P2)

- [ ] T013 [US4] Marker `fix/3523-proxy-502-json` + self-assign #3523 ; wrapper fetch proxy (try/catch + `AbortSignal.timeout`) → 502 JSON + log ; test unitaire fetch rejeté.
- [ ] T014 [P] [US4] Marker `fix/3522-middleware-gate-honest` + self-assign #3522 ; valider forme token ou documenter gate cosmétique + test e2e cookie forgé.
- [ ] T015 [US4] `npm run lint && npm run build` verts (front/web) ; CHANGELOG ×2.

## Phase 5: US5 — Versioning & bootstrap (#3528-#3531, P2)

- [ ] T016 [P] [US5] #3528 : `APP_VERSION=4.24.0` dans `api/.env.example` (+ garde CI optionnelle config/app.php ↔ .env.example) ; note pour MAJ env Render.
- [ ] T017 [P] [US5] #3529 : `edge/install.sh` — sha256 épinglé + vérification avant exécution ; abort sur mismatch.
- [ ] T018 [P] [US5] #3530 : `edge/docker-entrypoint.edge.sh` — retirer `|| true` sur migrate ; healthcheck `degraded` sinon ; logs explicites.
- [ ] T019 [P] [US5] #3531 : `render.yaml` — `name: gestionemployerbackend` (ou doc domaine custom obligatoire) ; aligner README/cors.php si besoin.

## Phase 6: US6 — Gouvernance CI (#3532, #3533, #3534, #3538)

- [ ] T020 [P] [US6] #3532 : `cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}` sur codeql/secret-scan/openapi-ci et workflows sécurité/couverture listés.
- [ ] T021 [P] [US6] #3533 : aligner commentaire CODEOWNERS ↔ protection canonique (ou activer reviews sur chemins paie/migrations — décision à documenter dans la PR).
- [ ] T022 [P] [US6] #3534 : `fix-composer-lock.yml` ouvre une PR (create-pull-request) au lieu de push direct ; garde `if: github.ref_name != 'main'`.
- [ ] T023 [P] [US6] #3538 : supprimer le stub `mobile-flutter-stable-compat` ou l'ajouter au JSON canonique (cohérence).

## Phase 7: US7 — Dette web/api (#3535, #3536, #3537, P3)

- [ ] T024 [P] [US7] #3535 : retirer next-mdx-remote, gray-matter, reading-time, rehype-slug, rehype-autolink-headings, remark-gfm, ts-node + lockfile ; build vert.
- [ ] T025 [P] [US7] #3536 : `integrations/layout.tsx` avec metadata dédiées ; rafraîchir description guides (« Checklist Paie 2024 » → millésime courant).
- [ ] T026 [P] [US7] #3537 : déplacer clés EDGE_* dans `config/edge.php` ; commande ne lit que `config()` ; PHPStan + pint verts.

---

## Dependencies

- T001-T008 (P1) avant tout le reste.
- Stories indépendantes entre elles → phases 3-7 parallélisables (une PR par issue).
- Chaque tâche de code : vérifier anti-doublon (branches contenant le numéro d'issue) avant de coder.

## Validation Strategy

- Scripts : `bash -n` + shellcheck + test d'échec explicite.
- CI : actionlint + preuve de déclenchement post-merge (`gh run list`).
- Web : lint + build + test proxy.
- API : PHPStan strict + pint si PHP touché (#3537).
- Merge uniquement quand les 5 checks requis sont verts ; branche supprimée après merge.
