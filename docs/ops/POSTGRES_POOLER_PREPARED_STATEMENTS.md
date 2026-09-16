# Erreurs Postgres transitoires avec un pooler — `cached plan must not change result type`

**Statut** : document opérationnel actif · **Issue** : #7479 (symptôme : `GET /auth/me`
en 500 intermittent en production, « une connexion valide présentée comme un échec »)
· **Dernière mise à jour** : 2026-09-16.

---

## 1. Le symptôme, tel qu'il a été observé

`POST /api/v1/auth/login` répond **200** (la session est créée, cookie httpOnly
posé), puis `GET /api/v1/auth/me` répond **500** — de façon **intermittente**,
donc invisible dans un test unique. Côté utilisateur : la connexion « échoue »
avec un message générique, alors que les identifiants sont valides.

## 2. La cause, retrouvée dans les journaux du service

```
production.ERROR: SQLSTATE[0A000]: Feature not supported: 7
  ERROR: cached plan must not change result type
  (Connection: pgsql, Host: ep-…-pooler.eu-west-2.aws.neon.tech, Port: 5432,
   Database: neondb, SQL: select * from "employees" where "employees"."id" = 299 limit 1)
```

Lecture : la base est atteinte via le **pooler** Neon (`…-pooler…`, mode
transaction). Un backend réutilisé par le pooler garde un **plan de requête
préparé** calculé avant un changement de schéma (un déploiement qui migre). Le
plan devient invalide, et **toute requête sur ce backend échoue** — alors que la
connexion, le réseau et les identifiants sont parfaitement valides. Un nouvel
essai sur une connexion fraîche réussit.

À noter : ce défaut est **indépendant du code applicatif**. Il frappe la première
requête qui touche une table modifiée, après chaque déploiement, sur les backends
déjà chauds du pooler.

## 3. Ce qui a été corrigé dans le dépôt (PR de #7479)

| Volet | Fichier | Effet |
|---|---|---|
| **Rejeu ciblé** | `api/app/Http/Middleware/RetryTransientDatabaseErrors.php` (branché en tête du groupe `api`) | sur erreur Postgres **transitoire** et méthode **idempotente** (GET/HEAD/OPTIONS) : fermeture de la connexion + **un** rejeu. Les méthodes non idempotentes ne sont **jamais** rejouées (pas de double écriture). |
| **Réponse dégradée documentée** | même middleware | si le rejeu échoue aussi : **503** `{"error":"SERVICE_UNAVAILABLE","code":"DB_TRANSIENT","retryable":true}` + `Retry-After` — au lieu d'un 500 nu. Une vraie erreur applicative (`42703`, etc.) n'est **pas** masquée. |
| **Côté client** | `front/web/src/app/auth/login/page.tsx` | un échec de `/auth/me` **après** un login réussi n'affiche plus un message d'identifiants : état « session créée, espace indisponible » + bouton de reprise qui **recharge le profil** (jamais les identifiants). i18n ×4. |

## 4. Le levier de fond (à arbitrer côté hébergeur)

Deux options, à trancher par le propriétaire — aucune n'est appliquée
automatiquement par le dépôt :

1. **Ne pas utiliser les *prepared statements* côté serveur** sur les connexions
   poolées : `PDO::ATTR_EMULATE_PREPARES => true` sur la connexion `pgsql`
   (`api/config/database.php`). C'est la recommandation usuelle pour
   pgBouncer/Neon en mode transaction. Contrepartie : les types sont liés côté
   client (comportement légèrement différent sur certains casts).
2. **Utiliser la connexion directe** pour les requêtes applicatives (le pooler
   n'étant gardé que pour ce qui en a besoin). Le dépôt applique **déjà** cette
   règle pour les **migrations au boot** (`api/docker-entrypoint.sh` dérive
   `DB_MIGRATE_URL` en retirant `-pooler`, issues #6916/#6924) — c'est le même
   raisonnement, appliqué ici au trafic de lecture.

> ⚠️ Ne pas activer l'option 1 « pour voir » en production : elle change la
> sémantique de liaison des types sur **toutes** les requêtes. À faire dans une
> fenêtre dédiée, avec la suite de tests complète et un contrôle des endpoints
> sensibles (exports, PDF, rapports).

## 5. Comment vérifier qu'un incident de ce type est bien celui-là

```bash
# 1. les journaux du service (Render → Logs) portent l'erreur exacte :
#    « cached plan must not change result type » ou « current transaction is aborted »
#
# 2. l'API renvoie maintenant un 503 identifiable au lieu d'un 500 :
curl -s -o /dev/null -w '%{http_code}\n' https://<api>/api/v1/auth/me
#    503 + {"code":"DB_TRANSIENT","retryable":true} -> incident transitoire (ce document)
#    500 + {"error":"INTERNAL_ERROR"}               -> bug applicatif (autre sujet)
#
# 3. la garde de dérive (#7304) rappelle l'état de l'environnement testé :
dev-hub/tools/check-deploy-drift.sh --url "$DEV_API_BASE_URL" --expect origin/main
```

## 6. Liens

- Issue #7479 · middleware `api/app/Http/Middleware/RetryTransientDatabaseErrors.php`
- Tests : `api/tests/Feature/Auth/RetryTransientDatabaseErrorsTest.php`,
  `front/web/src/app/auth/login/__tests__/login-session-recovery.test.tsx`
- Migrations au boot et hôte direct : `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (§ migrations)
- Vérifications d'environnement : `docs/ops/RENDER_DEV_ALIGNMENT.md`
