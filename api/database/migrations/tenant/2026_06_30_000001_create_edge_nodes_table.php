<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table edge_nodes
 *
 * Enregistre les nœuds Edge déployés pour chaque tenant.
 * Chaque nœud est identifié par un node_id unique (UUID court généré au provisioning).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('edge_nodes')) {
            // La table edge_nodes est déjà créée par la migration
            // 2026_06_29_000001_create_edge_sync_tables.php (module EdgeSync DDD,
            // schéma UUID). Cette migration légacy visait un schéma bigint
            // différent mais n'est reliée à aucune route active
            // (App\Http\Controllers\Api\V1\EdgeController n'est enregistré
            // dans aucun fichier de routes). On la neutralise pour éviter le
            // conflit "relation edge_nodes already exists" en CI/production.
            return;
        }

        Schema::create('edge_nodes', function (Blueprint $table): void {
            $table->id();

            // Référence tenant
            // Note : pas de FK vers companies car cette table est dans shared_tenants
            // et companies est dans le schéma public (cross-schema FK non supportée en CI).
            $table->unsignedBigInteger('company_id')->index();

            // Identité du nœud
            $table->string('node_id', 64)->unique();
            $table->string('name', 128);
            $table->string('ip_address', 45)->nullable();
            $table->string('version', 32)->nullable();

            // Statut opérationnel
            // 'online' | 'offline' | 'warning' | 'revoked'
            $table->string('status', 16)->default('offline')->index();

            // Heartbeat
            $table->timestamp('last_seen_at')->nullable();

            // File d'attente sync
            $table->unsignedInteger('pending_count')->default(0);

            // Signal de sync demandé depuis le Cloud
            $table->timestamp('sync_requested_at')->nullable();

            // Licence RS256
            $table->boolean('license_valid')->default(false);
            $table->timestamp('license_expires_at')->nullable();

            // Alertes
            $table->boolean('alert_muted')->default(false);
            $table->timestamp('last_alert_sent_at')->nullable();

            // Révocation
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Ne supprimer edge_nodes que si CETTE migration l'a créée (schéma legacy
        // bigint avec colonne node_id). Si la table a été créée par
        // 2026_06_29_000001_create_edge_sync_tables.php (module EdgeSync DDD,
        // schéma UUID sans colonne node_id), ne pas la toucher ici : ce sont
        // sync_logs/sync_queue/edge_licenses (FK vers edge_nodes) qui doivent
        // être rollback avant elle par leur propre migration.
        if (Schema::hasTable('edge_nodes') && Schema::hasColumn('edge_nodes', 'node_id')) {
            Schema::dropIfExists('edge_nodes');
        }
    }
};
