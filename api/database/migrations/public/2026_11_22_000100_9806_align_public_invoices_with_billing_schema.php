<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9806 — `public.invoices` divergente du schéma Billing.
 *
 * ## Le bug
 *
 * `billing:generate-invoices` échouait hors CI :
 *
 *   SQLSTATE[42703]: Undefined column: 7 ERROR:  column "subscription_id" does not exist
 *
 * ## Pourquoi
 *
 * Deux tables `invoices` coexistent :
 *
 *   - `public.invoices`         — créée par 2026_04_01_000003 (13 colonnes, héritée)
 *   - `shared_tenants.invoices` — créée par 2026_05_11_000003 (17 colonnes, Billing)
 *
 * `App\Modules\Billing\Domain\Models\Invoice` n'épingle aucun schéma : il résout
 * par le `search_path`, qui DIFFÈRE selon l'environnement :
 *
 *   - CI migre le tenant avec `DB_SEARCH_PATH=shared_tenants` → la table riche
 *     est créée → 14/14 tests verts ;
 *   - dev/production utilisent `public,shared_tenants` → `schemaTableExists('invoices')`
 *     répond `true` (elle trouve `public.invoices`) → la migration tenant SAUTE la
 *     création → c'est la table PAUVRE qui est résolue.
 *
 * La garde `if (! schemaTableExists('invoices'))` du CREATE tenant teste donc
 * l'EXISTENCE, pas la CONFORMITÉ.
 *
 * ## Le correctif
 *
 * Rendre `public.invoices` conforme au modèle Billing. Ainsi, que l'environnement
 * résolve `public` ou `shared_tenants` en premier, la table porte le schéma attendu.
 *
 * Additif, idempotent (`ADD COLUMN IF NOT EXISTS`), sans destruction de données :
 * `total` est backfillé depuis `amount` pour les lignes héritées.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('public.invoices')) {
            return;
        }

        // Colonnes du modèle Invoice absentes de la table héritée.
        DB::statement('ALTER TABLE public.invoices ADD COLUMN IF NOT EXISTS subscription_id bigint NULL');
        DB::statement('ALTER TABLE public.invoices ADD COLUMN IF NOT EXISTS number varchar(30) NULL');
        DB::statement('ALTER TABLE public.invoices ADD COLUMN IF NOT EXISTS tax_amount numeric(12,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE public.invoices ADD COLUMN IF NOT EXISTS total numeric(12,2) NULL');
        DB::statement('ALTER TABLE public.invoices ADD COLUMN IF NOT EXISTS stripe_invoice_id varchar(100) NULL');

        // Le schéma Billing stocke des chemins PDF longs (500) ; la table héritée
        // était en 255. Élargissement sûr (aucune perte).
        DB::statement('ALTER TABLE public.invoices ALTER COLUMN pdf_path TYPE varchar(500)');

        // Backfill : une facture héritée n'a pas de `total` distinct de `amount`.
        DB::statement('UPDATE public.invoices SET total = amount WHERE total IS NULL');

        DB::statement('CREATE INDEX IF NOT EXISTS invoices_subscription_id_index ON public.invoices (subscription_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS invoices_company_id_status_index ON public.invoices (company_id, status)');

        // Garde anti-doublon de #6549 — verrou final en base : le perdant d'une
        // course reçoit 23505 et saute proprement (cf. GenerateMonthlyInvoices).
        // Les lignes héritées (`subscription_id` NULL) ne se gênent pas entre elles :
        // Postgres autorise plusieurs NULL dans un index unique.
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS invoices_company_subscription_period_unique
            ON public.invoices (company_id, subscription_id, period)
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('public.invoices')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS invoices_company_subscription_period_unique');
        DB::statement('DROP INDEX IF EXISTS invoices_company_id_status_index');
        DB::statement('DROP INDEX IF EXISTS invoices_subscription_id_index');

        DB::statement('ALTER TABLE public.invoices DROP COLUMN IF EXISTS stripe_invoice_id');
        DB::statement('ALTER TABLE public.invoices DROP COLUMN IF EXISTS total');
        DB::statement('ALTER TABLE public.invoices DROP COLUMN IF EXISTS tax_amount');
        DB::statement('ALTER TABLE public.invoices DROP COLUMN IF EXISTS number');
        DB::statement('ALTER TABLE public.invoices DROP COLUMN IF EXISTS subscription_id');
    }
};
