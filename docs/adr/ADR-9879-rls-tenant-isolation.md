# ADR-9879 — Activer PostgreSQL RLS comme filet d'isolation tenant

- **Statut** : Accepté (déploiement progressif)
- **Date** : 2026-11-24
- **Contexte de décision** : revue d'architecture (#9879, constat R8)

## Contexte

L'isolation multi-tenant de Leopardo repose sur **deux mécanismes applicatifs** :

1. `search_path` PostgreSQL positionné par `TenantManager` (`shared_tenants,public`) ;
2. un scope Eloquent global avec `TenantContextMissingException` (fail-closed).

Mesure : `grep -rl "ENABLE ROW LEVEL SECURITY\|CREATE POLICY" database/migrations` → **0 fichier**. Aucune barrière au niveau base.

Conséquence : toute requête qui contourne Eloquent — `DB::table()`, SQL brut, un `withoutGlobalScopes()` oublié, un job qui n'a pas posé le contexte — **lit les données de tous les tenants**. Le dépôt a déjà des gardes CI statiques contre les `withoutGlobalScopes()` non justifiés, mais une garde statique ne voit pas le SQL construit dynamiquement.

## Décision

Activer RLS **progressivement**, en commençant par `bulk_payment_claims` :

```sql
ALTER TABLE bulk_payment_claims ENABLE ROW LEVEL SECURITY;
ALTER TABLE bulk_payment_claims FORCE ROW LEVEL SECURITY;

CREATE POLICY bulk_payment_claims_tenant_isolation
ON bulk_payment_claims
USING  (company_id::text = current_setting('app.current_company_id', true))
WITH CHECK (company_id::text = current_setting('app.current_company_id', true));
```

Le `true` de `current_setting(..., true)` (missing_ok) est **essentiel** : si la variable n'est pas positionnée, la comparaison vaut `NULL`, donc **aucune ligne n'est visible**. Oublier de positionner le tenant ne fuit rien — c'est fail-closed, cohérent avec le `TenantContextMissingException` existant.

`FORCE ROW LEVEL SECURITY` est nécessaire car sans lui le **propriétaire** de la table (souvent le rôle applicatif) contourne la politique.

## ⚠️ Prérequis de déploiement — mesuré dans ce dépôt

**Le rôle applicatif `leopardo` est SUPERUSER** (`\du` → `Superuser`). PostgreSQL fait **toujours** bypasser RLS aux superusers, **même avec `FORCE`**.

Tant que l'application se connecte en superuser, la politique existe mais **ne protège pas**. Le test `RlsTenantIsolationTest` en tient compte : il crée un rôle `rls_isolation_probe` non-superuser, exécute la politique, et vérifie depuis ce rôle. Un test qui compterait les lignes en tant qu'utilisateur applicatif passerait au vert sans rien prouver.

**Action requise avant de considérer RLS comme actif en production** : créer un rôle applicatif dédié, `NOSUPERUSER NOBYPASSRLS`, propriétaire distinct des tables (ou `FORCE` + rôle non-propriétaire), et l'utiliser dans `DB_USERNAME`.

## Preuve

`tests/Feature/Security/RlsTenantIsolationTest` :

