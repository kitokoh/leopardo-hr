# 🛠️ Plan d'action CI/CD — Leopardo RH

> Compagnon de `AUDIT_CICD_2026-07-19.md`. Chaque item référence la section de l'audit.
> Statut : `[ ]` à faire, `[x]` fait dans cette PR, `[~]` fait partiellement / nécessite une action manuelle hors-repo (secrets, settings GitHub).

---

## Priorité P0 — Bugs actifs à corriger immédiatement

- [x] **(Audit §1.1)** Supprimer le fragment de script orphelin dans `tests.yml` (job `backend-coverage`, steps `mobile_*` fantômes lignes ~753-800). Zéro risque de régression : ce code ne fait déjà rien d'utile aujourd'hui.
- [x] **(Audit §1.2)** Corriger `release.yml` : soit supprimer le job `build-artifacts` (legacy, remplacé par `mobile-distribute.yml`), soit le réécrire en matrice sur `front/mobile_apps/*`. → Choix fait : suppression du job legacy, avec commentaire renvoyant vers `mobile-distribute.yml`.
- [x] **(Audit §1.3)** Corriger `dependabot.yml` :
  - `pub` : un `directory` par app Flutter réelle (`front/mobile_apps/leopardo_core`, `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_platform_admin`).
  - `npm` : ajouter `front/web`, `front/admin-dashboard`, `front/web-offline` en plus de `api`.
  - Ajouter écosystème `github-actions` (`directory: "/"`) pour recevoir les CVE et mises à jour des actions tierces automatiquement.

## Priorité P1 — Sécurité supply-chain (implémenté partiellement le 2026-07-19)

- [x] **(Audit §2.1)** `secret-scan.yml` : `trufflesecurity/trufflehog` épinglé par SHA complet (`27b0417c16317ca9a472a9a8092acce143b49c55` = v3.95.9) au lieu de `@main`. C'était la ref la plus risquée du repo (accès lecture au code + exécution sur chaque PR/push). Reste à faire : étendre progressivement le pinning SHA à `shivammathur/setup-php`, `subosito/flutter-action`, `wzieba/Firebase-Distribution-Github-Action`, `dawidd6/action-send-mail` — l'écosystème `github-actions` de Dependabot (ajouté en P0) proposera les PR de bump automatiquement une fois ces actions épinglées une première fois.
- [x] **(Audit §2.2)** Uniformisation `actions/checkout@v4` → `v5` (3 fichiers) et `actions/upload-artifact@v4` → `v5` (9 fichiers). Vérifié au préalable qu'aucun de ces workflows ne fait de `git push` (aucun changement de comportement `persist-credentials` à risque).
- [x] **(Audit §2.2, bonus)** `lighthouse.yml` : `treosh/lighthouse-ci-action@v10` → `@v12` (actionlint signalait un runtime Node trop ancien pour GitHub Actions ; v12 tourne sur node24).
- [x] **(Audit §2.3)** Combler le trou SAST PHP :
  - `phpstan-strict.neon` (level 8, `app/Core`/`app/Modules`/`app/Shared`) est désormais exécuté par un nouveau job `phpstan-strict` dans `architecture-check.yml`, en complément de `phpstan-modules`. Volontairement non bloquant (`continue-on-error`) tant qu'aucun baseline des findings existants n'a été généré — sinon la première exécution bloquerait rétroactivement des PR sans rapport.
  - `codeql.yml` (job `analyze-backend`) exécute désormais réellement Semgrep OSS (`p/php` + `p/security-audit`, image `semgrep/semgrep` épinglée par digest) au lieu du stub texte précédent, avec upload SARIF vers l'onglet Security GitHub (catégorie `semgrep-php`). Également non bloquant pour la même raison (pas de baseline existant) — décision de durcissement à prendre par l'équipe une fois les findings triés.
- [ ] **(Audit §2.4, hors CI)** Rotation immédiate du mot de passe Redis Upstash exposé dans l'historique git + purge d'historique coordonnée avec l'équipe (BFG Repo-Cleaner ou `git filter-repo`). Action déjà notée dans `AUDIT.md`, toujours non cochée — à traiter en priorité absolue en dehors de ce plan CI mais mentionnée ici car elle affaiblit la valeur de `secret-scan.yml`.

