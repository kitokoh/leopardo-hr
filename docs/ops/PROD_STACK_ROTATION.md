# Rotation des stacks PROD — runbook

> Épic #8670 · issues #8671 (plan B), #8672 (bascule). Complète
> [`DEV_STACK_ROTATION.md`](DEV_STACK_ROTATION.md) (tier dev, données fictives).
> **Ici les données sont réelles** : bêta-testeurs, candidats, offres. Toute
> manipulation de la prod suit ce document à la lettre.

## 1. Pourquoi ce runbook existe

Le 2026-09-28, le service Render prod (`leopardo-prod`, plan **free**) a été
**suspendu pour dépassement du quota mensuel**, et n'est revenu que le
2026-10-01 (événements Render `suspender_added` / `suspender_removed`).
Par ailleurs, la prod est **figée depuis la v4.33.0 (2026-09-20)** : le
déploiement Vercel a échoué sur `api-deployments-free-per-day`, c'est-à-dire
**plus de 100 déploiements dans la journée** sur le compte Vercel gratuit.

Autrement dit, ce n'est pas la disponibilité du code qui est en cause, c'est la
**consommation des offres gratuites**. Ce runbook et `deploy/prod-stacks.json`
donnent la seule marge de manœuvre qui reste : **pouvoir basculer l'API prod
sur un second service** quand le premier tombe, sans jamais toucher aux
données.

## 2. Ce qui bascule et ce qui ne bascule PAS

| Élément | Bascule ? | Mécanisme |
|---|---|---|
| API (Render) | ✅ oui | ce runbook / bouton Super Admin |
| Web client (Vercel) | ✅ oui, manuellement | re-projet Vercel de la stack, puis `deploy-prod.yml` |
| Admin (Cloudflare Pages) | ✅ oui, manuellement | projet Pages de la stack, puis `deploy-prod.yml` |
| **Base de données (Neon)** | ❌ **jamais via une bascule de stack** | `db-standby-failover.yml` (RPO ≤ 6 h) |
| Redis / cache (Upstash) | ❌ hors périmètre | `CACHE_STORE` retombe sur `file` sur une stack sans KV |
| Backups (Backblaze B2) | ❌ hors périmètre | `database-backup.yml` (quotidien, chiffré age) |

**Règle d'or :** une stack prod est un ensemble *d'API et de fronts*. La base
reste unique. Deux stacks qui écrivent dans deux bases différentes, c'est deux
plateformes divergentes — le pire résultat possible, pire qu'une panne.

## 3. Inventaire des stacks

Source de vérité : [`deploy/prod-stacks.json`](../../deploy/prod-stacks.json).

| Stack | Statut | Render | Vercel | Cloudflare Pages | Base |
|---|---|---|---|---|---|
| **a** (active) | `ready` | `leopardo-prod` (free, frankfurt) | `leopardo-prod` | `leo-admin-prod` | Neon `LEOPARDO` (eu-central-1) |
| **b** | `planned` | `leopardo-prod-b` — compte ibrahim.ali, **0 service** | *à créer* (team_VHKD) | *optionnel* | **la même que a** |

`scripts/stack-health.sh --tier prod --pretty` donne l'état réel à l'instant T.

## 4. Bascule manuelle

### 4.1 Par le bouton Super Admin (recommandé)

Admin → **Système** → carte « Infrastructure — stacks » → bouton
**« Basculer vers b »**.

Le bouton appelle `POST /api/v1/platform/infra/stack-switch` (permission
`infra.manage`), qui émet un `repository_dispatch` `stack-switch`. Ce détour
est volontaire : le workflow détient les secrets, l'API n'en a aucun.

Sans `GITHUB_STACK_SWITCH_TOKEN` sur le service API, le bouton est désactivé
et l'écran l'indique — on ne laisse jamais croire qu'une bascule a eu lieu.

### 4.2 En ligne de commande

```bash
gh workflow run stack-switch.yml -f tier=prod -f stack=b
# ou, en local avec gh authentifié :
scripts/stack-switch.sh b --tier prod --dry-run   # simulation
scripts/stack-switch.sh b --tier prod
```

Le script **refuse** de basculer si :

- la cible n'a pas de `api.base_url` (stack non provisionnée) ;
- la cible n'est pas `ready`/`active` au registre ;
- la cible ne répond pas 200 sur `/api/v1/health` ;
- la cible déclare un `db.host` **différent** de la stack active ;
- on demande `--seed` (interdit en prod) ou `--force` seul.

### 4.3 Ce que la bascule automatise

Variables du dépôt, dans cet ordre :

```
PROD_ACTIVE_STACK        = b
PROD_API_BASE_URL        = <api de b>
PROD_WEB_URL             = <web de b>
PROD_ADMIN_URL           = <admin de b>
PROD_RENDER_SERVICE_URL  = <host de b>
```

Puis une issue `[stack-switch]` est créée (succès ou échec) : l'historique des
bascutes est lisible dans l'onglet Issues, pas seulement dans Actions.

