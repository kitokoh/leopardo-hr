<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // 1. Update user_lookups in public schema
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        Schema::table('user_lookups', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->change();
            $table->string('schema_name', 63)->nullable()->change();
        });

        // 2. Update employees in tenant schema
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO shared_tenants,public');
        }

        if (Schema::hasTable('employees')) {
            // #9897 (RLS) — `->change()` sur `company_id` etait INCOMPATIBLE avec
            // la politique RLS `employees_tenant_isolation`, qui porte
            // `company_id::text = current_setting(...)` : PostgreSQL refuse
            // `ALTER COLUMN ... TYPE` sur une colonne referencee par une policy
            // (SQLSTATE 0A000, « cannot alter type of a column used in a policy
            // definition »). L'echec avortait la transaction ambiante, d'ou la
            // cascade `25P02` qui masquait cette cause reelle
            // (`MigrationSchemaPlacementTest::test_artisan_migrate_is_idempotent`).
            //
            // Le DDL est donc pose en PostgreSQL BRUT, sans passer par
            // `->change()` : on peut ainsi viser exactement l'effet voulu
            // (retirer le NOT NULL, garder le type) sans reecrire la colonne.
            // `DROP POLICY`/`CREATE POLICY` n'est pas necessaire : on ne touche
            // ni au type ni a la definition de la policy.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE employees ALTER COLUMN company_id DROP NOT NULL');
                DB::statement('ALTER TABLE employees ALTER COLUMN role TYPE varchar(20)');
                DB::statement("ALTER TABLE employees ALTER COLUMN role SET DEFAULT 'employee'");
            } else {
                Schema::table('employees', function (Blueprint $table) {
                    $table->uuid('company_id')->nullable()->change();
                    $table->string('role', 20)->default('employee')->change();
                });
            }

            // Idempotent : la contrainte est recreee a chaque passage. Sans ce
            // DROP, un second `up()` leverait 42710 (constraint already exists)
            // — et la migration est justement rejouee telle quelle par
            // `test_migration_guards_are_idempotent_when_re_run`.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_role_check');
                DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_role_check CHECK (role IN ('manager', 'employee', 'ordinary'))");
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }
    }

    public function down(): void {}
};
