<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retire la contrainte UNIQUE globale sur companies.schema_name.
 *
 * En mode multi-tenant "shared", toutes les entreprises pointent vers le meme
 * schema (`shared_tenants`) donc l'unicite globale empeche de provisionner
 * plus d'une societe shared. On remplace la contrainte par un index unique
 * partiel qui ne s'applique qu'au mode `schema` (Enterprise), ou le
 * schema_name doit rester unique par entreprise.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.companies DROP CONSTRAINT IF EXISTS companies_schema_name_unique');
            DB::statement('DROP INDEX IF EXISTS public.companies_schema_name_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS companies_schema_name_unique_schema_mode
                ON public.companies (schema_name)
                WHERE tenancy_type = \'schema\'');
            DB::statement('CREATE INDEX IF NOT EXISTS companies_schema_name_index ON public.companies (schema_name)');

            return;
        }

        Schema::table('companies', function ($table): void {
            try {
                $table->dropUnique('companies_schema_name_unique');
            } catch (Throwable $e) {
                // Ignore si l'index n'existe pas (ex: sqlite en test).
            }
            $table->index('schema_name', 'companies_schema_name_index');
        });
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('companies')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS public.companies_schema_name_unique_schema_mode');
            DB::statement('DROP INDEX IF EXISTS public.companies_schema_name_index');
            DB::statement('ALTER TABLE public.companies ADD CONSTRAINT companies_schema_name_unique UNIQUE (schema_name)');

            return;
        }

        Schema::table('companies', function ($table): void {
            try {
                $table->dropIndex('companies_schema_name_index');
            } catch (Throwable $e) {
                // noop
            }
            $table->unique('schema_name', 'companies_schema_name_unique');
        });
    }
};
