<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verticale FUNDRAISING (cagnottes solidaires) — table `fundraisers`.
 *
 * Spec : docs/specifications/SOLUTION_FUNDRAISING.md §3.1.
 *
 * - `slug` unique global : consultation publique `/public/fundraisers/{slug}` ;
 * - `collected_amount` / `contributions_count` dénormalisés, mis à jour en
 *   transaction (lockForUpdate) par ApplyPaymentUpdate — jamais recalculés
 *   en lecture ;
 * - `status` string `draft|active|paused|completed|closed|cancelled`
 *   (enum PHP côté code), fenêtre de collecte `starts_at..ends_at` ;
 * - `suggested_amounts` JSON (montants suggérés façon GoFundMe),
 *   `min_amount`/`max_amount` garde-fous anti-abus.
 *
 * Tenant-scoped (`company_id` uuid), sans FK (colonnes simples + index
 * nommés, conventions migrations tenant §2.6). Idempotente + down() qui ne
 * détruit que ce que ce fichier crée (leçon #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fundraisers')) {
            Schema::create('fundraisers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');

                $table->string('slug', 160);
                $table->string('title', 190);
                $table->text('description')->nullable();
                $table->string('beneficiary_name', 190);
                $table->string('beneficiary_contact', 190)->nullable();
                $table->string('category', 40)->default('other');

                $table->decimal('goal_amount', 15, 2)->nullable();
                $table->decimal('collected_amount', 15, 2)->default(0);
                $table->unsignedInteger('contributions_count')->default(0);
                $table->string('currency', 3)->default('XOF');
                $table->json('suggested_amounts')->nullable();
                $table->decimal('min_amount', 15, 2)->nullable();
                $table->decimal('max_amount', 15, 2)->nullable();

                $table->string('cover_image_path')->nullable();
                $table->string('status', 20)->default('draft');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->uuid('created_by')->nullable();

                $table->timestamps();

                $table->unique('slug', 'fundraisers_slug_unique');
                $table->index(['company_id', 'status'], 'fundraisers_company_status_index');
            });

            DB::statement("COMMENT ON TABLE fundraisers IS 'Cagnottes solidaires du tenant (verticale FUNDRAISING) : lien public par slug unique, compteurs dénormalisés transactionnels, statut draft|active|paused|completed|closed|cancelled.';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraisers');
    }
};
