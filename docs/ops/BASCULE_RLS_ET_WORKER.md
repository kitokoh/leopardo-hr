# Note de bascule production — RLS effectif, worker dédié

> **Public** : opérateur qui exécute la bascule. Chaque commande est copiable.
> **Issues** : #9974 (rôle applicatif), #9975 (couverture RLS), #9976 (worker).
> **Référence** : `docs/adr/ADR-9879-rls-tenant-isolation.md`, `docs/ops/QUEUE_TOPOLOGY.md`.

---

## 0. Ce que cette note change, et ce qu'elle ne change pas

| Élément | Avant | Après |
|---|---|---|
| Rôle PostgreSQL applicatif | `leopardo` — **SUPERUSER** | `leopardo_app` — `NOSUPERUSER NOBYPASSRLS` |
| Politiques RLS | posées, **inertes** (bypass superuser) | **effectives** |
| Gate de déploiement | aucune sonde | `tenant:rls-status --strict` en `DEPLOY_TIER=prod` |
| Queue + scheduler | dans le conteneur web (s'endort) | worker Render dédié |

**Ce que cette note ne fait pas** : elle ne modifie aucune donnée métier, ne
supprime aucune politique, et ne touche pas au schéma. Le rollback est une
variable d'environnement (étape 5).

---

## 1. Pré-vol — mesurer l'état réel (5 min)

À exécuter **avant** toute modification, avec le rôle actuel :

```bash
# 1.1 Le rôle applicatif bypasse-t-il RLS ?
php artisan tenant:rls-status; echo "code=$?"
#   0 = RLS effectif (rien à faire, passer à l'étape 4)
#   1 = RLS BYPASSÉ  → c'est l'état attendu avant bascule
#   2 = rôle conforme mais couverture incomplète (normal, chantier #9975)
#   3 = driver non-PostgreSQL (sonde non applicable)

# 1.2 Quel rôle utilise l'application ?
psql "$DATABASE_URL" -c "SELECT current_user, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user;"

# 1.3 Combien de tables portent company_id, combien sont couvertes ?
bash api/database/scripts/rls/inventory-company-id-tables.sh | tail -5
```

**Point d'arrêt** : si 1.1 renvoie déjà `0`, la bascule est faite — ne pas
rejouer les étapes 2 et 3.

---

## 2. Créer le rôle applicatif (10 min)

Le script est **idempotent** : le rejouer ne casse rien (rotation de mot de
passe incluse).

```bash
# 2.1 Générer un mot de passe fort et le stocker dans le gestionnaire de secrets
#     (Render → Environment → Add Secret). NE JAMAIS le committer.
APP_DB_PASSWORD="$(openssl rand -base64 32)"

# 2.2 Exécuter le script avec le rôle PROPRIÉTAIRE (celui des migrations).
#     Il exige SUPERUSER ou CREATEROLE — c'est le rôle actuel.
psql "$DATABASE_URL" \
  -v app_password="'${APP_DB_PASSWORD}'" \
  -f api/database/scripts/rls/create-app-role.sql

# 2.3 Vérifier le résultat : NOSUPERUSER + NOBYPASSRLS, et les GRANT posés.
psql "$DATABASE_URL" -c \
  "SELECT rolname, rolsuper, rolbypassrls, rolcanlogin FROM pg_roles WHERE rolname = 'leopardo_app';"
#   attendu : leopardo_app | f | f | t
```

**Ce que le script fait** (vérifié par `RlsAppRoleBootstrapTest`) :
- crée le rôle `leopardo_app` avec `NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE` ;
- accorde `CONNECT` sur la base, `USAGE` sur les schémas `public` et `shared_tenants` ;
- accorde `SELECT/INSERT/UPDATE/DELETE` sur les tables existantes ;
- pose `ALTER DEFAULT PRIVILEGES` pour les tables **futures** (sans quoi chaque
  nouvelle migration créerait une table inaccessible) ;
- ne touche pas à `DB_USERNAME` — c'est l'étape 3.

**Si le script échoue** : il s'arrête sur `ON_ERROR_STOP` et n'a rien laissé à
moitié (garde `CREATEROLE` en tête). Lire le message, corriger, rejouer.

---

## 3. Basculer l'application (5 min + redéploiement)

```bash
# 3.1 Dans le dashboard Render → service API → Environment :
#       DB_USERNAME = leopardo_app
#       DB_PASSWORD = <le secret de l'étape 2.1>
#       DEPLOY_TIER = prod          (déjà posé normalement)
#       RLS_GATE_MODE = strict      (défaut en prod, peut rester implicite)

# 3.2 Redéployer. Le gate s'exécute au démarrage du conteneur.
```

**Lecture des logs de démarrage** :

```
[entrypoint] Sonde RLS (mode: strict)...
[entrypoint] RLS effectif : rôle non-bypass, tables attendues couvertes.
```

Si le gate refuse :

```
::error::[entrypoint] RLS est BYPASSÉ par le rôle applicatif (SUPERUSER/BYPASSRLS).
  Les politiques existent mais ne filtrent RIEN. Déploiement refusé (#9974).
```

→ `DB_USERNAME` n'a pas été pris en compte (variable posée sur le mauvais
service, ou cache de config). **Render garde la version précédente en ligne** :
le service n'est pas tombé, c'est le comportement voulu.

