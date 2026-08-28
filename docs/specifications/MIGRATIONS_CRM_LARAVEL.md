# Conventions — Migrations CRM avec Laravel (issue #5735, CRM-PRE)

- **Statut :** ratifié — référentiel des migrations CRM du monorepo
- **Date :** 2026-08-28
- **Runner canonique :** `php artisan leopardo:migrate` (`api/routes/console.php`)
- **Prépare :** #5707 (squelette DDD), #5708 (migrations accounts/contacts), #5709 (migrations leads/pipelines/opportunités)

---

## 1. Runner canonique

**Une seule chaîne de migration** : Laravel (`Illuminate\Database\Migrations`). Aucun Flyway, Prisma, Knex ou seconde chaîne.

`php artisan leopardo:migrate [--fresh] [--seed] [--demo]` :
1. `--fresh` : drop des schémas `public` et `shared_tenants` (recréés vides).
2. **Migrations `public`** : `database/migrations/public` (schéma `public` — tables plateforme, CRM commercial Leopardo, socle).
3. **Migrations tenant** : `database/migrations/tenant` (schéma `shared_tenants` — tables des modules livrés aux entreprises clientes, dont le CRM client).
4. `--seed` : `DatabaseSeeder` ; `--demo` : `DemoCompanySeeder` (local/dev uniquement).

Répertoires contrôlés par la garde `check-migration-prefixes.mjs` : `tenant` et `shared` (le dossier `shared` n'existe pas encore — si un jour des tables partagées multi-tenant hors `shared_tenants` sont nécessaires, c'est là qu'elles iront).

## 2. Emplacement des tables CRM

| Tables | Répertoire | Schéma | Issue |
|---|---|---|---|
| `crm_accounts`, `crm_contacts` | `database/migrations/tenant/` | tenant (`shared_tenants`) | #5708 |
| `crm_leads`, `crm_pipelines`, `crm_opportunities` | `database/migrations/tenant/` | tenant | #5709 |
| `crm_activities`, `crm_tasks` | `database/migrations/tenant/` | tenant | #5710 |
| `crm_imports` (sessions d'import) | `database/migrations/tenant/` | tenant | #5714 |

Le CRM commercial Leopardo (`marketing_leads`, `company_requests`, `webhook_events`…) reste en **`public`** et n'est **jamais modifié** par les migrations CRM client (ADR-CRM-002).

## 3. Nommage et gardes (obligatoires avant push)

- Nom : `YYYY_MM_DD_0000NN_<issue>_<slug>.php` — **la référence d'issue dans le nom est obligatoire** (règle #5431) : `2026_08_28_000012_5714_create_crm_imports_table.php`.
- **Préfixe de séquence unique** : vérifier `bash dev-hub/tools/check-migration-basename-collisions.sh` (intra-branche) ET la garde inter-PR `check-migration-prefixes.mjs` (comparer les préfixes avec toutes les PRs ouvertes avant de choisir le numéro — un préfixe déjà pris = renuméroté, la plus ancienne conserve son préfixe). Incident #1962/#5437.
- **Schéma tenant** : les migrations tenant utilisent `schemaTableExists()` / `schemaHasColumn()` et `Schema::create()` (jamais `Schema::hasTable()`/`Schema::table()` avec nom nu — convention #1613, garde `check-migrations-tenant-schema.sh`). Pas de FK vers `companies` (table public).
- **Parité MVP** : toute nouvelle table tenant récente doit être couverte par `RefreshTenantDatabase` dans un test OU ajoutée à `api/tests/Support/CreatesMvpSchema.php` (garde `check-mvp-schema-parity.sh`, anti-régression #5443).

## 4. Réentrance, provisioning, rollback, restauration

- **Réentrance** : toute migration est ré-exécutable sans erreur (garde `schemaTableExists` avant `Schema::create`, `schemaHasColumn` avant ajout de colonne, `CREATE INDEX IF NOT EXISTS` pour les index).
- **Provisioning d'un nouveau tenant** : en mode `shared` (défaut), les tables tenant vivent dans `shared_tenants` — un nouveau tenant reçoit les tables par `search_path` sans migration supplémentaire. En mode `schema` (isolation physique), `TenantManager::setTenant()` bascule le `search_path` sur le schéma de l'entreprise, qui doit avoir été provisionné avec le même jeu de tables (runbook de provisioning hors périmètre CRM — convention #1613).
- **Rollback** : `migrate:rollback --path=database/migrations/tenant --step=N` — chaque `down()` est écrit pour laisser la base dans un état cohérent (drop des tables/colonnes créées).
- **Restauration** : `leopardo:migrate --fresh` recrée les deux schémas ; la restauration de données suit le runbook backup existant (`docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md`).

## 5. Scripts SQL autonomes = référence seulement

Le dépôt ne contient **aucun DDL CRM au format SQL autonome** (vérifié). Toute requête SQL montrée en exemple dans la documentation (cette spec, l'ADR, les runbooks) est **référence seulement** — jamais présentée comme un moyen de déploiement. Le déploiement passe exclusivement par `php artisan leopardo:migrate` (ou `migrate`/`migrate:rollback` Laravel sur les chemins `public`/`tenant`).

## 6. Tests couverts

`api/tests/Feature/Database/LeopardoMigrateRunnerTest.php` :
- `leopardo:migrate` est **réentrant** (deux exécutions successives sans erreur).
- Les tables tenant atterrissent dans `shared_tenants`, les tables public dans `public` (vérifié via `information_schema`).
- Cycle **migrate → rollback → migrate** sur une migration temporaire isolée : création, rollback (down), recréation — le runner et les gardes restent cohérents.