## Priorité P2 — Réduction de dette / duplication (implémenté le 2026-07-19)

- [x] **(Audit §3.1)** Dédup setup PHP+PostgreSQL+Redis : création de `.github/actions/setup-backend-db` (**composite action**, pas un reusable workflow — voir note technique ci-dessous) puis migration de `tests.yml` (jobs `backend-tests` et `backend-coverage`), `coverage-gate.yml`, `backend-jobs-ci.yml`. ~360 lignes dupliquées supprimées.
- [x] **(Audit §3.2)** Dédup setup Flutter/Java : création de `.github/actions/setup-flutter-android` puis migration de `mobile-apps-ci.yml` (×2 jobs), `mobile-distribute.yml`, `deploy-main.yml` (job `distribute-mobile`).
- [x] Suppression de `_setup-php.yml` et `_setup-flutter.yml` (reusable workflows morts, remplacés par les composite actions ci-dessus).
  - **Note technique importante** découverte pendant l'implémentation : ces deux fichiers étaient des *reusable workflows* (`on: workflow_call`), pas des composite actions. Un reusable workflow appelé via `jobs.<id>.uses:` s'exécute comme **un job séparé sur un runner distinct** — il ne peut donc pas partager les conteneurs `services:` (postgres/redis) déclarés par le job appelant. C'est très probablement la raison structurelle pour laquelle `_setup-php.yml`, pourtant bien écrit, n'a jamais pu être appelé par `tests.yml`/`coverage-gate.yml`/`backend-jobs-ci.yml` : ces jobs ont besoin que Postgres/Redis tournent dans le *même* job que les steps PHP. Les composite actions (`uses: ./.github/actions/...` avec `runs: using: composite`) s'exécutent comme des steps normaux à l'intérieur du job appelant et n'ont pas cette limitation — c'est le mécanisme correct pour ce cas d'usage.
- [x] **(Audit §3.3)** Remplacé la détection de fichiers changés maison (`git diff` + `grep -E`) par `dorny/paths-filter@v4.0.2` (épinglé par SHA) dans `tests.yml` et `deploy-main.yml`, avec `.github/paths-filters.yml` comme configuration centralisée partagée par les deux workflows au lieu de deux regex divergentes. **Changement de comportement mineur à valider par l'équipe** : le filtre `web` utilisé par `tests.yml` est maintenant le sur-ensemble que `deploy-main.yml` utilisait déjà (ajoute `deploy-main.yml` + 2 fichiers doc comme déclencheurs `web_changed`) — élargit strictement le périmètre de déclenchement de Web CI, ne le rétrécit jamais.

## Priorité P3 — Fiabilité des déploiements (implémenté le 2026-07-19, **changements de comportement à valider explicitement par l'équipe avant merge**)

- [x] **(Audit §4.1)** `deploy-staging.yml` déclenche désormais sur `workflow_run` de `Tests - Leopardo RH` (même mécanisme déjà utilisé par `deploy-main.yml`) au lieu de `push: [main]` + polling JS de 10 minutes sur un nom de check partiel. Conséquence directe : **le comportement "déploiement optimiste après timeout" a été supprimé**, pas reconfiguré — `workflow_run` ne démarre le job que si `Tests - Leopardo RH` a déjà conclu, donc il n'y a plus de fenêtre d'attente ni de scenario "CI n'a pas fini à temps". `workflow_dispatch` reste disponible pour un déploiement manuel explicite (hotfix) sans attendre la CI, comme avant. **À valider par l'équipe** : si un déploiement staging "optimiste" était activement utilisé en pratique, cette PR le retire.
- [x] **(Audit §4.1, bonus)** Le polling par nom de check partiel (`r.name.includes('Backend') && r.name.includes('PHP')`) a disparu avec le passage à `workflow_run` (résolu par construction, plus besoin de référence de nom du tout).
- [x] **(Audit §4.2)** `owasp-zap.yml` et `e2e-staging.yml` vérifient désormais spécifiquement la conclusion du job `Deploy API + Web to Render` (`deploy-api`) via l'API GitHub (`listJobsForWorkflowRun`), au lieu de la conclusion globale du workflow `Deploy - Leopardo RH`. Un échec de `distribute-mobile` (build Flutter/Firebase) ne bloque plus les smoke tests API/E2E si le déploiement API a réellement réussi.