### 4.4 Ce qui reste MANUEL — ne pas l'oublier

1. **Retirer les verrous de standby** du service Render de la cible :
   `WEB_QUEUE_DRAIN=false` → `true`, `WEB_SCHEDULER_LOOP=false` → `true`.
   Sans ça : plus de jobs en file, plus de scheduler (relances, exports,
   notifications) en prod. C'est le piège n°1 de cette bascule.
2. **Fronts** : si la cible a ses propres projets Vercel/Pages, pointer
   `VERCEL_PROD_*` / `CLOUDFLARE_PROD_*` dessus puis rejouer `deploy-prod.yml`.
   Sinon, les fronts de A continuent d'appeler l'API **via leur proxy**
   `/api/v1/*` : vérifier que ce proxy cible bien l'API active.
3. **Remettre les verrous à `false` sur l'ancienne stack** (elle redevient
   standby, elle ne doit plus draîner la queue partagée).
4. **Aligner le registre** : `active_stack: "b"`, statuts `a: ready`, `b: active`
   dans `deploy/prod-stacks.json`, en PR (la branche `main` est protégée).
5. **Smoke test** : `/api/v1/health`, une connexion web, une page de l'admin,
   et — si les bêta-testeurs sont actifs — un parcours candidat complet.

### 4.5 Rollback

```bash
scripts/stack-switch.sh a --tier prod
```
Même procédure dans l'autre sens (verrous, fronts, registre). La base n'a
jamais bougé : le rollback ne perd aucune donnée.

## 5. Onboarding d'une nouvelle stack prod

1. Créer le service Render depuis [`render.prod-b.yaml`](../../render.prod-b.yaml)
   (copier `DB_URL` et `APP_KEY` de la stack active — **les mêmes valeurs**).
2. Poser `RENDER_PROD_API_KEY_STACK_<X>` et `RENDER_PROD_SERVICE_ID_STACK_<X>`
   dans les secrets du dépôt.
3. Écrire l'entrée dans `deploy/prod-stacks.json` avec `status: "ready"`.
4. Vérifier : `scripts/stack-health.sh --tier prod --stack b --pretty`.
5. Faire une **bascule à blanc** (`--dry-run`) puis une vraie bascule hors
   heures creuses, avec un rollback prêt.

## 6. Bascule automatique — état et limites

`prod-keepalive.yml` sonde la prod **toutes les 10 minutes**, uniquement dans
la fenêtre **05:00–23:00 UTC** (voir §7). Le watchdog :

- **alerte d'abord** : ouvre (ou met à jour) une issue `[prod-watchdog]` ;
- **ne bascule pas tout seul par défaut** : `AUTO_SWITCH=0`. Une bascule
  automatique sur un faux positif (cold start Render de 50 s sur un service
  free) couperait la prod deux fois au lieu d'une. L'activation se fait en
  connaissance de cause, avec un cooldown de 6 h.

Raison de fond : sur Render free, un service endormi répond lentement au
premier appel. Un watchdog qui bascule sur « lent » crée des incidents qu'il
prétend éviter. La bascule automatique n'a de sens qu'avec un plan payant.

## 7. Le vrai risque : les quotas, pas les pannes

| Ressource | Offre gratuite | Ce qui l'a déjà cassé |
|---|---|---|
| Render web service | **750 h/mois** | suspension prod 28/09 → 01/10. Un service pingé 24/7 consomme ~730 h à lui seul ; **deux services pingés 24/7 dépassent le quota du compte**. |
| Vercel déploiements | **100 déploiements/jour** (free) | prod figée depuis le 20/09 (`api-deployments-free-per-day`) |
| Neon | 100 CU-h, 5 Go de transfert | consomme aussi via la queue `database` partagée |
| Cloudflare Pages | 500 builds/mois | à surveiller si l'admin est reconstruit à chaque push |

**Conséquence opérationnelle :** la fenêtre de ping de la prod
(`prod-keepalive.yml`, 05:00–23:00 UTC) et de la supervision de queue
(`queue-supervision.yml`) existe **pour ne pas brûler 24 h/jour de quota** sur
un service gratuit. Un ping nocturne n'apporte aucune valeur si personne ne
regarde — et il coûte la moitié du quota mensuel.

## 8. Checklist incident « prod suspendue »

1. `scripts/stack-health.sh --tier prod --pretty` → `render_suspended_hint`.
2. Vérifier la facturation du compte Render (suspension = quota, pas code).
3. Si une stack B existe et est saine → **bascule** (§4).
4. Sinon : issue `[prod-watchdog]` + communication bêta-testeurs (page de
   statut à prévoir — angle non couvert aujourd'hui).
5. Post-mortem : **quelle consommation a dépassé le quota** (trafic réel ?
   ping 24/7 ? déploiements ?). Sans cette réponse, l'incident se répétera.

---

_Réf. : épic #8670, issues #8671 / #8672. Historique des décisions et données
mesurées : [`COUTS_ET_PERF.md`](COUTS_ET_PERF.md)._
