<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1813 — Workflow de validation des modifications de taux légaux.
 *
 * Les tables `tax_slabs` et `social_contributions` alimentent directement les
 * bulletins (AbstractCountryRules::resolveTaxSlabsFromDatabase /
 * resolveContributionRate) : une modification erronée affecterait toutes les
 * paies suivantes. Ce workflow protège ces tables :
 *
 * - `status` : draft → pending_validation → active | superseded (défaut
 *   'active' pour les lignes existantes : rétrocompatibilité totale) ;
 * - `tax_rate_change_log` : audit trail append-only (aucun UPDATE/DELETE
 *   possible au niveau base + modèle) ;
 * - seules les lignes `active` sont utilisées dans les calculs.
 */
return new class extends Migration
{
    private const TABLES = ['tax_slabs', 'social_contributions'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('status', 20)->default('active')->index();
                $blueprint->unsignedBigInteger('submitted_by')->nullable();
                $blueprint->unsignedBigInteger('validated_by')->nullable();
                $blueprint->timestamp('validated_at')->nullable();
                $blueprint->text('rejection_reason')->nullable();
            });

            // Rétrocompat : les lignes EXISTANTES restent 'active' (défaut posé
            // ci-dessus), mais toute NOUVELLE ligne créée sans statut explicite
            // doit passer par le workflow → défaut 'draft' désormais.
            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN status SET DEFAULT \'draft\'', $table));
        }

        if (! Schema::hasTable('tax_rate_change_log')) {
            Schema::create('tax_rate_change_log', function (Blueprint $table): void {
                $table->id();
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
                $table->index('created_at');
            });

            // Append-only : aucun UPDATE/DELETE possible pour le rôle applicatif.
            DB::statement('REVOKE UPDATE, DELETE ON TABLE tax_rate_change_log FROM PUBLIC');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rate_change_log');

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'status', 'submitted_by', 'validated_by', 'validated_at', 'rejection_reason',
                ]);
            });
        }
    }
};
