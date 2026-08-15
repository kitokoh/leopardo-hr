# Session QA expert15 — 2026-08-15 (après-midi)

Bilan de session agent : rebases, merges, nettoyage et implémentations.

## 1. Pipeline merge & nettoyage (Phase 0/2)

- **Rebase de masse** : 23 branches de PRs rebasées sur `main` à plusieurs reprises
  (2 vagues complètes + passe ciblée behind/dirty) ; 3 conflits vitrine résolus
  (`footer-links.test.ts` + `navbar-locale-url.test.ts` — imports vitest retirés
  par #3802, commentaire Jest conservé).
- **Anti-doublon (protocole #2400)** :
  - PR #3804 fermée → fusionnée dans la canonique #3801 → merge final via #3814
    (scope tenant fail-closed).
  - PR #3829 fermée (doublon #3806, canonique #3821) ; branche supprimée.
  - PR #3749 fermée (contenu déjà sur main, apporté par #3747+).
  - PR #3787 fermée (contenu déjà sur main).
- **Branches supprimées** : fix/3726-csv-import-duplicate-race (claim vide),
  fix/3727-tenant-scope-fail-closed, fix/3727-belongs-to-company-fail-closed,
  fix/3734-footer-dead-links, fix/3719-web-offline-edge-api, fix/3733-seo-dead-module…
- **Gardes CI débloquées** : PR #3813 (baselines PHPStan régénérées, `Closes #3158`
  ajouté) et #3815 (retitrée `chore(ci):` — exempte de l'exigence Closes #).
- **Merge-bot** : démon de fusion des PRs vertes (`mergeable_state == clean`
  + 5 checks requis success) — tourne toutes les ~75 s.

## 2. Implémentations (spec-kit)

| Issue | Correctif | PR |
|---|---|---|
| #3726 (P3 api) | Import CSV — catch 23505 par ligne, plus de 500, test `EmployeeImportRaceTest` (3 scénarios) | #3795 |
| #3727 (P3 api, sec) | Scope `BelongsToCompany` fail-closed (contrainte company_id explicite autorisée) — consolidé dans la canonique | #3814 (merged) |
| #3826 (P2 mobile, nouveau) | Manifest #2212 rouge : routes `/notifications`, `/evaluations`, `/history` restaurées dans leopardo_hr (régression #3715) | #3828 |
| #2597 (P3 mobile) | Écrans AI Voice « Bientôt disponible » + repos + provider retirés (code mort ×3 apps) | #3832 |
| #3006 (P2 mobile) | App Marketing : authentification câblée (login, redirect /login, logout) — fin des 401 en cascade | #3839 |
| #3809 (P3 admin) | PR ouverte sur la branche complète (LogoutView + ChatView honnêtes) | #3833 |

## 3. Audit 360° (Phase 1)

- Probes live prod : API v4.23.5 stale (explorer 500, i18n catalog 500,
  supported-countries 404, `/docs/openapi.yaml` 404) — famille connue
  #2627/#2632/#2812/#3767 (déploiement, pas code).
- Vitrine : pages 200, hreflang absent (#3250), locale URL absente (#3250) — connus.
- **Trouvaille nouvelle** : garde `check-mobile-manifest-routes.sh` rouge sur main
  (routes HR retirées par #3715 mais toujours servies par le manifeste) → issue
  #3826 + fix #3828.
- Gardes hygiène (version, env parity, migrations, pays, domaines) : PASS sur main.

## 4. Leçons

- `git add -A` a failli commiter un script contenant le token de session → Push
  Protection GitHub (GH013) l'a bloqué ; amend + reflog scrub. Ne jamais
  `git add -A` avec des fichiers outil dans le working tree.
- `git checkout -b` peut échouer silencieusement (worktree main déjà extrait) →
  toujours vérifier `git branch --show-current` avant de committer.
- Le treadmill CI (main bouge toutes les ~5 min) exige des rebases groupés :
  rebase de masse → laisser CI → merger vite (merge-bot).
