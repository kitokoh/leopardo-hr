# Render — topologie réelle vs blueprints (#7845)

> Audit du 2026-09-20, inspection **lecture seule** via l'API Render
> (`GET /v1/services`, `GET /v1/redis`, `GET /v1/postgres`) sur les deux
> workspaces. Complète l'issue #7845 (elle-même complémentaire de #7657).
> Aucun secret ici — uniquement type/plan/statut/région.

## 1. État réel constaté (API Render, 2026-09-20)

### Workspace PROD (« ALI MAHADI's workspace »)

| Ressource | Type | Plan | Statut | Région | Créée |
|---|---|---|---|---|---|
| `leopardo-prod` | web service (docker) | free | not_suspended | frankfurt | — |
| `leopardo-redis-prod` | Key Value (redis 8.1.4) | free | available | frankfurt | 2026-09-03 |

- **Aucun** background worker, **aucun** cron job, **aucun** Postgres Render.
- La base de données réelle est **Neon Postgres** (externe, hors Render).

### Workspace DEV (« africanovatech »)

| Ressource | Type | Plan | Statut | Région | Créée |
|---|---|---|---|---|---|
| `gestionemployerBackend` | web service (docker) | free | not_suspended | frankfurt | — |
| `leopardoai` | Key Value (redis 8.1.4) | free | available | frankfurt | 2026-04-12 |

- **Aucun** background worker, **aucun** cron job, **aucun** Postgres Render.
- Base réelle : Neon Postgres (externe), comme en prod.

**Total : 2 ressources par compte (1 web + 1 Key Value), tout en free-tier.**

## 2. Écarts blueprints ↔ réalité (avant #7845)

