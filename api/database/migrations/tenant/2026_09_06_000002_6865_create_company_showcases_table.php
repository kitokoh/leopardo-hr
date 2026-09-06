<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6865 (BC-27 SHOWCASE) - Site vitrine 1-clic : table `company_showcases`.
 *
 * - `company_id` unique : une seule vitrine par tenant (creation 1-clic, US1,
 *   spec SOLUTION_SITE_VITRINE.md §5) ;
 * - `slug` unique global : URL publique stable `/vitrine/{slug}` (US3) ;
 * - `status` `draft|published`, `theme` (defaut `industry`), `settings` JSON
 *   (variables : couleurs, logo_id...), `custom_domain` nullable (phase 2),
 *   `published_at` nullable.
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommes, conventions
 * migrations tenant §2.6 — pattern RestaurantManager #6167). Idempotente +
 * down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcases')) {
            Schema::create('company_showcases', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->unique();

                $table->string('slug', 130)->unique();
                $table->string('status', 20)->default('draft');
                $table->string('theme', 50)->default('industry');
                $table->json('settings')->nullable();
                $table->string('custom_domain')->nullable();
                $table->timestamp('published_at')->nullable();

                $table->timestamps();
            });

            DB::statement("COMMENT ON TABLE company_showcases IS 'Site vitrine public du tenant - une seule par compagnie, slug unique global, statut draft|published (BC-27/#6865).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_showcases');
    }
};
