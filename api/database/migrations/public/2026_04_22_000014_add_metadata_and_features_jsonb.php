<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * APV - Module boundaries foundation
 *
 * Ajoute:
 *  - companies.features JSONB : source de verite des modules actifs par company
 *    (active/desactive un module sans redeploiement, aligne sur APV L.08)
 *  - companies.metadata JSONB : extension sans migration (APV L.10)
 *  - user_invitations.metadata JSONB : idem
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('SET search_path TO public');

        if (Schema::hasTable('companies')) {
            if (! Schema::hasColumn('companies', 'features')) {
                Schema::table('companies', function (Blueprint $table): void {
                    $table->jsonb('features')->default(DB::raw("'{}'::jsonb"));
                });

                DB::statement("COMMENT ON COLUMN companies.features IS 'Feature flags par module (APV L.08). Ex: {\"rh\":true,\"finance\":false,\"cameras\":false}. Toggle par super-admin, sans redeploiement.'");
            }

            if (! Schema::hasColumn('companies', 'metadata')) {
                Schema::table('companies', function (Blueprint $table): void {
                    $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
                });

                DB::statement("COMMENT ON COLUMN companies.metadata IS 'Champs d extension JSONB (APV L.10). Aucune donnee critique ici.'");
            }

            DB::statement('CREATE INDEX IF NOT EXISTS companies_features_gin ON companies USING GIN (features)');
            DB::statement('CREATE INDEX IF NOT EXISTS companies_metadata_gin ON companies USING GIN (metadata)');
        }

        if (Schema::hasTable('user_invitations') && ! Schema::hasColumn('user_invitations', 'metadata')) {
            Schema::table('user_invitations', function (Blueprint $table): void {
                $table->jsonb('metadata')->default(DB::raw("'{}'::jsonb"));
            });
        }
    }

    public function down(): void
    {
        DB::statement('SET search_path TO public');

        DB::statement('DROP INDEX IF EXISTS companies_features_gin');
        DB::statement('DROP INDEX IF EXISTS companies_metadata_gin');

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table): void {
                if (Schema::hasColumn('companies', 'features')) {
                    $table->dropColumn('features');
                }
                if (Schema::hasColumn('companies', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }

        if (Schema::hasTable('user_invitations') && Schema::hasColumn('user_invitations', 'metadata')) {
            Schema::table('user_invitations', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }
};
