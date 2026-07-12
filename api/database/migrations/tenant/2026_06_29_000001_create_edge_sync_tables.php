<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nettoyage de sécurité en cas de reliquat d'une migration legacy ou d'un échec partiel
        Schema::dropIfExists('edge_licenses');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('edge_nodes');

        // Edge nodes — one per client site
        Schema::create('edge_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->index(); // NULL en mode schema isolé — pas de FK cross-schema
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('site_address')->nullable();
            $table->string('status')->default('active');  // active|inactive|suspended
            $table->string('mode')->default('hybrid');    // cloud|offline|hybrid
            $table->string('license_key')->unique()->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('local_ip')->nullable();
            $table->string('public_ip')->nullable();
            $table->string('edge_version')->default('1.0.0');
            $table->json('capabilities')->default('{}');
            $table->json('metadata')->default('{}');
            $table->timestamps();
        });

        // Sync audit logs
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('edge_node_id')->index();
            $table->string('direction');        // push|pull|bidirectional
            $table->string('status');           // pending|running|success|partial|failed
            $table->integer('records_sent')->default(0);
            $table->integer('records_received')->default(0);
            $table->integer('conflicts_detected')->default(0);
            $table->integer('conflicts_resolved')->default(0);
            $table->text('error_message')->nullable();
            $table->json('summary')->default('{}');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->cascadeOnDelete();
            $table->index(['edge_node_id', 'started_at']);
        });

        // Outbound sync queue (Edge → Cloud)
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('edge_node_id')->index();
            $table->string('entity_type');   // attendance_logs, absences, etc.
            $table->string('entity_id');     // UUID of local record
            $table->string('operation');     // create|update|delete
            $table->json('payload');
            $table->string('status')->default('pending');   // pending|processing|synced|conflict|failed
            $table->integer('attempt_count')->default(0);
            $table->string('conflict_resolution')->nullable(); // local_wins|cloud_wins|manual
            $table->text('conflict_note')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->cascadeOnDelete();
            $table->index(['edge_node_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });

        // Signed offline licenses
        Schema::create('edge_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->index(); // NULL en mode schema isolé — pas de FK cross-schema
            $table->uuid('edge_node_id')->unique(); // one license per node
            $table->string('license_key')->unique();
            $table->text('signed_payload');          // JWT-signed blob
            $table->json('allowed_features')->default('[]');
            $table->integer('max_employees')->default(50);
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('validation_status')->default('valid'); // valid|expired|revoked|pending_renewal
            $table->timestamps();

            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_licenses');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('edge_nodes');
    }
};
