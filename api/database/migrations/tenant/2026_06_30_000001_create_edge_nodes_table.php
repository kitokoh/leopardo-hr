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
        Schema::dropIfExists('edge_nodes');
    }
};
