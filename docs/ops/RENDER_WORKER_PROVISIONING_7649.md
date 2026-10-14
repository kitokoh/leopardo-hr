# 🔑 Procédure propriétaire — worker de queue + scheduler Render (issue #7649)

**Version** : 1.0 · **Date** : 2026-09-19 · **Statut** : ⏳ **EN ATTENTE D'ACTION
PROPRIÉTAIRE** (billing Render). Remplace, pour ce sujet, le runbook historique
`docs/ops/RENDER_QUEUE_WORKERS.md` (marqué dépassé).

## Constat (audit 2026-09-19, vérifié via l'API Render)

- Les deux workspaces (dev `africanovatech`, prod `ALI MAHADI's workspace`) ne
  contiennent **qu'un seul web service** chacun : ni worker, ni cron, ni scheduler.
- La queue (`QUEUE_CONNECTION=database`) est drainée par une boucle `queue:work`
  en arrière-plan du conteneur web (`api/docker-entrypoint.sh`) : throughput lié
  au CPU web, jobs tués à chaque déploiement/spin-down.
- `php artisan schedule:run` ne tourne nulle part en dehors des workflows GitHub.
- La création des workers via l'API Render a été **tentée le 2026-09-19** et a
  échoué en `402 Payment information is required` sur les DEUX workspaces
  (détail : PR #7819). **Aucun moyen de paiement n'est enregistré** — les
  background workers Render sont inéligibles au tier gratuit.

## Ce qui est déjà prêt côté code

- `render.yaml` (dev) et `render.prod.yaml` (prod) déclarent le(s) worker(s)
  `queue:work` + la boucle `schedule:run` — consolidés par la PR #7819 (un seul
  worker starter par tier, commande combinée queue+scheduler, env alignées sur
  le web service, `RUN_MIGRATIONS=false`).
- Le drain web intérimaire devient débrayable (`WEB_QUEUE_DRAIN`,
  `WEB_SCHEDULER_LOOP`, défaut `true`) — PR #7819.
- Le contexte tenant des jobs est verrouillé : middleware
  `EnsureTenantContext` généralisé + test d'architecture
  `api/tests/Unit/Architecture/QueueTenantContextArchitectureTest.php`
  (cette PR, Refs #7649) — un worker dédié peut traiter les jobs de tous les
  tenants sans risque de fuite de contexte.
- La supervision est déjà une pure alarme sans credentials :
  `queue-supervision.yml` (le drain CI `queue-worker-fallback.yml` a été
  **supprimé** par #7694 — un CI ne draine plus jamais la prod).

## Procédure (propriétaire uniquement — ~15 min)

1. **Billing** : ajouter un moyen de paiement sur chaque workspace →
   <https://dashboard.render.com/billing> (coût attendu : 1 worker starter
   ≈ 7 $/mois par tier).
2. **Provisionner** (au choix) :
   - `render blueprint sync` (dashboard → Blueprints → sync sur le repo, branche
     `main`, fichier `render.yaml` pour le dev / `render.prod.yaml` pour la prod —
     dé-commenter le bloc worker prod si encore commenté au moment du sync) ;
   - ou rejouer l'appel API documenté dans la PR #7819
     (`POST /v1/services`, type `background_worker`).
3. **Vérifier** : le worker apparaît dans le workspace, ses logs montrent
   `Processing:` sur les queues `webhooks,audit,notifications,emails,pdf,payroll,documents,default`
   et une exécution de `schedule:run`/`schedule:work` chaque minute.
4. **Débrayer le drain web** : poser `WEB_QUEUE_DRAIN=false` et
   `WEB_SCHEDULER_LOOP=false` sur le **web service** (évite un double
   consommateur sur la table `jobs` et une double exécution des tâches
   planifiées non `onOneServer()`), puis redéployer le web.

## Migration `QUEUE_CONNECTION=redis` (APRÈS le worker, pas avant)

Prérequis : worker dédié en place et stable (sinon on migre un problème).

1. Provisionner un Redis : Render Key Value (plan free suffisant au début) dans
   le même workspace/région (Frankfurt), ou Upstash.
2. Sur le **web service ET le worker** : `REDIS_URL=<url interne>` puis
   `QUEUE_CONNECTION=redis` (predis est déjà dans `composer.json` ;
   `config/queue.php` et `config/database.php` savent déjà lire `REDIS_URL`).
3. Mettre à jour les blueprints (`render.yaml`/`render.prod.yaml`) et la garde
   `dev-hub/tools/check-queue-strategy-coherence.sh` dans la même PR — la
   cohérence database/redis y est verrouillée (#5578).
4. Déployer worker puis web ; laisser la table `jobs` se vider (les jobs
   database restants sont consommés tant que l'ancien drain tourne) ; vérifier
   `failed_jobs` à J+1.
5. Bénéfice : plus de polling SQL + verrous sur la même instance Neon que
   l'OLTP tenants.

## Suivi

- Issue #7649 : reste **ouverte** tant que le provisioning (étapes 1–4) n'est
  pas fait — les volets code sont livrés par #7819 et la présente PR.
- Voir aussi : `docs/ops/ACTIONS_PROPRIETAIRES.md` (décision n°7 « Plan
  Render »), `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`.
