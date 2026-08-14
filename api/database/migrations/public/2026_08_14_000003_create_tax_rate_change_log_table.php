<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADMIN-PAIE (#1813) — audit trail immuable des modifications de taux légaux.
 *
 * Table append-only : un trigger PostgreSQL (créé ci-dessous) interdit tout
 * UPDATE/DELETE — seul INSERT est possible, conformément au cahier des
 * charges (« GRANT INSERT, SELECT »). Le modèle Eloquent correspondant
 * désactive les timestamps (pas d'updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tax_rate_change_log')) {
            Schema::create('tax_rate_change_log', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('table_name', 50); // 'tax_slabs' | 'social_contributions'
                $table->unsignedBigInteger('record_id');
                $table->string('action', 30); // created | submitted | approved | rejected | superseded
                $table->unsignedBigInteger('actor_id');
                $table->string('actor_role', 30);
                $table->jsonb('previous_value')->nullable();
                $table->jsonb('new_value');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['table_name', 'record_id']);
                $table->index(['action']);
                $table->index(['actor_id']);
            });
        }

        // Append-only dur : refuse UPDATE/DELETE au niveau PostgreSQL.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_tax_rate_change_log_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'tax_rate_change_log is append-only (UPDATE/DELETE interdit)';
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_tax_rate_change_log_append_only ON tax_rate_change_log
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_tax_rate_change_log_append_only
                BEFORE UPDATE OR DELETE ON tax_rate_change_log
                FOR EACH ROW EXECUTE FUNCTION prevent_tax_rate_change_log_mutation()
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_tax_rate_change_log_append_only ON tax_rate_change_log');
        }

        Schema::dropIfExists('tax_rate_change_log');
    }
};
