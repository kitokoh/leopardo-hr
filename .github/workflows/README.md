# GitHub Actions Workflows

Ce dossier contient les workflows CI/CD actifs de Leopardo RH.

## Workflows actifs

### `tests.yml` - Backend, mobile et gouvernance

Ce workflow centralise :

- backend PostgreSQL + Redis
- audit Composer
- qualité PHP (Pint, syntaxe, PHPStan/Larastan)
- couverture backend visible via Clover + artifact HTML
- qualité mobile Flutter avec couverture et seuil
- gouvernance documentaire
- rapport CI unifié

### `web-ci.yml` - Admin dashboard

Ce workflow remplace les anciens `build.yml`, `lint.yml` et `test.yml`.

Il ne s'exécute que sur :

- `admin-dashboard/**`
- `.github/workflows/web-ci.yml`

Jobs inclus :

- `web-lint`
- `web-build`
- `web-e2e-playwright`

Artefacts utiles :

- rapport HTML Playwright
- `test-results/junit.xml`
- traces Playwright au premier retry
- videos Playwright retenues en echec

### `secret-scan.yml` - Scan de secrets

Scan TruffleHog sur push et pull request pour bloquer les secrets verifiés ou inconnus avant merge.

### `codeql.yml`

Analyse statique de securite GitHub CodeQL.

### `deploy-main.yml`

Pipeline de deploiement principal.

Le deploiement automatique ne part que si :

- `Tests - Leopardo RH` est vert sur le SHA de `main`
- `Web CI - Leopardo Admin` est aussi vert si le SHA touche `admin-dashboard/**`
- les workflows requis sont bien conclus sur le meme SHA

### `lighthouse.yml`

Workflow de performance web conserve en declenchement manuel pour eviter le bruit CI.

## Notes importantes

- Le frontend versionne dans ce depot est `admin-dashboard/`, pas `web/`.
- Le statut externe `Vercel` peut etre rouge sans que les GitHub Actions utiles soient en echec.
- Les workflows doivent rester limites par `paths:` quand cela permet d'eviter des executions inutiles.
