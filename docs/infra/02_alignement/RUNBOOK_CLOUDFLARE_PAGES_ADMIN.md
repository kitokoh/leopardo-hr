# Runbook — Déploiement admin-dashboard sur Cloudflare Pages (leo-admin)

> Issue #1834 — le SPA admin (`front/admin-dashboard`) est servi par Cloudflare
> Pages (`leo-admin.pages.dev`) via **intégration Git**, sans workflow GitHub
> de déploiement. Quand l'intégration Git ne redéploie pas main (ou échoue
> silencieusement), **aucun fix admin n'atteint la production** — c'est le cas
> constaté 2026-08-14 : le fix CSP #1783 (retrait de `upgrade-insecure-requests`
> du header report-only) n'était toujours pas servi 17 h après le merge.

## Contexte (vérifié 2026-08-14)

- Le header CSP servi par `https://leo-admin.pages.dev/login` contenait encore
  `upgrade-insecure-requests` alors que `front/admin-dashboard/public/_headers`
  (copié tel quel dans `dist/` par Vite) ne le contient plus depuis #1783.
- Aucun workflow GitHub ne déploie le SPA : le déploiement repose entièrement
  sur la connexion Git Cloudflare Pages du projet `leo-admin`.
- Un garde CI existe déjà : `.github/workflows/admin-pages-deploy-guard.yml`
  (cron toutes les 6 h + `workflow_dispatch`) — il vérifie que le header CSP
  réellement servi correspond au `_headers` du repo et sort en erreur si
  `upgrade-insecure-requests` est encore présent.

## Procédure de diagnostic / réparation (dashboard Cloudflare)

1. Aller sur https://dash.cloudflare.com → compte `kitokoh` → **Workers &
   Pages** → projet **`leo-admin`** → onglet **Settings** :
   - **Production branch** : doit être `main` ;
   - **Build command** : `npm run build` (le SPA admin) ;
   - **Build output directory** : `dist` ;
   - **Root directory** : `front/admin-dashboard`.
2. Onglet **Deployments** :
   - Vérifier le **dernier déploiement** : date, commit, statut (échec ?
     build ignoré ? branche source correcte ?) ;
   - Si l'intégration Git n'a pas réagi au dernier push main : vérifier la
     **connexion Git** (Settings → Git) et cliquer **Retry deployment** sur le
     dernier commit main.
3. Confirmer le correctif :
   ```bash
   curl -sI https://leo-admin.pages.dev/login | grep -i content-security-policy
   ```
   → le header servi ne doit **plus** contenir `upgrade-insecure-requests`.
4. Si le build échoue : lire le log du déploiement (npm install ? vite build ?
   variable d'environnement `VITE_API_URL` manquante ?) et corriger dans les
   **Settings → Environment variables** du projet Pages.

## Alternative durable : déploiement depuis GitHub Actions

Si l'intégration Git Pages reste instable, déplacer le déploiement dans un
workflow GitHub avec `cloudflare/pages-action@v1` :

```yaml
# .github/workflows/deploy-admin-pages.yml (esquisse — à adapter)
name: Deploy Admin SPA to Cloudflare Pages
on:
  push:
    branches: [main]
    paths: ["front/admin-dashboard/**"]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with: { node-version: 22, cache: npm, cache-dependency-path: front/admin-dashboard/package-lock.json }
      - run: npm ci && npm run build
        working-directory: front/admin-dashboard
      - uses: cloudflare/pages-action@v1
        with:
          apiToken: ${{ secrets.CLOUDFLARE_API_TOKEN }}
          accountId: ${{ secrets.CLOUDFLARE_ACCOUNT_ID }}
          projectName: leo-admin
          directory: front/admin-dashboard/dist
          gitHubToken: ${{ secrets.GITHUB_TOKEN }}
```

Prérequis : secrets `CLOUDFLARE_API_TOKEN` + `CLOUDFLARE_ACCOUNT_ID` dans les
secrets du dépôt.

## Règle pour les agents

- **Ne pas tenter de réparer le déploiement depuis une PR** — c'est un état du
  projet Cloudflare (intégration Git Pages), pas du code du repo.
- Le garde `admin-pages-deploy-guard.yml` est la source de vérité : un run
  rouge = l'intégration Git ne redéploie pas main → suivre ce runbook (étapes
  1-3) avant d'ouvrir une PR « fix » sans effet.
- Toute modification de `front/admin-dashboard/public/_headers` doit garder
  la **même CSP que le garde vérifie** (ne pas réintroduire
  `upgrade-insecure-requests` en report-only — warning console Chrome traité
  comme erreur par le smoke E2E).
