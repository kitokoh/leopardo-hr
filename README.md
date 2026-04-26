# Leopardo RH

Monorepo de conception et d'execution pour Leopardo RH.

## Points d'entree recommandes

- `PILOTAGE.md` : source de verite operationnelle du programme
- `docs/README.md` : index documentaire avec distinction entre cible et etat courant
- `docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md` : etat reel de `main` et ecarts doc/implementation
- `docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md` : validation backend locale, Docker d'abord
- `api/README.md` : bootstrap backend Laravel et commandes de verification
- `docs/PROMPTS_EXECUTION/v3/MVP-01_INIT_LARAVEL.md` : contexte d'execution MVP

## Important

La documentation produit et API contient a la fois :

- une cible fonctionnelle large
- un etat d'implementation plus restreint sur `main`

Avant de brancher un client mobile/web sur l'API, lire d'abord :

- `docs/GESTION_PROJET/ALIGNEMENT_DOCUMENTATION_MAIN_2026-04-26.md`
- `api/routes/api.php`
- `api/routes/modules/rh.php`
- `api/routes/modules/cameras.php`

## Distribution mobile

- Workflow : `.github/workflows/mobile-distribute.yml`
- Trigger staging : `git tag v1.0-staging && git push origin v1.0-staging`
- Secrets GitHub Actions requis : `FIREBASE_APP_ID`, `FIREBASE_TOKEN`
