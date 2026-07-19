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

## Priorité P1 — Sécurité supply-chain (à planifier, 1-2 sprints)

- [ ] **(Audit §2.1)** Épingler par SHA complet au minimum `trufflesecurity/trufflehog` (actuellement `@main`, le plus risqué car il tourne avec accès en lecture au repo). Ensuite étendre progressivement à `shivammathur/setup-php`, `subosito/flutter-action`, `treosh/lighthouse-ci-action`, `wzieba/Firebase-Distribution-Github-Action`, `dawidd6/action-send-mail`.
  - Une fois l'écosystème `github-actions` de Dependabot actif (fait en P0), Dependabot propose déjà les PR de bump de SHA — pas de script custom nécessaire.
- [ ] **(Audit §2.2)** Uniformiser toutes les actions GitHub officielles sur la dernière version stable (`actions/checkout@v5`, `actions/upload-artifact@v5` partout). Vérifier le changelog de `checkout@v5` (`persist-credentials`) avant bascule sur les workflows qui font des `git push` (auto-fix Pint, release, fix-composer-lock).
- [ ] **(Audit §2.3)** Combler le trou SAST PHP :
  - Option recommandée : job dédié `Psalm` ou renforcement de `phpstan-modules.neon`/`phpstan-strict.neon` déjà présents dans le repo mais pas systématiquement exécutés en CI bloquante — vérifier s'ils tournent réellement quelque part (`architecture-check.yml` exécute `phpstan-modules.neon`, mais `phpstan-strict.neon` ne semble appelé par aucun workflow).
  - Alternative complémentaire : intégrer `semgrep` (gratuit, open-source, supporte PHP nativement) en job CI dédié pour combler l'absence de CodeQL PHP réel.
- [ ] **(Audit §2.4, hors CI)** Rotation immédiate du mot de passe Redis Upstash exposé dans l'historique git + purge d'historique coordonnée avec l'équipe (BFG Repo-Cleaner ou `git filter-repo`). Action déjà notée dans `AUDIT.md`, toujours non cochée — à traiter en priorité absolue en dehors de ce plan CI mais mentionnée ici car elle affaiblit la valeur de `secret-scan.yml`.

## Priorité P2 — Réduction de dette / duplication (moyen terme)

- [ ] **(Audit §3.1)** Étendre `_setup-php.yml` pour couvrir le bootstrap multi-tenant (schema `shared_tenants` + migrations `public`/`tenant`), puis migrer `tests.yml` (×2 jobs), `coverage-gate.yml`, `backend-jobs-ci.yml` pour l'appeler via `uses: ./.github/workflows/_setup-php.yml` + `needs`. Réduction attendue : ~300 lignes de duplication.
- [ ] **(Audit §3.2)** Faire de même pour `_setup-flutter.yml` : migrer `mobile-apps-ci.yml`, `mobile-distribute.yml`, `deploy-main.yml` (distribute-mobile) pour l'appeler.
- [ ] **(Audit §3.3)** Remplacer la détection de fichiers changés maison (`git diff` + `grep -E`) par `dorny/paths-filter@v3` (bien maintenu, épinglable par SHA) dans `tests.yml` et `deploy-main.yml`, avec une configuration YAML centralisée des patterns partagés plutôt que deux regex divergentes à maintenir manuellement.

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
2. Correctifs P0 :
   - `tests.yml` : suppression du fragment orphelin `mobile_*`.
   - `release.yml` : suppression du job legacy `build-artifacts` pointant vers `front/mobile` (dossier supprimé).
   - `.github/dependabot.yml` : correction des chemins Flutter réels + extension npm aux 3 front-ends manquants + ajout écosystème `github-actions`.
3. Le reste (P1-P4) est documenté et priorisé mais volontairement **non implémenté dans cette PR** car cela demande des décisions produit/sécurité (rotation de secrets, comportement de déploiement optimiste, choix d'outil SAST) qui doivent être validées par l'équipe avant modification du comportement de production.
