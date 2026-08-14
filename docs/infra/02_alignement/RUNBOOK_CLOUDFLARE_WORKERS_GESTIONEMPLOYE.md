# Runbook — Check CI « Workers Builds: gestionemploye » (Cloudflare Workers)

> Issue #1914 — le check externe « Workers Builds: gestionemploye » est rouge
> sur (quasi) chaque PR. Il n'est **pas requis** pour merger (pas dans les
> required checks de main) mais génère un bruit rouge permanent et signale un
> build Cloudflare Workers cassé — même famille que #1834 pour CF Pages.

## Contexte

- Le worker `gestionemploye` est **géré côté dashboard Cloudflare** (intégration
  Git Cloudflare Workers) : **aucune config `wrangler.toml`/`wrangler.json` ni
  source worker dans ce dépôt** (vérifié le 2026-08-14).
- GitHub ne peut donc pas réparer ce check depuis une PR : c'est un état du
  projet Cloudflare (account propriétaire `kitokoh`).
- Symptôme vu par les agents : « Workers Builds: gestionemploye » rouge sur le
  dashboard de la PR, sans log accessible côté GitHub.

## Procédure de diagnostic / réparation (dashboard Cloudflare)

1. Aller sur https://dash.cloudflare.com → compte `kitokoh` → **Workers & Pages**.
2. Ouvrir le worker **`gestionemploye`** → onglet **Builds** (intégration Git).
   - Vérifier la **branche de production** : doit être `main`.
   - Vérifier la **build command** et le **root directory** (s'ils existent).
   - Ouvrir le **dernier build** : lire l'erreur exacte (npm install ? esbuild ?
     secret manquant ?).
3. Corriger la config OU cliquer **Retry build** si l'échec est transitoire.
4. Re-vérifier le check sur une PR fraîche : le check « Workers Builds:
   gestionemploye » doit passer au prochain push.

## Alternative durable : déplacer le build dans GitHub Actions

Si le worker a une source versionnable, la bonne pratique est de la déplacer
dans le repo et de builder avec `wrangler` :

```yaml
# .github/workflows/worker-gestionemploye.yml (esquisse — à adapter)
name: Worker gestionemploye
on:
  push:
    branches: [main]
    paths: ["workers/gestionemploye/**"]
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: cloudflare/wrangler-action@v3
        with:
          apiToken: ${{ secrets.CLOUDFLARE_API_TOKEN }}
          accountId: ${{ secrets.CLOUDFLARE_ACCOUNT_ID }}
          workingDirectory: workers/gestionemploye
```

Prérequis : secrets `CLOUDFLARE_API_TOKEN` + `CLOUDFLARE_ACCOUNT_ID` dans les
secrets du dépôt, et le `wrangler.toml` du worker versionné.

## Règle pour les agents

- **Ne pas tenter de réparer ce check depuis une PR** — il est externe.
- Si le check apparaît rouge sur une PR, vérifier qu'il ne bloque pas le merge
  (il n'est pas dans les required checks : Backend Coverage, PHPStan Strict,
  Module Structure Validator, Frontend ESLint, actionlint) puis **le signaler
  dans la PR** avec un lien vers ce runbook au lieu de perdre du temps.