---

## 4. Vérifier que l'isolation est réelle (10 min)

```bash
# 4.1 La sonde doit renvoyer 0.
php artisan tenant:rls-status --strict; echo "code=$?"

# 4.2 Test fonctionnel : une requête sans tenant publié ne voit RIEN.
psql "$DATABASE_URL" -c "SET ROLE leopardo_app; SELECT count(*) FROM shared_tenants.employees;"
#   attendu : 0  (fail-closed — pas d'erreur, pas de fuite)

# 4.3 Avec un tenant publié, on ne voit QUE ce tenant.
psql "$DATABASE_URL" -c \
  "SET ROLE leopardo_app; SET app.current_company_id = '<uuid-d-une-entreprise>'; \
   SELECT count(*) FROM shared_tenants.employees;"
#   attendu : le nombre d'employés de CETTE entreprise uniquement

# 4.4 Smoke test applicatif : connexion, liste des employés, ouverture d'un bulletin.
```

**Point d'arrêt** : si 4.2 renvoie autre chose que `0`, **rollback immédiat**
(étape 5). Une fuite inter-tenant est un incident RGPD, pas un bug à planifier.

---

## 5. Rollback (2 min)

```bash
# Render → Environment : DB_USERNAME = leopardo   (l'ancien rôle)
# Redéployer.
```

Le rôle `leopardo_app` peut rester en place : il ne gêne rien et permet de
retenter. Les politiques RLS restent posées — elles redeviennent simplement
inertes, exactement l'état d'avant. **Aucune migration à défaire.**

---

## 6. Worker dédié (queue + scheduler) — #9976

**Prérequis bloquant** : un moyen de paiement valide sur le compte Render.
Sans lui, la création du service échoue en `402` (mesuré, #7649). Ce n'est
pas un problème de code — le blueprint porte déjà le bloc cible, commenté.

Le blueprint `render.yaml` contient le service `leopardo-queue-worker`
(`type: worker`, `plan: starter`) **en commentaire**, avec un marqueur qui
déclare explicitement la dégradation. La garde
`check-queue-strategy-coherence.sh` refuse un blueprint `plan: free` sans ce
marqueur : la dégradation doit rester DÉCLARÉE, jamais implicite.

```bash
# 6.1 Décommenter le bloc `type: worker` de render.yaml (lignes ~340-365).
#     Ne rien changer d'autre : dockerCommand porte déjà queue:work ET
#     schedule:work (un seul service starter, pas deux).

# 6.2 Sur le service WEB, poser :
#       WEB_QUEUE_DRAIN=false
#       WEB_SCHEDULER_LOOP=false
#     Sans ces deux gates, le web ET le worker consomment la même queue :
#     l'ordre des jobs n'est plus garanti et un job peut être traité deux fois.

# 6.3 Redéployer le web, puis vérifier que le scheduler est bien observé :
curl -s "$API_URL/health" | jq '.checks.scheduler'
#   attendu : status == "observed", last_slot_age_seconds < 120

# 6.4 Retirer le marqueur de dégradation du blueprint.
#     La garde exige alors les deux gates à false et refuse le retour d'un
#     drain web silencieux.
```

**Vérification** :

```bash
# Le worker consomme : le backlog diminue.
php artisan queue:monitor high,default,low --max=100

# Le scheduler tourne une fois par minute, sur UN seul service.
# (logs du worker : « Running scheduled command: ... »)
```

**Détection de panne** (déjà en place depuis #9976, même sans worker) :
`checks.scheduler.last_slot_age_seconds` dans `/health` expose l'âge du
dernier créneau ; `queue-supervision.yml` alerte au-delà de 20 min. La panne
n'est donc plus silencieuse — elle est seulement encore *possible*.

---

## 7. Checklist de sortie

- [ ] `tenant:rls-status --strict` renvoie `0`
- [ ] `SELECT rolsuper, rolbypassrls FROM pg_roles WHERE rolname='leopardo_app'` → `f, f`
- [ ] Requête sans tenant publié → `0` ligne (pas d'erreur)
- [ ] Smoke test applicatif vert (connexion, employés, bulletin)
- [ ] Logs de démarrage : « RLS effectif »
- [ ] Worker dédié : `queue:monitor` stable, scheduler sur un seul service
- [ ] `RUN_QUEUE_IN_WEB=false` sur le service web
- [ ] Mot de passe `leopardo_app` dans le gestionnaire de secrets, **pas** dans le dépôt

---

## 8. Ce qui reste hors de cette note

| Sujet | Pourquoi | Où |
|---|---|---|
| Couverture des 503 tables restantes | chantier par lots, pas un prérequis de bascule | #9975 |
| `RLS_GATE_STRICT_COVERAGE=true` | à activer quand la couverture sera complète | #9975 |
| Paiement Render | action de facturation, pas de code | #7649 |
| Revue obligatoire (ruleset) | déjà actif, vérifié | #9980 |
