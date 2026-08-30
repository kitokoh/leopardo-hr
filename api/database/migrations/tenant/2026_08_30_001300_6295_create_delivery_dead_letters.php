<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6295 (BC-26-D07) — Dead-letter queue du module Delivery.
 *
 * File de messages morts tenant-scoped pour les jobs asynchrones du module
 * (clôture lourde, exports) : après épuisement des tentatives, le job
 * enregistre ici sa cause d'échec (payload + erreur) pour rejeu contrôlé
 * via `php artisan delivery:replay-dlq` — jamais de perte silencieuse,
 * jamais de doublon métier (les jobs restent idempotents au rejeu).
 *
 * Tenant-scoped, sans FK, réentrante (schemaTableExists) + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('delivery_dead_letters')) {
            Schema::create('delivery_dead_letters', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('job_class', 255);
                $table->json('payload')->nullable();
                $table->string('queue', 60)->default('delivery');
                $table->text('error')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->string('status', 20)->default('new'); // new|replayed|failed
                $table->timestamp('replayed_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'delivery_dead_letters_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_dead_letters IS 'DLQ des jobs Delivery - rejeu controle via delivery:replay-dlq, jamais de perte silencieuse (BC-26-D07/#6295).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_dead_letters');
    }
};
