# Runbook — actions propriétaires restantes (2026-10-29)

> **Pourquoi ce document existe.** Quatre issues du backlog (#7967, #7994, #8285,
> #8191) décrivent des blocages qui **ne sont pas du code manquant**. Le code est
> livré, vérifié, et parfois déjà sur `main` depuis plusieurs jours — mais leur
> critère d'acceptation exige un geste humain : un secret, un plan payant, un
> réglage de dashboard, un snapshot de production.
>
> Ce runbook est le **point d'entrée unique** pour ces quatre issues. Il remplace
> quatre lectures de tickets par un tableau action → preuve → rollback.
>
> Règle de clôture (décision PM, 2026-10-29) : **aucune de ces issues ne se ferme
> sur du code.** Elles se ferment sur la preuve de succès listée ci-dessous.

---

## 0. Tableau de bord

| Issue | Ce qui est livré (code) | Ce qui reste (propriétaire) | Effort | Bloquant pour |
|---|---|---|---|---|
| **#7994** — fronts Vercel prod | `marketplace-deploy.yml` (préview + prod + healthcheck), skip **bloquant** sur push main, drift guard `fronts-vercel` | Poser 2 jeux de secrets Vercel | ~10 min | Serving du correctif #7963 en prod |
| **#7967** — Render queue/scheduler | Rattrapage des tâches perdues (PR #9033), garde `check-render-app-key-pinned.sh` | Passer queue + scheduler sur un worker payant | ~15 min + coût | Fiabilité paie / facturation |
| **#8285** — Cloudflare Workers Builds | Aucun code requis (l'app Cloudflare est hors périmètre du repo) | Débrancher `kitokoh/leopardo-hr` du Worker `gestionemploye` | ~3 min | Chaque push `main` produit un run rouge |
| **#8191** — audit scheduler-duplicates | Outil `audit:scheduler-duplicates` livré (PR #8190) | Fournir un snapshot prod + valider les corrections `--execute` | 2 j (runbook) | Nettoyage des doublons de tâches planifiées |

---

## 1. #7994 — secrets Vercel des fronts

### État constaté

- `travel-web-deploy.yml` : job production **existe** et fonctionne, mais toutes
  ses étapes sont `skipped` quand `VERCEL_TRAVEL_PROD_*` est absent. Depuis
  #7994 §4, ce skip est **bloquant** sur `push main` (`::error` + `exit 1`) :
  le retard est désormais visible au lieu d'être masqué par un run vert.
- `marketplace-deploy.yml` : **créé** (préview + production + healthcheck +
  enregistrement du GitHub Deployment). Même politique de skip que travel-web.
- `deploy-drift-guard.yml` : job `fronts-vercel` compare le SHA du dernier
  GitHub Deployment à `main`, sans aucun secret Vercel.

### Action propriétaire

Poser les **6 secrets** (trois par front) :

```bash
# travel-web
gh secret set VERCEL_TRAVEL_PROD_TOKEN     # token Vercel, scope projet leopardo-travel-prod
gh secret set VERCEL_TRAVEL_PROD_ORG_ID    # team id Vercel
gh secret set VERCEL_TRAVEL_PROD_PROJECT_ID

# marketplace
gh secret set VERCEL_MARCHE_PROD_TOKEN
gh secret set VERCEL_MARCHE_PROD_ORG_ID
gh secret set VERCEL_MARCHE_PROD_PROJECT_ID
```

> Ne **pas** réutiliser un token voué à révocation (#7994 §1). Le runbook Vercel
> détaillé (création du token, portée, rotation) reste `docs/ops/travel-web-vercel.md`.

### Preuve de succès

```bash
# 1. le job prod n'est plus en skip
gh run list --workflow=travel-web-deploy.yml  --limit 1 --json conclusion,headSha
gh run list --workflow=marketplace-deploy.yml --limit 1 --json conclusion,headSha

# 2. les deux fronts servent bien un build récent
bash dev-hub/tools/check-vercel-front-drift.sh --label travel-web  --path front/travel-web
bash dev-hub/tools/check-vercel-front-drift.sh --label marketplace --path front/marketplace
```

Critère : les deux commandes de drift sortent `OK` (déployment ≥ `main`), et les
deux jobs prod sont `success` **sans** étape `Skip — secrets absents`.

### Rollback

Les déploiements Vercel sont réversibles depuis le dashboard (`Promote` sur le
déploiement précédent). Aucun état en base n'est modifié par ce workflow.

---

## 2. #7967 — Render `plan: free` avec spin-down

### État constaté

Le blueprint `render.prod.yaml` place **queue worker et scheduler dans le même
web service** que l'API, sur un plan gratuit. Un plan gratuit **s'endort** : le
scheduler ne tire pas les tâches dues, et le worker ne draine pas la queue. Les
jobs ne sont pas « lents », ils sont **perdus**.

Code déjà livré :
- `fix/7967-scheduler-missed-run-catchup` (PR #9033) — rattrapage des tâches
  planifiées dont la fenêtre est tombée pendant le spin-down ;
- `dev-hub/tools/check-render-app-key-pinned.sh` — garde CI : `APP_KEY` doit
  rester `sync: false` (une régénération invalide sessions et valeurs chiffrées).

### Action propriétaire

1. Souscrire un **worker starter** (~7 $/mois) dans Render.
2. Déplacer `queue` + `scheduler` de `render.prod.yaml` vers ce worker.
3. Basculer `QUEUE_CONNECTION` sur `redis`/`database` **du worker**, pas du web.

### Preuve de succès

```bash
# dans le shell du worker Render
php artisan queue:work --once && php artisan schedule:list
```

Critère : une tâche due s'exécute **sans** que le web service ait été réveillé,
et `schedule:list` montre les tâches attendues avec leur prochaine échéance.

### Rollback

Revenir au blueprint précédent (queue + scheduler dans le web service) : les
tâches restent dans la table `jobs`, aucune n'est perdue — seulement retardée.

---

## 3. #8285 — Cloudflare Workers Builds

### État constaté

L'application GitHub Cloudflare est branchée sur un Worker `gestionemploye` du
compte **dev**, avec « Workers Builds » activé, alors qu'**aucune configuration
wrangler n'existe dans ce dépôt**. Résultat : un check `failure` en **0 s** à
chaque push sur `main`. Ce n'est pas un échec de notre code, c'est une
intégration tierce qui s'exécute sur un dépôt qu'elle ne connaît pas.

### Action propriétaire

Dashboard Cloudflare → Workers & Pages → `gestionemploye` → Settings →
**déconnecter le dépôt** `kitokoh/leopardo-hr` (ou désactiver Workers Builds
pour ce Worker). Aucun changement dans le dépôt n'est nécessaire ni suffisant.

### Preuve de succès

```bash
gh api repos/Kitokoh/leopardo-hr/commits/main/check-runs \
  --jq '.check_runs[]|select(.name|test("[Ww]orkers"))|.conclusion'
```

Critère : plus aucun check-run `Workers Builds: gestionemploye` sur les nouveaux
commits de `main` (sortie vide).

### Rollback

Rebrancher le dépôt dans le dashboard si le Worker redevient utile — aucune
trace côté repo.

---

## 4. #8191 — audit `scheduler-duplicates` sur copie prod

### État constaté

L'outil `php artisan audit:scheduler-duplicates` est livré (PR #8190) et tourne
en **lecture seule** par défaut. L'issue demande de l'exécuter sur une **copie
de production** puis d'appliquer les corrections validées (`--execute`).

### Action propriétaire

1. Fournir un snapshot de la base de production (jamais la prod elle-même).
2. Lancer l'audit sur la copie, joindre la sortie à l'issue :

```bash
php artisan audit:scheduler-duplicates --format=json > audit-duplicates.json
```

3. Relire chaque correction proposée **une par une**, puis seulement :

```bash
php artisan audit:scheduler-duplicates --execute --only=<ids validés>
```

### Preuve de succès

Un second audit sur la même copie renvoie **0 doublon**, et le diff des lignes
supprimées est archivé en pièce jointe de l'issue.

### Rollback

`--execute` supprime des entrées de `...jobs`/`...scheduled_tasks` : le snapshot
est la sauvegarde. Rejouer le snapshot restaure l'état exact.

---

## 5. Décisions PM attachées

1. **Aucune fermeture sur du code.** Les quatre issues restent ouvertes après
   livraison : leur critère d'acceptation est une preuve d'exploitation, pas un
   diff. Fermer sur un merge reproduirait le défaut d'origine — un backlog qui
   paraît vide alors que la production ne sert pas le correctif.
2. **Le skip silencieux est un bug.** C'est la leçon de #7994 : un pipeline vert
   qui ne déploie rien est plus dangereux qu'un pipeline rouge. Les deux
   workflows de front échouent maintenant bruyamment sur `push main`.
3. **Pas de contournement d'infrastructure par du code.** #8285 ne se corrige
   pas dans le dépôt ; #7967 ne se corrige pas sans plan payant. Toute tentative
   de « rustine » (cron GitHub remplaçant le scheduler, par exemple) crée une
   dépendance plus fragile que le problème d'origine.
4. **Ordre de traitement** : #8285 (3 min, supprime un faux rouge sur chaque
   push) → #7994 (10 min, sert un correctif de sécurité en prod) → #7967
   (coût récurrent, à budgéter) → #8191 (2 j, nécessite un snapshot).