| # | Écart | Fichier (avant) | Réalité | Gravité |
|---|---|---|---|---|
| E1 | `leopardo-queue-worker-prod` déclaré comme service actif | `render.prod.yaml` (~l.185) | ❌ jamais provisionné (402 billing, #7649) | **Majeur** — IaC mensongère |
| E2 | `leopardo-queue-worker` déclaré comme service actif | `render.yaml` (~l.253) | ❌ jamais provisionné (402 billing) | **Majeur** |
| E3 | `leopardo-scheduler` déclaré (2e worker dev) | `render.yaml` (~l.389) | ❌ jamais provisionné, ET redondant : le worker E2 porte déjà `schedule:work` | **Majeur** + drift dev/prod (prod fusionne, dev séparait) |
| E4 | Blueprints présentés comme « source de vérité déclarative » | en-têtes des deux fichiers | Ils décrivaient une cible non provisionnée | Majeur (confiance IaC) |
| E5 | Redis dev réel `leopardoai` non déclaré | `render.yaml` | ✅ existe — volontairement hors blueprint (une seule instance free/workspace, un bloc blueprint recréerait un doublon) ; documenté en en-tête + commentaires `REDIS_URL sync: false` | Mineur (documentaire) |
| E6 | `leopardo-redis-prod` : l'issue #7845 le supposait inexistant | `render.prod.yaml` (databases) | ✅ **il existe bien** (créé 2026-09-03, available) — l'audit de l'issue est corrigé sur ce point | Aucun — blueprint conforme |
| E7 | Note prod « les tâches planifiées ne tournent pas » (stale, 2026-09-05) | en-tête `render.prod.yaml` | Depuis #7649, le scheduler tourne en intérim dans le web (`WEB_SCHEDULER_LOOP`) | Mineur (doc périmée) |

### Risque opérationnel réel (inchangé par ce correctif)

Queue **et** scheduler prod tournent en intérim dans le conteneur web
**free-tier** (`api/docker-entrypoint.sh`, gates `WEB_QUEUE_DRAIN` /
`WEB_SCHEDULER_LOOP`, défaut on). Le web free spin-down après ~15 min
d'inactivité → scheduler arrêté avec lui, tâches planifiées dues pendant le
spin-down **perdues** (pas de rattrapage), queue drainée seulement au réveil.
Supervision existante sans credentials : `queue-supervision.yml` (sonde HTTP).

## 3. Ce que fait le correctif #7845 (blueprints)

Principe retenu : **les blueprints décrivent l'état provisionné** ; l'état
cible bloqué par le billing est conservé en **blocs commentés** dé-commentables
tels quels.

- `render.prod.yaml` : bloc `leopardo-queue-worker-prod` **commenté** (cible),
  en-tête réécrit avec tableau déclaré/réel, section « INTÉRIM ASSUMÉ » avec
  plan de sortie en 3 étapes, note stale E7 corrigée, existence du redis prod
  confirmée (E6).
- `render.yaml` : bloc `leopardo-queue-worker` **commenté** (cible, worker
  fusionné queue+scheduler identique à la topologie prod) ; service
  `leopardo-scheduler` **supprimé** (redondant — résorbe le drift dev/prod E3
  et divise par deux le coût cible dev) ; en-tête réécrit avec tableau
  déclaré/réel.
- Aucun changement `api/` ni `.github/` — comportement runtime inchangé.

## 4. Topologie cible recommandée (symétrique dev/prod)

Par workspace : **1 web + 1 worker fusionné (queue:work + schedule:work) +
1 Key Value**. Postgres reste Neon (externe). Pas de service scheduler séparé.

## 5. Plan de remédiation chiffré (décisions propriétaire)

| Étape | Action | Coût | Risque si non fait |
|---|---|---|---|
| 0 (fait, #7845) | Blueprints alignés sur le réel, cible en commentaire | 0 $ | — |
| 1 — PROD (priorité) | Ajouter un moyen de paiement au workspace prod ; dé-commenter et provisionner `leopardo-queue-worker-prod` (starter) ; poser `WEB_QUEUE_DRAIN=false` + `WEB_SCHEDULER_LOOP=false` sur `leopardo-prod` | **~7 $/mois** | Scheduler prod perdu à chaque spin-down ; jobs traités avec latence de réveil |
| 1bis — alternative 0 $ | Assumer l'intérim : upgrade du seul web prod en starter (pas de spin-down) OU alerte si scheduler silencieux > X min (extension queue-supervision) | ~7 $/mois (upgrade web) ou 0 $ (alerte seule) | L'alerte constate la perte, ne l'empêche pas |
| 2 — DEV (optionnel) | Même opération sur le workspace dev (`leopardo-queue-worker`, starter) | ~7 $/mois | Acceptable en dev : l'intérim web suffit pour QA |
| 3 | Mettre à jour `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` + snapshots du script `dev-hub/tools/check-render-env-parity.sh` après tout provisionnement | 0 $ | Doc/outillage re-divergent |

Cible complète (2 workers starter, web prod starter) : **~21 $/mois** ;
minimum recommandé (étape 1 seule) : **~7 $/mois**.

## 6. Rien à supprimer côté Render

Aucun service orphelin constaté : tout ce qui existe est utilisé. Les
suppressions de #7845 sont uniquement **déclaratives** (blueprints).

## 7. #9976 — la dégradation est désormais DÉTECTÉE et DÉCLARÉE

> État du code au 2026-11-27. La section 5 ci-dessus (« alerte si scheduler
> silencieux > X min — 0 $, l'alerte constate la perte, ne l'empêche pas »)
> est désormais **implémentée** : l'option 1bis est en place, l'option 1 (worker
> payant) reste bloquée par le billing.

### Ce qui a changé dans le code

| Ajout | Où | Effet |
|---|---|---|
| `HealthProbeService::checkScheduler()` | `api/app/Modules/Platform/.../HealthProbeService.php` | `/health` expose `checks.scheduler.last_slot_age_seconds` — l'âge du dernier créneau de `scheduler_slot_runs` |
| Exposition dans les deux endpoints | `HealthController` (`/health`, `/health/ready`) | la sonde externe n'a plus besoin d'un accès SQL |
| Alerte sur scheduler mort | `queue-supervision.yml` (`SCHEDULER_MAX_STALE_MINUTES`, 20 min) | un scheduler arrêté déclenche `::error::` + Slack opt-in |
| Marqueur `QUEUE_DEGRADED:` | `render.yaml`, `render.prod.yaml` | la dégradation est écrite dans le blueprint, pas seulement dans un ticket |
| Gardes §7-8 | `check-queue-strategy-coherence.sh` | refuse un `plan: free` sans marqueur, et refuse un worker actif avec un gate encore `on` |

**Pourquoi 20 minutes.** Le spin-down Render survient après ~15 min
d'inactivité ; 20 min laisse la marge d'un redémarrage. En dessous, l'alerte
crierait à chaque réveil normal.

**Pourquoi `status: unknown` n'est pas une alerte.** Une base fraîchement
migrée n'a aucune ligne dans `scheduler_slot_runs` : ce n'est pas une panne.
La sonde renvoie `unknown` et n'échoue jamais — transformer une absence de
mesure en incident est la façon la plus rapide de faire ignorer les alertes.

### Ce qui reste impossible depuis le code

Le worker dédié. `POST /v1/services` avec `type: background_worker` répond
**402 « Payment information is required »** sur un workspace sans moyen de
paiement, et les Background Workers ne sont pas éligibles au tier gratuit
(#7649). **Décommenter le bloc worker sans carte produirait un échec de sync
du blueprint** — et, pire, laisserait croire la topologie corrigée.

C'est la seule action de cette mission qui exige un humain côté plateforme :
ajouter un moyen de paiement au workspace Render, puis suivre la procédure de
bascule ci-dessous.

### Procédure de bascule (propriétaire du compte Render)

1. Ajouter un moyen de paiement au workspace (prod prioritaire : ~7 $/mois).
2. Décommenter `  - type: worker` dans `render.yaml` / `render.prod.yaml`
   (plan `starter`). Le `dockerCommand` porte déjà `schedule:work` en
   arrière-plan + `exec queue:work` : les deux processus meurent ensemble et
   Render redémarre le conteneur.
3. Poser sur le web service :
   ```
   WEB_QUEUE_DRAIN=false
   WEB_SCHEDULER_LOOP=false
   ```
   **Sans ces deux lignes, le web continue de consommer la même file** — deux
   consommateurs, jobs volés, ordre non garanti. La garde §7 échoue si elles
   manquent : ce n'est pas une convention, c'est un contrôle CI.
4. Redéployer le web, puis vérifier :
   ```
   curl -s "$APP_URL/api/v1/health" | jq '.checks.scheduler, .checks.queue'
   ```
   Attendu : `status == "observed"` et un âge qui **avance** (interroger deux
   fois à 2 min d'intervalle ; un âge figé signifie que le ledger n'écrit plus).
5. Retirer le marqueur `QUEUE_DEGRADED:` des blueprints. La garde exige alors
   les deux gates à `false`.
6. Laisser le web inactif 30 min et vérifier que `scheduler_slot_runs` a
   **continué d'avancer** — c'est la seule preuve que le spin-down ne fait
   plus mal.

### Limite honnête de la supervision

`queue-supervision.yml` sonde entre 05:00 et 23:00 UTC (18 h/jour). **Hors de
cette fenêtre, personne ne sait si le scheduler tourne.** Élargir la fenêtre
coûterait le quota : 750 h/mois ≈ 31 j × 24 h, et deux consommateurs
permanents le dépassent *par construction* — c'est la cause mesurée de la
suspension prod du 2026-09-28 au 2026-10-01 (#8674).

C'est acceptable tant que la dégradation est assumée. Ça ne l'est plus dès
qu'un client dépend d'une facture émise la nuit. La correction définitive est
le worker dédié, pas un élargissement de la sonde.