| Scénario | Attendu | Obtenu |
|---|---|---|
| tenant A interroge la table | 1 ligne (la sienne) | ✅ |
| tenant B interroge la table | 1 ligne (la sienne) | ✅ |
| aucun tenant positionné | 0 ligne (fail-closed) | ✅ |
| tenant B modifie une ligne de A | 0 ligne affectée | ✅ |
| rôle superuser applicatif | 2 lignes (RLS bypassé) | ✅ — documente la limite |
| `pay_slips` / `payroll_runs` portent la politique | `relrowsecurity` + `relforcerowsecurity` + `pg_policy` | ✅ (#9889) |
| `TenantManager::setTenant()` publie le tenant | `current_setting` = id de la company | ✅ (#9889) |
| `TenantManager::clearTenant()` efface le tenant | `current_setting` = `''` | ✅ (#9889) |

## Alternatives écartées

| Alternative | Pourquoi écartée |
|---|---|
| **Schéma par tenant** (`search_path` seul) | Déjà en place, mais ne protège pas du SQL brut dans le schéma partagé |
| **Base par tenant** | Coût opérationnel et migrations prohibitifs à 42 modules |
| **Base par tenant + RLS** | Redondant : si chaque tenant a sa base, RLS n'apporte rien |
| **Gardes CI uniquement** | Statique, contournable par du SQL dynamique — ne voit pas l'exécution |

## Plan de déploiement

| Étape | Périmètre | Statut |
|---|---|---|
| 1 | `bulk_payment_claims` (table neuve, aucun accès inter-tenant légitime) | ✅ fait |
| 2 | Rôle applicatif non-superuser (`NOSUPERUSER NOBYPASSRLS`) | 🟨 **outillage livré (#9896)** — bascule à faire côté exploitation |
| 3 | `pay_slips`, `payroll_runs` (données de paie) | ✅ fait (#9889) |
| 4 | `employees` | ✅ fait (#9897) |

L'étape 2 est **bloquante** : sans elle, les étapes 3 et 4 donneraient une fausse impression de sécurité.

## Étape 2 — outillage livré (#9896)

L'ADR décrivait la bascule en prose. Rien n'était exécutable ni vérifiable : impossible de savoir, depuis l'application, si RLS protège réellement dans l'environnement courant. Trois livrables comblent ce trou.

### 1. Sonde `php artisan tenant:rls-status`

Interroge `pg_roles` et `pg_class`, et rend un verdict explicite :

| Code | Signification |
|---|---|
| `0` | RLS effectif : rôle non-bypass, toutes les tables attendues couvertes |
| `1` | RLS **annoncé mais bypassé** (rôle SUPERUSER ou BYPASSRLS) |
| `2` | Rôle conforme, mais une table attendue n'est pas couverte |
| `3` | Environnement non-PostgreSQL |

`--json` pour un usage scripté, `--strict` pour faire échouer un pipeline de déploiement sur le code 1.

Le code 1 n'est pas une erreur de la commande : **c'est le verdict**. Sur ce dépôt, il sort 1 — le rôle `leopardo` est SUPERUSER.

### 2. Script de bascule `database/scripts/rls/create-app-role.sql`

Idempotent, avec `ALTER DEFAULT PRIVILEGES` (sans quoi chaque nouvelle migration produirait une table illisible par le rôle applicatif — une panne au déploiement suivant, pas au moment de la bascule), les requêtes de vérification avant/après, et le rollback.

Le script ne bascule **pas** `DB_USERNAME` : c'est une décision d'exploitation, réversible en changeant une variable d'environnement. Un script de migration ne doit pas pouvoir couper l'accès à la base d'un déploiement en cours.

### 3. ⚠️ Défaut trouvé par la sonde, et corrigé

La sonde a immédiatement révélé que `bulk_payment_claims` avait `ENABLE` + `FORCE` mais **aucune politique**, alors que la migration #9879 était enregistrée comme passée.

Cause mesurée dans `shared_tenants.migrations` :

```
batch 2 : 2026_11_24_000200_9879_enable_rls_on_bulk_payment_claims
batch 3 : 2026_11_24_000100_9874_create_bulk_payment_claims_table
```

La migration RLS a tourné **avant** la création de la table. Son garde `if (! schemaTableExists(...)) { return; }` l'a silencieusement ignorée, et Laravel l'a marquée « Ran » définitivement — aucune réexécution possible.

Le garde est correct en soi (il rend la migration tolérante à l'ordre de déploiement), mais il est **silencieux** : l'état final est « migration passée, protection absente ». C'est exactement le mode de défaillance que cet ADR cherche à éliminer.

Correction : migration de réparation `2026_11_24_000400_9896_repair_missing_rls_policies`, idempotente, qui réconcilie l'état voulu avec l'état réel. Elle ne remplace pas les migrations d'origine (qui restent la source de vérité de la définition) ; c'est un filet, et la sonde prouve le résultat.

### 4. Défaut trouvé dans le test de #9879

`RlsTenantIsolationTest::resetPolicy()` faisait `ALTER TABLE ... DISABLE ROW LEVEL SECURITY` en `tearDown`. Après chaque exécution de la suite, la table ressortait **ouverte** alors que la migration était enregistrée comme passée.

Un test qui laisse la base moins protégée qu'il ne l'a trouvée est un défaut de sécurité silencieux : la suite passe au vert et la table reste ouverte. `resetPolicy()` **restaure** désormais l'état de la migration, et un test dédié le vérifie.

### Bascule — commandes exactes

```bash
# 1) Vérifier l'état courant (doit sortir 1 aujourd'hui)
php artisan tenant:rls-status

# 2) Créer le rôle et poser les droits
psql "$DATABASE_URL" -v app_password="'<secret>'" -f database/scripts/rls/create-app-role.sql

# 3) Vérifier AVANT bascule : les 2 requêtes du script doivent renvoyer 0 ligne / toutes les tables couvertes
# 4) Basculer
#    DB_USERNAME=leopardo_app  DB_PASSWORD=<secret>  puis redéploiement

# 5) Vérifier APRÈS bascule (doit sortir 0)
php artisan tenant:rls-status --strict
```

**Rollback** : `DB_USERNAME=leopardo` puis redéploiement. Aucune migration à défaire — les politiques restent en place et redeviennent inertes. Aucun risque de perte de données.

### #9974 — la bascule est désormais outillée, pas seulement documentée

**Le défaut trouvé à l'audit du 2026-11-27.** Le script de bascule ci-dessus
**ne pouvait pas s'exécuter**. Il écrivait `:'app_password'` à l'intérieur d'un
bloc `DO $$ … $$` ; psql n'interpole pas ses variables dans une chaîne
dollar-quotée, et le script échouait en `syntax error at or near ":"`. La
procédure de sécurité de cet ADR était donc une procédure **jamais exécutée**,
et aucune CI ne la lançait — d'où l'absence de détection.

Quatre corrections, toutes vérifiées :

| Correction | Où | Preuve |
|---|---|---|
| Création via `\gexec` (interpolation correcte) | `create-app-role.sql` | exécuté contre PostgreSQL 16 réel |
| Gate de déploiement `RLS_GATE_MODE` | `docker-entrypoint.sh` | `strict` par défaut en `DEPLOY_TIER=prod` |
| Test d'exécution + effet | `RlsAppRoleBootstrapTest` | non-superuser, fail-closed, `WITH CHECK` |
| Garde statique + auto-test | `check-rls-role-script.py` | `guards-fast.yml` 15/15 |

**Le gate de déploiement.** `tenant:rls-status` existait depuis #9896 et n'était
branché **nulle part**. Il l'est désormais au démarrage du conteneur :

| `RLS_GATE_MODE` | Comportement |
|---|---|
| `strict` (défaut si `DEPLOY_TIER=prod`) | code 1 (rôle SUPERUSER/BYPASSRLS) → **déploiement refusé** |
| `warn` (défaut ailleurs) | verdict journalisé, démarrage poursuivi |
| `off` | sonde non exécutée (dépannage explicite) |

Un déploiement refusé laisse la version précédente en ligne (Render) : c'est le
comportement voulu — un déploiement qui annonce une isolation inexistante ne
doit pas passer. `RLS_GATE_STRICT_COVERAGE=true` fait en plus échouer sur le
code 2 (couverture incomplète) ; c'est **séparé** du mode, car couvrir les 523
tables est un chantier par lots (#9975), pas un prérequis de bascule.

**Ce que le gate ne fait pas.** Il ne bascule pas `DB_USERNAME` : c'est une
décision d'exploitation, réversible en changeant une variable. Un script de
migration ne doit pas pouvoir couper l'accès à la base d'un déploiement en
cours.

**Ordre d'exécution recommandé en production :**

1. `php artisan tenant:rls-status` → attendu **1** (rôle superuser).
2. Exécuter `create-app-role.sql` (idempotent, rejouable pour une rotation).
3. Vérifier les deux requêtes de contrôle du script : **0 ligne** sur la
   première, toutes les tables attendues couvertes sur la seconde.
4. Poser `DB_USERNAME=leopardo_app` + `DB_PASSWORD`, redéployer.
5. Le gate `strict` s'exécute au boot : s'il échoue, le déploiement est refusé
   et l'ancienne version reste en ligne. Lire le message, corriger, redéployer.
6. `php artisan tenant:rls-status --strict` → attendu **0**.

**Point de vigilance non couvert par le code.** `ALTER DEFAULT PRIVILEGES` ne
s'applique qu'aux objets créés par le rôle qui exécute le script. Si les
migrations tournent sous un autre rôle que celui utilisé pour la bascule,
rejouer la section 2 du script sous **ce** rôle — sinon la première migration
suivante créera une table que `leopardo_app` ne peut pas lire (panne au
déploiement suivant, pas au moment de la bascule).

## Étape 4 — `employees` (#9897)

### Inventaire des accès légitimes (exigé par l'ADR)

| Accès | Fichier | Verdict |
|---|---|---|
| Jointures SQL sur `employees` | `app/AI/Predictions/AbsenteeismPredictor.php` (2), `ProactiveNotificationService.php` (4) | **OK** — partent d'une table tenant déjà filtrée ; la jointure hérite du tenant. |
| `Employee::withoutGlobalScopes()` | `app/Core/Auth/Infrastructure/Services/AuthService.php` (8) | **OK, et c'est le point critique** — voir ci-dessous. |
| `Employee::factory()` | `database/factories/EmployeeFactory.php` | **Corrigé** — ne renseignait pas `company_id`. |
| Jobs multi-tenant | `app/Console/Commands/PrecalculatePayrollRuns.php` | **OK** — itère par `company_id` via `TenantManager::withinTenant()`. |

### Pourquoi `AuthService::withoutGlobalScopes()` ne casse pas

C'est l'accès qui aurait dû bloquer cette étape : la connexion lit `employees` **avant** qu'un tenant ne soit connu. Le flux réel est :

1. `public.user_lookups` (email → `company_id`, `employee_id`) est lu **hors** RLS (table plateforme) ;
2. `TenantManager::setTenant()` publie `app.current_company_id` ;
3. **puis** `Employee::withoutGlobalScopes()->where('company_id', $lookup->company_id)`.

Le `where('company_id', ...)` explicite satisfait la politique : la ligne demandée est bien celle du tenant publié. `withoutGlobalScopes()` ne contourne que le scope Eloquent, pas RLS — et c'est précisément ce qu'on veut : le scope applicatif peut être retiré par erreur, la politique PostgreSQL non.

### Le blocage : `company_id` était nullable

La politique des étapes 1 et 3 est `USING (company_id::text = current_setting('app.current_company_id', true))`. Avec `company_id IS NULL`, la comparaison vaut `NULL` → **la ligne devient invisible**. Poser la politique telle quelle aurait masqué tous les employés sans tenant.

Mesures : `EmployeeFactory` ne renseignait pas `company_id` ; **2 336** appels `Employee::factory()` dans les tests, **12** seulement passaient `->for(...)` ; `tenancy_type` est verrouillé à `'shared'` (ADR-0028), donc le mode « schéma isolé » qui justifiait le nullable n'est plus créable. Le nullable était une **dette historique**, pas un besoin.

### Décision : backfill bloquant, pas de contrainte NOT NULL

La migration **backfill** `company_id` (sources : `user_lookups`, puis `pay_slips`→`payroll_runs`, puis la company unique si l'installation est mono-tenant) et **échoue explicitement** s'il reste un seul NULL. Elle ne pose **pas** `NOT NULL` au niveau DDL : 326 sites de test font `new Employee([...])->save()` puis renseignent `company_id` dans un second `save()`, et la contrainte casserait le premier INSERT. Corriger 326 appels pour une garantie que le backfill apporte déjà serait un mauvais échange.

Ce qui protège réellement :

1. le backfill **échoue** si un NULL subsiste — la migration ne peut pas se terminer sur une base trouée ;
2. la politique rend toute ligne à NULL **invisible** (`NULL = 'x'` vaut NULL, pas TRUE) : un NULL futur serait une panne d'affichage bruyante, **jamais une fuite cross-tenant** ;
3. `Employee::creating` renseigne `company_id` depuis le tenant courant quand il existe.

### Preuve

`RlsTenantIsolationTest::test_employees_isolates_tenants_and_fails_closed` crée un rôle **non-superuser**, applique la politique et compte depuis ce rôle : tenant A → 1, tenant B → 1, aucun tenant → **0**, écriture hors tenant → **0 ligne affectée**. Un test qui compterait en tant qu'utilisateur applicatif (SUPERUSER) passerait au vert sans rien prouver.

## Étape 3 — inventaire des accès légitimes (#9889)

L'ADR exigeait un inventaire **avant** de poser la politique. Résultat de la recherche exhaustive :

| Accès | Filtre tenant | Verdict |
|---|---|---|
| `SocialDeclarationService::quarterPayrollData()` / `monthPayrollData()` (`DB::table('pay_slips')` + join `payroll_runs`) | `where('payroll_runs.company_id', $companyId)` explicite | scopé |
| `PilotReportCommand::companyMetrics()` (`DB::table('payroll_runs')`) | exécuté dans `TenantManager::withinTenant($company, …)` | scopé |
| `withoutGlobalScopes()` sur ces tables | — | **aucun** |
| `ProcessBulkPaymentJob` | traite UN run, donc UN tenant | scopé |
| vues matérialisées / tables d'agrégat | — | **aucune** |

**Conclusion : aucun accès inter-tenant légitime.** Aucune exemption n'est posée — une exemption implicite serait exactement le trou que cette étape ferme.

## ⚠️ Le maillon qui manquait : `app.current_company_id` n'était jamais positionné

Constat en écrivant l'étape 3 : `grep -rn "app.current_company_id" app/` ne renvoyait **que la migration de l'étape 1**. La variable n'était positionnée **nulle part** dans le code applicatif.

Conséquence : la politique de l'étape 1 était **inerte**. `current_setting('app.current_company_id', true)` renvoie `NULL` quand la variable n'existe pas, donc la comparaison vaut `NULL` et **aucune ligne n'est visible**. Le fail-closed fonctionnait — mais il aurait rendu `bulk_payment_claims` illisible dès qu'un rôle non-superuser aurait été adopté.

Le `search_path` ne pouvait pas combler ce trou : il choisit le **schéma**, il ne dit rien à RLS sur le **tenant**. Les deux mécanismes sont orthogonaux.

**Correction (#9889)** : `TenantManager` publie désormais le tenant dans la session PostgreSQL, aux trois points où le contexte change :

| Méthode | Effet |
|---|---|
| `setTenant()` | `set_config('app.current_company_id', <id>, false)` |
| `restoreCompanyContext()` | republie le tenant restauré (couvre `withinTenant()` imbriqué) |
| `clearTenant()` | efface la variable — ne pas laisser traîner le tenant précédent |

`set_config(..., false)` = portée **session**, pas transaction : la politique doit survivre aux commits d'une même requête HTTP, un `SET LOCAL` serait perdu au premier commit.

## Plan de migration vers un rôle NOSUPERUSER (étape 2)

```sql
-- 1) Créer le rôle applicatif, sans superuser ni bypass RLS
CREATE ROLE leopardo_app LOGIN PASSWORD '<secret>' NOSUPERUSER NOBYPASSRLS;

-- 2) Droits sur les schémas et les tables
GRANT USAGE ON SCHEMA shared_tenants, public TO leopardo_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA shared_tenants TO leopardo_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA shared_tenants TO leopardo_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA shared_tenants
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO leopardo_app;

-- 3) Vérification AVANT bascule : la politique doit être posée
SELECT relname, relrowsecurity, relforcerowsecurity
  FROM pg_class
 WHERE relname IN ('bulk_payment_claims','pay_slips','payroll_runs');

-- 4) Bascule applicative : DB_USERNAME=leopardo_app, puis redéploiement

-- 5) Vérification APRÈS bascule (doit renvoyer 0)
SELECT count(*) FROM shared_tenants.pay_slips;  -- sans app.current_company_id
```

**Rollback** : `DB_USERNAME` revient à `leopardo` (superuser) et l'application redémarre. Les politiques restent en place mais redeviennent inertes — aucune migration à défaire, donc aucun risque de perte de données.

**Point d'attention** : le rôle `leopardo_app` ne doit **pas** être propriétaire des tables, sinon `FORCE ROW LEVEL SECURITY` est indispensable (il l'est déjà) et toute future table créée par un autre rôle devra recevoir les `GRANT` (d'où `ALTER DEFAULT PRIVILEGES`).

## Étape 5 — tables RH nominatives (#9924)

### Inventaire mesuré, et pourquoi il fallait le faire

`database/scripts/rls/inventory-company-id-tables.sh` mesure la base réelle :

```
523 tables `shared_tenants` portant `company_id`
  4 couvertes (étapes 1, 3, 4)
  1 bloquée  (tax_slabs — voir ci-dessous)
 87 à couvrir, `company_id` nullable mais 0 ligne à NULL
431 à couvrir, `company_id` NOT NULL
```

Ajouter 500 politiques d'un coup serait une faute : l'ADR exige un inventaire
des accès légitimes **avant** chaque politique, et une politique inerte donne
une fausse impression de sécurité — pire que l'absence.

### Lot livré : 4 tables RH nominatives

| Table | `company_id` | Lignes NULL | Accès `withoutGlobalScopes()` | Verdict |
|---|---|---|---|---|
| `absences` | nullable | 0 | — | couverte |
| `contracts` | NOT NULL | 0 | — | couverte |
| `leave_balances` | NOT NULL | 0 | `LeaveCarryForward` | **corrigé** |
| `attendance_logs` | nullable | 0 | `PlatformCompanyHealthService` | OK (filtre explicite) |

**Le point qui a bloqué, et sa correction.** `LeaveCarryForward` itérait les
politiques de congés de **tous** les tenants sans publier le tenant courant.
Sous RLS, `current_setting('app.current_company_id', true)` vaut `NULL`, la
comparaison vaut `NULL`, et **aucune ligne n'est visible** : le report de
congés aurait été silencieusement sans effet. Chaque boucle passe désormais par
`TenantManager::withinTenant()`, qui publie le tenant et restaure l'état
précédent en `finally`.

`withoutGlobalScopes()` ne contourne que le scope Eloquent, **pas** RLS — c'est
précisément ce qu'on veut : le scope applicatif peut être retiré par erreur, la
politique PostgreSQL non.

### `tax_slabs` : exclue explicitement, avec sa raison

L'inventaire la classe « bloquée » : 6 lignes à `company_id` NULL. Ce ne sont
**pas** des données orphelines — ce sont les **barèmes légaux partagés**
(repli `whereNull('company_id')` dans
`AbstractCountryRules::resolveTaxSlabsFromDatabase()`). Les filtrer par tenant
casserait le calcul de paie de toute company sans barème propre.

Elle demande une politique à sémantique différente :

```sql
USING (company_id IS NULL OR company_id::text = current_setting('app.current_company_id', true))
```

C'est une décision séparée, car elle change la sémantique de lecture des
barèmes. Elle est **exclue**, pas oubliée — et `tenant:rls-status` ne la compte
pas, avec ce motif en commentaire.

### Preuve

`tests/Feature/Security/RlsHrTablesIsolationTest.php` — **3 tests, 29
assertions**. Comme pour #9879, le test crée un rôle **non-superuser** : un
compte fait en tant qu'utilisateur applicatif (SUPERUSER) passerait au vert
sans rien prouver. Vérifié par table : tenant A → 1, tenant B → 1, aucun
tenant → **0** (fail-closed), écriture hors tenant → **0 ligne affectée**.

## Conséquences

**Positives** : filet indépendant du code applicatif ; un `DB::table()` oublié ne fuit plus ; fail-closed par défaut.

**Négatives** : coût de planification sur les tables volumineuses ; nécessite que chaque connexion positionne `app.current_company_id` (sinon **plus rien** n'est lisible) ; impose la séparation rôle-propriétaire / rôle-applicatif.
