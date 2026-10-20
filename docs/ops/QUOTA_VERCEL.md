# Quota de déploiements Vercel — cause racine et remède

> Constat du 2026-10-18. Ce document existe parce que l'incident qu'il décrit a
> coûté **un mois de production figée** sans que personne ne s'en aperçoive.

## Le symptôme

`deploy-prod.yml` échouait au job Vercel avec :

```
Error: Deployment rate limited — retry in 24 hours (api-deployments-free-per-day)
```

Conséquence réelle : **aucun tag n'a été publié entre le 2026-09-20 (v4.33.0) et
le 2026-10-18**, alors que `main` continuait d'avancer. La prod servait donc du
code vieux de quatre semaines, et rien ne le signalait.

## La cause racine (mesurée, pas supposée)

Deux mécanismes distincts consommaient le même quota de 100 déploiements/jour :

| Mécanisme | Projet | Volume mesuré |
|---|---|---|
| Intégration Git — **preview par branche** | `leopardo` (dev, `prj_RcRVAujaFul8WNZXxgnNKTb9lXdr`) | **48 previews + 12 prod en 22 h** |
| Workflow API — déploiement explicite | `leopardo-prod` (`prj_6bmNKBT9lXdr…`) | 1 par tag |

Autrement dit : **le projet de dev épuisait le quota que la prod avait besoin de
consommer**. Chaque `git push` sur une branche de travail créait un déploiement
preview complet, même pour une PR de documentation.

Le détail qui rend le diagnostic non évident : les six autres projets Vercel
(`leopardo-dev`, `leopardo-dev-b`, `leopardo-dev-c`, `leopardoai`,
`leopardo-recrutement`, `gestionemployer-backend`) n'ont **aucune** intégration
Git — ils sont déployés uniquement par API. Un seul projet portait donc toute la
consommation « passive ».

## Le remède, par ordre d'efficacité

### 1. Passer le projet dev en « production-only » (immédiat, gratuit)

Dans Vercel → projet `leopardo` → Settings → Git → **Ignored Build Step**, ou en
API :

```bash
curl -X PATCH "https://api.vercel.com/v9/projects/prj_RcRVAujaFul8WNZXxgnNKTb9lXdr" \
  -H "Authorization: Bearer $VERCEL_DEV_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"gitProviderOptions":{"createDeployments":"disabled"}}'
```

⚠️ **Piège rencontré le 2026-10-18** : retirer l'intégration Git (`DELETE
/v9/projects/{id}/link`) ne rétablit pas les déploiements, et **la reposer via
l'API échoue** (`gitCredentialId` introuvable) quand le compte n'a pas de
connexion GitHub OAuth active. Sur ce projet, Git **est** le seul canal de
déploiement du front web dev : `deploy-main.yml` ne déploie que l'API. Couper le
lien sans avoir d'abord un déploiement API équivalent = perdre la CD du front.

**Ne jamais déconnecter Git avant d'avoir un canal de remplacement vérifié.**

### 2. Ne déployer les previews que sur les branches utiles

Si les previews sont nécessaires pour la revue, restreindre aux branches
`feat/**` et `fix/**` (Ignored Build Step avec `[[ "$VERCEL_GIT_COMMIT_REF" =~ ^(feat|fix)/ ]]`
inversé). Les branches `docs/**`, `chore/**`, `mission/**` n'ont pas besoin d'un
build Next complet.

### 3. Sérialiser les déploiements prod

Un seul job Vercel par tag, avec `concurrency: group: vercel-prod` — deux tags
poussés à 5 minutes d'intervalle ne doivent pas produire deux déploiements
concurrents qui se disputent le quota.

### 4. Décider du plan (arbitrage fondateur)

| Option | Coût | Effet |
|---|---|---|
| Hobby + previews désactivées | 0 € | ~1 déploiement/jour/tag : suffisant pour le rythme actuel |
| Vercel Pro | ≈ 20 $/mois | Quota levé, previews illimitées, logs longs |
| Front sur Cloudflare Pages | 0 € | 500 builds/mois, cache illimité — mais réécriture du pipeline |

Le statu quo (Hobby + previews par branche) **n'est pas tenable** : la limite est
atteinte en une journée de travail normale, et la sanction tombe sur la prod.

## Vérification

```bash
# Consommation du jour, tous projets du team
curl -s "https://api.vercel.com/v6/deployments?limit=100&since=$(date -d 'today 00:00' +%s000)" \
  -H "Authorization: Bearer $VERCEL_DEV_TOKEN" | jq -r '.deployments[] | "\(.name) \(.target // "preview")"'
```

Objectif sain : **moins de 10 déploiements par jour**, dont un seul en
`production` par tag publié.
