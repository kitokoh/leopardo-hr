# Conventions — Migrations Laravel par bounded context (MAT-005)

- **Statut :** ratifié — référentiel des conventions de migrations du monorepo
- **Date :** 2026-08-28
- **Runner canonique :** `php artisan leopardo:migrate` (`api/routes/console.php`)
- **Portée :** tous les bounded contexts (Platform/Tenant/HR/Payroll/Accounting/CRM
  client/FuelStation/EduManager…). Le référentiel CRM spécifique
  (`docs/specifications/MIGRATIONS_CRM_LARAVEL.md`, #5735) reste la déclinaison
  CRM de ces conventions.
- **Garde CI :** `dev-hub/tools/check-migrations-bc-conventions.sh` (job
  Hygiene Guards du workflow `architecture-check.yml`).

Ce document standardise **noms, emplacement, ordre, réentrance, FK composites,
index, fresh, rerun et rollback** des migrations Laravel, quel que soit le
bounded context qui les produit. Toute nouvelle table, colonne ou contrainte
du monorepo doit respecter ces conventions ; la garde échoue sur toute
violation nouvelle (depuis la date d'introduction, voir §6).

---

## 1. Laravel est l'UNIQUE chaîne de migration

- Aucun DDL SQL autonome (`*.sql` avec `CREATE TABLE`/`ALTER TABLE`) sous
  `api/database/` : les exemples SQL montrés dans la documentation sont
  **référence seulement**, jamais un moyen de déploiement.
- Aucun marqueur d'outil de migration parallèle (Flyway, Prisma, Knex,
  TypeORM…) dans `api/database/`.
- Le déploiement passe exclusivement par `php artisan leopardo:migrate`
  (ou les commandes Laravel `migrate` / `migrate:rollback` sur les chemins
  `public` / `tenant` / `edge`).

## 2. Emplacement par bounded context

| Répertoire | Schéma | Contenu |
|---|---|---|
| `api/database/migrations/public/` | `public` | Plateforme, CRM commercial Leopardo, socle multi-tenant (tables **non** scopées par entreprise) |
| `api/database/migrations/tenant/` | `shared_tenants` (ou schéma de l'entreprise en isolation physique) | Tables des modules livrés aux entreprises clientes (CRM client, FuelStation, EduManager, HR/Payroll/Accounting…) — **toute table métier porte `company_id` non nullable** |
| `api/database/migrations/edge/` | — | Migrations du plan de données Edge (kiosques, synchronisation) |

Règle simple : une table porte des lignes appartenant à une entreprise cliente
→ migration tenant. Une table plateforme (fournisseurs de tenants, CRM
commercial, socle) → migration public. Une migration tenant **ne crée jamais
de FK vers une table public** (notamment `companies`, constitution §II) : les
liens vers le tenant se font par colonne `company_id` UUID indexée.

## 3. Nommage

- Format : `YYYY_MM_DD_0000NN_<issue>_<slug>.php`
  - `YYYY_MM_DD` : date de création ;
  - `0000NN` : séquence **unique sur tout le dépôt** (6 chiffres, zéro-paddés),
    choisie **après vérification** de l'absence de collision (voir §7) ;
  - `<issue>` : **référence d'issue obligatoire** (règle #5431) ;
  - `<slug>` : descriptif court en `snake_case`.
- Exemples : `2026_08_28_000013_5741_create_crm_outbox_events_table.php`,
  `2026_08_25_000003_5435_create_bank_statement_tables.php`.
- Interdit : `Schema::hasTable()`/`Schema::table()` au nom nu dans les
  migrations tenant (convention #1613 — voir §4) ; un nom de fichier sans
  issue ou sans slug.

## 4. Forme et réentrance

Chaque migration (`public`, `tenant`, `edge`) :

1. déclare `declare(strict_types=1);` ;
2. retourne un **nouveau `return new class extends Migration`** (pas de classe
   nommée) ;
3. implémente **`down()`** complet (drop des tables/colonnes créées — le
   rollback doit laisser la base dans un état cohérent) ;
4. est **ré-exécutable sans erreur** :
   - `schemaTableExists('ma_table')` avant `Schema::create('ma_table', …)`
     (helper global `api/app/helpers.php`) ;
   - `schemaHasColumn('ma_table', 'col')` avant d'ajouter une colonne ;
   - `CREATE INDEX IF NOT EXISTS` pour les index ;
5. documente l'objectif et les invariants dans un docblock (référence issue,
   règles métier, lien vers la spec).

Les migrations tenant qualifient le schéma : les helpers
`resolveTableSchema()` / `schemaTableExists()` / `schemaHasColumn()` résolvent
le schéma via le `search_path` — jamais de nom nu (pièges #1595/#1933).

## 5. FK composites, index, tenant-first

- **`company_id` non nullable**, UUID, indexé, sur toute table tenant.
- Les contraintes d'unicité et les index composites commencent par
  `company_id` (tenant-first) : ex.
  `$table->unique(['company_id', 'idempotency_key'], 'crm_outbox_company_key_unique');`.
- Les index de file/statut suivent le pattern
  `['company_id', 'status', 'available_at']` (claim atomique efficace).
- Aucune FK vers `companies` (table public) depuis une migration tenant.
- Les CHECK (enums, bornes) sont nommés explicitement et portent un commentaire
  SQL (`COMMENT ON TABLE/COLUMN`) quand ils portent des invariants métier.

## 6. Fresh, rerun, rollback

- **fresh** : `php artisan leopardo:migrate --fresh` droppe et recrée les
  schémas `public` et `shared_tenants`, puis ré-applique toutes les migrations.
- **rerun** : `php artisan leopardo:migrate` (sans options) est un no-op propre
  sur une base déjà migrée (réentrance).
- **rollback** : `php artisan migrate:rollback --path=database/migrations/tenant
  --step=N` — chaque `down()` restaure un état cohérent.
- Couvert par `api/tests/Feature/Database/LeopardoMigrateRunnerTest.php` :
  réentrance, atterrissage public/tenant, cycle migrate → rollback → migrate
  sur une migration isolée, cycle `--fresh` (schémas recréés).

## 7. Gardes et protocole anti-collision (obligatoires avant push)

1. `bash dev-hub/tools/check-migration-basename-collisions.sh` — collision
   intra-branche (#1962).
2. `node dev-hub/tools/check-migration-prefixes.mjs --local <dir> <main>`
   — collision inter-PR (#5437) : un préfixe `0000NN` déjà pris (sur main ou
   sur une autre PR ouverte) = renuméroté, la plus ancienne conserve son
   préfixe.
3. `bash dev-hub/tools/check-migrations-tenant-schema.sh` — helpers qualifiés
   (#1613).
4. `bash dev-hub/tools/check-migrations-bc-conventions.sh` — conventions du
   présent document (nommage #5431, forme `return new class` + `down()`,
   réentrance `schemaTableExists`, zéro chaîne parallèle, zéro FK
   tenant→public, `strict_types`).
5. `bash dev-hub/tools/check-mvp-schema-parity.sh api` — toute table tenant
   récente couverte par `RefreshTenantDatabase` ou `CreatesMvpSchema` (#5443).

## 8. Tests du runner

`php artisan test --filter=LeopardoMigrateRunnerTest` (ou
`tests/Feature/Database/LeopardoMigrateRunnerTest.php`) :
- `leopardo:migrate` réentrant (double exécution sans erreur) ;
- tables tenant → `shared_tenants`, tables public → `public`
  (via `information_schema`) ;
- cycle migrate → rollback → migrate sur une migration temporaire isolée ;
- cycle `--fresh` : schémas recréés, tables canoniques présentes, re-rerun
  stable.