## Priorité P4 — Hygiène générale (continu)

- [x] Documenté dans `docs/CI_CD_SECRETS.md` la liste complète des secrets et variables GitHub Actions référencés par `.github/workflows/**` (généré par grep exhaustif le 2026-07-19), avec usage et caractère requis/optionnel par workflow.
- [x] Ajouté un job `actionlint` (+ shellcheck intégré via `reviewdog/action-actionlint`, épinglé par SHA) dans `.github/workflows/actionlint.yml`, déclenché sur PR/push touchant `.github/workflows/**`, `.github/actions/**` ou `.github/paths-filters.yml`.
- [ ] Revoir périodiquement (trimestriel) la liste des workflows pour identifier de nouveaux jobs morts/legacy (comme `_setup-php.yml`/`_setup-flutter.yml` avant leur suppression le 2026-07-19). Reste une tâche récurrente manuelle, non automatisée.

---

## Ce qui a été livré dans cette PR (2026-07-19)

1. `AUDIT_CICD_2026-07-19.md` — audit complet (ce document est son plan associé).
2. Correctifs P0 (commit 1) :
   - `tests.yml` : suppression du fragment orphelin `mobile_*`.
   - `release.yml` : suppression du job legacy `build-artifacts` pointant vers `front/mobile` (dossier supprimé).
   - `.github/dependabot.yml` : correction des chemins Flutter réels + extension npm aux 3 front-ends manquants + ajout écosystème `github-actions`.
3. Correctifs P1 + P2 (commit 2) :
   - Pinning SHA de `trufflehog`, uniformisation `checkout`/`upload-artifact` sur les dernières versions stables, bump `lighthouse-ci-action` v10→v12.
   - Nouvelles composite actions `.github/actions/setup-backend-db` et `.github/actions/setup-flutter-android`, migration de 7 workflows, suppression des reusable workflows morts `_setup-php.yml`/`_setup-flutter.yml`.
4. Le reste (partie de P1, P3, P4) est documenté et priorisé mais volontairement **non implémenté dans cette PR** car cela demande des décisions produit/sécurité (rotation de secrets, comportement de déploiement optimiste, choix d'outil SAST) qui doivent être validées par l'équipe avant modification du comportement de production.

## Ce qui a été livré dans la PR de suivi (2026-07-19, tâches restantes du plan)

Cette PR implémente le reste des items non traités ci-dessus (P1 §2.3, P2 §3.3, P3 §4.1/4.2, P4), à l'exception explicite de la rotation du secret Redis Upstash (Audit §2.4) qui reste une action manuelle hors-repo pour l'équipe.

1. **P1 §2.3 — trou SAST PHP comblé** :
   - `phpstan-strict.neon` (level 8) est maintenant exécuté par un job dédié `phpstan-strict` dans `architecture-check.yml`.
   - `codeql.yml` (job `analyze-backend`) exécute réellement Semgrep OSS (`p/php` + `p/security-audit`, image épinglée par digest) et uploade les résultats en SARIF vers l'onglet Security, au lieu du stub texte précédent.
   - Les deux jobs sont volontairement non bloquants (`continue-on-error`) tant qu'aucun baseline des findings existants n'a été généré par l'équipe — à durcir dans un futur commit une fois les findings triés.
2. **P2 §3.3 — détection de changements unifiée** : nouveau fichier `.github/paths-filters.yml` consommé par `dorny/paths-filter@v4.0.2` (épinglé SHA) dans `tests.yml` et `deploy-main.yml`, remplaçant les deux regex `git diff`/`grep -E` divergentes.
3. **P3 §4.1/4.2 — fiabilité des déploiements** (changements de comportement, à valider par l'équipe) :
   - `deploy-staging.yml` déclenche sur `workflow_run` de `Tests - Leopardo RH` au lieu de `push` + polling 10 min ; le déploiement "optimiste" après timeout est supprimé.
   - `owasp-zap.yml`/`e2e-staging.yml` vérifient désormais spécifiquement la conclusion du job `deploy-api`, pas celle du workflow parent entier.
4. **P4 — hygiène** : nouveau `docs/CI_CD_SECRETS.md` (inventaire complet secrets/vars) et nouveau job `actionlint.yml` (+ shellcheck) sur PR touchant `.github/workflows/**`.
