<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6075 (TRAVEL-505) — Historique des exports CSV de rapports.
 *
 * Un export = une requête figée (filtres + hash) → même requête rejouée
 * renvoie le même fichier (idempotence, `request_hash` unique par tenant).
 * L'URL signée est éphémère (30 min) ; l'historique est borné (prune des
 * lignes les plus anciennes au-delà de 50 par tenant, garde anti-accumulation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_report_exports')) {
            Schema::create('travel_report_exports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('report_type', 40);
                $table->string('request_hash', 64);
                $table->json('filters');
                $table->string('storage_path');
                $table->string('mime_type', 80)->default('text/csv; charset=UTF-8');
                $table->unsignedInteger('row_count')->default(0);
                $table->unsignedBigInteger('generated_by_user_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'request_hash'], 'travel_report_exports_hash_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_report_exports');
    }
};
