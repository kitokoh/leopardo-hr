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
- [ ] **(Audit §2.3)** Combler le trou SAST PHP :
  - Option recommandée : job dédié `Psalm` ou renforcement de `phpstan-modules.neon`/`phpstan-strict.neon` déjà présents dans le repo mais pas systématiquement exécutés en CI bloquante — vérifier s'ils tournent réellement quelque part (`architecture-check.yml` exécute `phpstan-modules.neon`, mais `phpstan-strict.neon` ne semble appelé par aucun workflow).
  - Alternative complémentaire : intégrer `semgrep` (gratuit, open-source, supporte PHP nativement) en job CI dédié pour combler l'absence de CodeQL PHP réel.
- [ ] **(Audit §2.4, hors CI)** Rotation immédiate du mot de passe Redis Upstash exposé dans l'historique git + purge d'historique coordonnée avec l'équipe (BFG Repo-Cleaner ou `git filter-repo`). Action déjà notée dans `AUDIT.md`, toujours non cochée — à traiter en priorité absolue en dehors de ce plan CI mais mentionnée ici car elle affaiblit la valeur de `secret-scan.yml`.

## Priorité P2 — Réduction de dette / duplication (implémenté le 2026-07-19)

- [x] **(Audit §3.1)** Dédup setup PHP+PostgreSQL+Redis : création de `.github/actions/setup-backend-db` (**composite action**, pas un reusable workflow — voir note technique ci-dessous) puis migration de `tests.yml` (jobs `backend-tests` et `backend-coverage`), `coverage-gate.yml`, `backend-jobs-ci.yml`. ~360 lignes dupliquées supprimées.
- [x] **(Audit §3.2)** Dédup setup Flutter/Java : création de `.github/actions/setup-flutter-android` puis migration de `mobile-apps-ci.yml` (×2 jobs), `mobile-distribute.yml`, `deploy-main.yml` (job `distribute-mobile`).
- [x] Suppression de `_setup-php.yml` et `_setup-flutter.yml` (reusable workflows morts, remplacés par les composite actions ci-dessus).
  - **Note technique importante** découverte pendant l'implémentation : ces deux fichiers étaient des *reusable workflows* (`on: workflow_call`), pas des composite actions. Un reusable workflow appelé via `jobs.<id>.uses:` s'exécute comme **un job séparé sur un runner distinct** — il ne peut donc pas partager les conteneurs `services:` (postgres/redis) déclarés par le job appelant. C'est très probablement la raison structurelle pour laquelle `_setup-php.yml`, pourtant bien écrit, n'a jamais pu être appelé par `tests.yml`/`coverage-gate.yml`/`backend-jobs-ci.yml` : ces jobs ont besoin que Postgres/Redis tournent dans le *même* job que les steps PHP. Les composite actions (`uses: ./.github/actions/...` avec `runs: using: composite`) s'exécutent comme des steps normaux à l'intérieur du job appelant et n'ont pas cette limitation — c'est le mécanisme correct pour ce cas d'usage.
- [ ] **(Audit §3.3)** Remplacer la détection de fichiers changés maison (`git diff` + `grep -E`) par `dorny/paths-filter@v3` (bien maintenu, épinglable par SHA) dans `tests.yml` et `deploy-main.yml`, avec une configuration YAML centralisée des patterns partagés plutôt que deux regex divergentes à maintenir manuellement. Non implémenté dans cette itération (changement de comportement de déclenchement CI à valider avec l'équipe avant merge).

## Priorité P3 — Fiabilité des déploiements (à discuter avec l'équipe avant de changer le comportement)

- [ ] **(Audit §4.1)** Décider explicitement : `deploy-staging.yml` doit-il continuer à déployer "de manière optimiste" après 10 minutes de timeout CI ? Si non, changer le comportement par défaut en échec bloquant, avec un input `workflow_dispatch` explicite pour forcer le déploiement optimiste en cas de besoin ponctuel.
- [ ] **(Audit §4.1)** Remplacer le polling par nom de check partiel (`r.name.includes('Backend') && r.name.includes('PHP')`) par une référence stricte au nom exact du job (`"Backend (PHP 8.4 + PostgreSQL 16 + Redis 7)"`), ou mieux : utiliser `workflow_run` comme le fait déjà `deploy-main.yml` plutôt qu'un polling manuel — supprimerait la fenêtre de 10 minutes et la logique de retry.
- [ ] **(Audit §4.2)** Découpler `owasp-zap.yml`/`e2e-staging.yml` du statut global du workflow `Deploy - Leopardo RH` : vérifier spécifiquement la conclusion du job `deploy-api` via l'API GitHub (comme le fait déjà `deploy-main.yml` pour ses propres gates) plutôt que la conclusion globale du workflow, pour éviter qu'un échec de build mobile bloque les smoke tests API.

## Priorité P4 — Hygiène générale (continu)

- [ ] Documenter dans un seul fichier (`docs/CI_CD_SECRETS.md` ou équivalent) la liste complète des secrets et variables GitHub Actions requis par workflow, avec leur portée (repo vs environment). Actuellement dispersé entre `AUDIT.md` et la mémoire de l'équipe.
- [ ] Ajouter un job `actionlint` (+ shellcheck intégré) en CI dédiée sur PR touchant `.github/workflows/**`, pour éviter la régression du bug §1.1 à l'avenir. Coût : quasi nul (actionlint tourne en quelques secondes), gain : détection immédiate de scripts orphelins / expressions invalides.
- [ ] Revoir périodiquement (trimestriel) la liste des workflows pour identifier de nouveaux jobs morts/legacy (comme `_setup-php.yml`/`_setup-flutter.yml` aujourd'hui).

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
