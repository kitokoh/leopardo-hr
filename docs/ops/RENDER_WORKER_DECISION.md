# Décision — services worker/scheduler dédiés en production (#8147)

> **Statut de l'issue : BLOQUÉE côté provisionnement, DÉBLOQUÉE côté signal.**
> Décision DevOps/PM du 2026-10-26. Ce document enregistre l'arbitrage et ce
> qui a été livré par du code.

## 1. Le problème réel

En production Render **free-tier**, la queue et le scheduler tournent **dans le
conteneur web** (`api/docker-entrypoint.sh`, gates `WEB_QUEUE_DRAIN` /
`WEB_SCHEDULER_LOOP`, défaut `on`). Ce conteneur **s'endort après ~15 min
d'inactivité**.

Conséquences observées :

| Conséquence | Effet |
|---|---|
| Le scheduler s'arrête avec le conteneur | **Toute tâche planifiée due pendant le spin-down est perdue** — pas de rattrapage |
| La queue n'est drainée qu'au réveil | Latence de traitement non bornée ; `failed_jobs` peut gonfler |
| Aucun signal rouge | La prod est restée **~2 jours** derrière `main` sans alerte (#8092) |

## 2. Ce qui bloque, précisément

Le provisionnement a été **tenté via l'API Render le 2026-09-19**
(`POST /v1/services`, `type: background_worker`, `plan: starter`) : **refus HTTP
402 « Payment information is required »**.

Les *Background Workers* ne sont **pas éligibles au tier gratuit**. Le blocage
n'est donc ni technique ni documentaire : il tient à l'absence d'un moyen de
paiement sur le workspace prod.

## 3. Décisions prises

### D1 — Ne pas dé-commenter le bloc worker de `render.prod.yaml`

Le bloc `leopardo-queue-worker-prod` existe, **commenté**, comme état *cible*
(#7845). Le dé-commenter sans moyen de paiement produirait deux dégâts :

1. **Le blueprint mentirait.** `render.prod.yaml` documente explicitement
   « l'**état provisionné** » ; un blueprint sync échouerait (402) ou, pire,
   laisserait croire qu'un worker tourne.
2. **La garde de parité casserait.** `dev-hub/tools/check-render-worker-env-parity.sh`
   extrait les clés du worker **des lignes commentées** (`^#\s*- key:`). Le
   dé-commentaire viderait son extraction côté prod → la garde signalerait
   « MANQUANT côté worker prod : … » et échouerait.

Le bloc reste donc en commentaire, **mis à jour et vérifié** (33 clés dev ↔ prod).

### D2 — Livrer la moitié qui ne dépend d'aucun budget : l'alerte à 1 h

Le critère d'acceptation n°3 de #8147 est « l'alerte de drift se déclenche sur
un retard simulé > 1 h ». La garde existante (`deploy-drift-guard.yml`, job
`drift`) n'échoue qu'à **24 h** — seuil du DoD de #8092 (visibilité), pas de
#8147 (réactivité).

**Livré :** job `prod-drift-alert` dans `deploy-drift-guard.yml` + script
testable `dev-hub/tools/check-prod-drift-age.sh`.

- Seuil par défaut **1 h**, surchargeable par `vars.PROD_DRIFT_ALERT_HOURS`.
- **Âge du retard** = ancienneté du **plus vieux commit non déployé**
  (`commits[0]` du compare) — et non celle du commit déployé. Mesurer l'âge du
  commit déployé produirait une alerte permanente sur une prod déployée il y a
  longtemps, donc une alerte qu'on apprend à ignorer.
- **Aucun credential** : la santé de la prod est publique
  (`/api/v1/health` → `.version`), l'API GitHub utilise `github.token`.
  Conforme à l'audit #7694 (plus aucun secret prod dans un runner).
- **Non bloquant au merge** : planifié, hors PR — il n'allonge aucun cycle.
- **Dégradation explicite** : si `vars.PROD_RENDER_SERVICE_URL` manque, le job
  émet un `::notice` nommant la variable, plutôt qu'un vert silencieux.

Vérifié par trois cas (`--fixture`) : retard 2 h / seuil 1 h → échec (1) ;
retard 2 h / seuil 24 h → succès (0) ; aucun écart → succès (0).

### D3 — Ne pas provisionner un service `cron` à la place

Un service `type: cron` Render ne consomme que le temps d'exécution, mais il
**exécute une commande, pas un démon** : il ne peut pas drainer la queue en
continu. Il ne remplacerait donc que le scheduler, pas le worker, et Render ne
facture pas moins le worker restant. Écarter : cela déplacerait la dépense sans
supprimer le besoin.

### D4 — Coût : la décision est budgétaire, pas technique

| Option | Coût mensuel | Couvre |
|---|---|---|
| **Un seul worker `starter`** (queue:work + schedule:work dans le même conteneur) | **~7 $** | Queue **et** scheduler |
| Statu quo (intérim dans le web free) | 0 $ | Rien de fiable : tâches perdues au spin-down |

Le worker **fusionné** est retenu comme cible (l'ancien second service
`leopardo-scheduler-prod` a été fusionné) : ~7 $/mois pour les deux fonctions.

**Recommandation au propriétaire : provisionner le worker `starter`.** Le coût
est inférieur à une heure d'ingénierie par mois, alors que des échéances de
paie, de facturation et de purge non exécutées sont un risque produit et
juridique (documents à conservation légale). À défaut de paiement, **D2 rend le
retard visible** — ce qui est le seul levier disponible sans budget.

## 4. Critères d'acceptation de #8147 — état

| # | Critère | État |
|---|---|---|
| 1 | Un job planifié de test s'exécute **à l'heure** pendant 7 jours en prod | ❌ **bloqué** — exige le worker provisionné |
| 2 | La queue ne dépend plus du cycle de vie du conteneur web | ❌ **bloqué** — même cause |
| 3 | L'alerte de drift se déclenche sur un retard simulé > 1 h | ✅ **livré et vérifié** |
| 4 | Coût mensuel réel documenté vs estimation | ✅ **documenté** (§D4 : ~7 $, à confirmer après provisionnement) |

## 5. Procédure de déblocage (quand le paiement est ajouté)

```bash
# 1. Vérifier que le moyen de paiement est actif
RENDER_API_KEY=... dev-hub/tools/render-verify-services.sh

# 2. Dé-commenter le bloc « leopardo-queue-worker-prod » de render.prod.yaml
#    (ainsi que le bloc jumeau dev de render.yaml, pour garder la parité),
#    PUIS adapter la garde de parité aux blocs actifs :
#    dev-hub/tools/check-render-worker-env-parity.sh (extraction ^#\s*- key:)

# 3. Provisionner (blueprint sync ou API), puis basculer les gates
#    WEB_QUEUE_DRAIN=false et WEB_SCHEDULER_LOOP=false sur leopardo-prod
#    — sinon DOUBLE consommation de la queue.

# 4. Vérifier pendant 7 jours : le job canary doit s'exécuter à l'heure.
```

**Point d'attention bloquant pour l'étape 2** : la garde de parité doit être
adaptée **dans le même commit** que le dé-commentaire, sinon `architecture-check`
échoue. C'est une raison de plus de ne pas dé-commenter à moitié.

## 6. Rollback

Retirer le job `prod-drift-alert` de `deploy-drift-guard.yml` : le signal
disparaît, aucune donnée n'est touchée. Le provisionnement du worker se
retourne en retirant les deux gates `WEB_*` (retour à l'intérim).

## Références

- Issue #8147 · #7649 (décision de financement) · #7845 (audit topologie) · #8092 (leçon : 2 j de retard invisible)
- `render.prod.yaml` (en-tête « INTÉRIM ASSUMÉ » et plan de sortie)
- `docs/ops/RENDER_SECRETS_CHECKLIST.md` (#8397 — l'autre moitié, les secrets)
- `dev-hub/tools/check-prod-drift-age.sh`, `.github/workflows/deploy-drift-guard.yml`
