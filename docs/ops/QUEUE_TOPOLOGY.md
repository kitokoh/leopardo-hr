# Topologie d'exécution — web, queue, scheduler (#9976)

> **Statut au 2026-11-27 : DÉGRADÉ ET DÉCLARÉ.** La queue et le scheduler
> tournent dans le conteneur web, sur un plan `free` qui s'endort. Ce document
> décrit l'état réel, la cible, et la procédure exacte de bascule.

## 1. L'état réel, sans euphémisme

| Composant | Où il tourne | Plan | S'endort ? |
|---|---|---|---|
| API HTTP | `gestionemployerbackend` (web) | `free` | **oui**, ~15 min d'inactivité |
| `queue:work` | **dans le conteneur web** (`WEB_QUEUE_DRAIN=on`) | `free` | **oui** |
| `schedule:work` | **dans le conteneur web** (`WEB_SCHEDULER_LOOP=on`) | `free` | **oui** |

Le worker dédié (`leopardo-queue-worker`) est **écrit et prêt** dans
`render.yaml`, mais **commenté** : son provisionnement est refusé en 402
« Payment information is required » (#7649). Ce n'est pas un choix
d'architecture, c'est une contrainte de facturation.

### Ce que cela coûte, concrètement

Le produit vend de la **paie** et de la **facturation**. Sur ce périmètre :

- un job de calcul de paie, d'export PDF ou de réconciliation mis en queue
  juste avant le spin-down **ne tourne pas** jusqu'au prochain réveil ;
- les tâches planifiées (relances, facturation mensuelle, alertes carburant,
  acquisition de congés) **ne se déclenchent pas** pendant l'endormissement ;
- `queue:work` partage CPU et mémoire avec les requêtes HTTP : un pic de jobs
  dégrade la latence API.

Une paie non calculée n'est pas une dégradation cosmétique : c'est un **défaut
fonctionnel silencieux**, découvert par le client.

## 2. Ce qui a été fait pour que la panne ne soit plus silencieuse

Depuis #9976, l'état est **mesuré** et **alerté** :

| Signal | Où | Seuil |
|---|---|---|
| `checks.scheduler.last_slot_age_seconds` | `/api/v1/health` | alerte > 20 min |
| `checks.scheduler.status` | `/api/v1/health` | `observed` attendu |
| `checks.queue.backlog` | `/api/v1/health` | alerte sur backlog |
| `checks.queue.oldest_reserved_seconds` | `/api/v1/health` | jobs réservés trop longtemps |

`queue-supervision.yml` interroge `/api/v1/health` **sans credentials**
(#7694) et ouvre une alerte. Un scheduler mort ne reste donc plus vert.

## 3. La cible

```
┌──────────────────────┐   ┌──────────────────────────┐
│ web (starter)        │   │ leopardo-queue-worker    │
│ HTTP uniquement      │   │ (starter)                │
│ WEB_QUEUE_DRAIN=false│   │ schedule:work  (arrière) │
│ WEB_SCHEDULER_LOOP=  │   │ queue:work     (exec)    │
│   false              │   │ RUN_MIGRATIONS=false     │
└──────────────────────┘   └──────────────────────────┘
```

Un seul worker porte les deux processus : `schedule:work` en arrière-plan,
`queue:work` en `exec`. Sa sortie (`--max-time=3600` ou crash) termine le
conteneur, Render le redémarre, **les deux** repartent. Un second service
payant est ainsi évité.

`RUN_MIGRATIONS=false` sur le worker est **obligatoire** : sinon chaque
redémarrage rejoue migrations + seed (course de migrations, seed non
idempotent).

## 4. Procédure de bascule — dans cet ordre

> Ne pas inverser les étapes 2 et 3 : couper le drain web avant que le worker
> existe laisserait la queue sans consommateur.

1. **Ajouter un moyen de paiement** au workspace Render (débloque le 402).
2. **Décommenter** le bloc `type: worker` de `render.yaml` (et l'équivalent
   `render.prod.yaml`), puis synchroniser le blueprint.
3. **Vérifier que le worker tourne** : logs Render → `schedule:work` et
   `queue:work` actifs, aucune erreur de connexion DB/Redis.
4. **Poser sur le service web** : `WEB_QUEUE_DRAIN=false`,
   `WEB_SCHEDULER_LOOP=false`. Redéployer le web.
5. **Vérifier** : `curl /api/v1/health | jq .checks.scheduler` →
   `status: observed`, `last_slot_age_seconds` < 300.
6. **Retirer le marqueur `QUEUE_DEGRADED`** de l'en-tête de `render.yaml`.
   La garde `check-queue-strategy-coherence.sh` exige alors que les deux gates
   soient à `false` — elle refuse le retour d'un drain web silencieux.
7. **Passer le web en `starter`** (sinon il s'endort encore, et l'API avec).

## 5. Rollback

Remettre `WEB_QUEUE_DRAIN=on` et `WEB_SCHEDULER_LOOP=on` sur le web, redéployer,
puis remettre le marqueur `QUEUE_DEGRADED`. Le worker peut rester provisionné :
il ne consomme plus rien si le web draine aussi — mais **ne pas laisser les
deux** : deux consommateurs sur la même queue doublent le débit de traitement
et faussent les métriques de backlog.

## 6. Ce qui reste hors périmètre du code

| Action | Où | Pourquoi ce n'est pas dans le dépôt |
|---|---|---|
| Ajouter un moyen de paiement | Dashboard Render → Billing | Décision propriétaire, données bancaires |
| Provisionner le worker | Blueprint sync Render | Refusé 402 tant que le billing n'est pas réglé |
| Passer le web en `starter` | Dashboard Render | Coût récurrent, décision propriétaire |
| Vérifier `checks.scheduler` après bascule | `curl /api/v1/health` | Nécessite l'URL de prod et un déploiement réel |

Le code est prêt ; seule la décision de facturation manque.
