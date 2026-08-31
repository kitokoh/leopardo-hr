<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-23-D07 (issue #6239) — asynchronisme IA : file idempotente + DLQ dédiée.
 *
 * `ai_exports` : exports asynchrones de conversations IA (idempotents via
 * dédup `(company_id, conversation_id, format)`, cycle pending → processing →
 * done/failed). `ai_dead_letter_queue` : dead-letter queue IA (échecs après
 * épuisement des retries), replay contrôlé via `php artisan ai:dlq:replay`.
 * Additives, sans réécriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('ai_exports')) {
            Schema::create('ai_exports', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('user_id')->index();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('format', 20)->default('json');
                // Clé de déduplication (idempotence) : une seule exportation par
                // (tenant, conversation, format) — rejouer la demande renvoie
                // l'exportation existante, jamais de doublon.
                $table->string('dedup_key', 191)->unique();
                $table->string('status', 20)->default('pending')->index();
                $table->text('file_path')->nullable();
                $table->text('error_message')->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Pas de FK vers `companies` (table publique) : convention repo
                // « FK tenant→public interdites » (MIGRATIONS_CONVENTIONS.md) —
                // le scope tenant reste porté par `company_id` + index.
                $table->foreign('user_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        if (! schemaTableExists('ai_dead_letter_queue')) {
            Schema::create('ai_dead_letter_queue', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('job_class', 191);
                $table->unsignedBigInteger('job_id')->nullable();
                $table->string('dedup_key', 191)->nullable()->unique();
                $table->jsonb('payload')->default('{}');
                $table->text('error');
                $table->unsignedInteger('attempts')->default(0);
                $table->string('status', 20)->default('open')->index();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('resolved_at')->nullable();

                $table->index(['company_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_dead_letter_queue');
        Schema::dropIfExists('ai_exports');
    }
};
