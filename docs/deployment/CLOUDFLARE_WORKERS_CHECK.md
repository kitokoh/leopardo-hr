# Cloudflare Workers « gestionemploye » — check CI rouge (issue #1914)

## Qu'est-ce que ce check ?

`Workers Builds: gestionemploye` est un check **externe** posté par
l'intégration **Cloudflare Workers ↔ GitHub** (pas un workflow GitHub
Actions). Il apparaît sur **chaque PR** du dépôt.

- **Build géré côté dashboard Cloudflare** : aucune config `wrangler` dans
  ce repo (`grep -r wrangler .` ne renvoie rien), le build part du dashboard
  Cloudflare (service `gestionemploye`), pas de `.github/workflows/`.
- **Non requis pour merger** : il n'est PAS dans la liste des required
  checks de la branch protection de `main` → un PR peut être mergé même si
  ce check est rouge.
- **Bruit rouge permanent** + signal d'un build CF Workers cassé (même
  famille que #1834 pour CF Pages).

## Procédure quand il est rouge

1. **Vérifier la cause côté dashboard CF** : ouvrir
   `https://dash.cloudflare.com/.../workers/services/view/gestionemploye/production/builds/<build-id>`
   (lien dans la description du check) et lire l'erreur de build.
2. **Si le build est cassé** (erreur de compilation, secret manquant,
   variable d'env absente) : corriger dans le dashboard CF, relancer le
   build. Le check se mettra à jour sur les PRs suivantes.
3. **Si le build est sain mais le check reste rouge** (décalage de state) :
   relancer depuis le dashboard (retry build) — l'intégration GitHub
   republie le statut.
4. **Ne pas bloquer un merge pour ce check** : il n'est pas requis.
   Documenter l'écart dans la PR si le rouge est persistant.

## Alternative durable (recommandée si le rouge persiste)

Déplacer le build dans un workflow GitHub (`wrangler deploy` via
`cloudflare/wrangler-action`) avec les secrets CF dans GitHub Secrets
(`CF_API_TOKEN`, `CF_ACCOUNT_ID`) et un `wrangler.toml` versionné :
- le build devient visible et débuggable dans GitHub Actions ;
- le check devient reproductible (mêmes entrées, même résultat) ;
- on peut le déclarer required sur `main` en connaissance de cause.

Si cette option est retenue, supprimer le build géré dashboard pour éviter
les doubles déploiements (dashboard ET GitHub) sur le même worker.
