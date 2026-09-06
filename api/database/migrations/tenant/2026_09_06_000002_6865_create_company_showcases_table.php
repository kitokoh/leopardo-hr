<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6865 (BC-27 SHOWCASE) - Vitrine entreprise : table `company_showcases`.
 *
 * - `company_id` unique : une seule vitrine par tenant (création 1-clic,
 *   spec SOLUTION_SITE_VITRINE.md §5 — US1) ;
 * - `slug` unique global : consultation publique `/vitrine/{slug}`
 *   (v1, pas de sous-domaine) ;
 * - `status` `draft|published` (enum PHP cote code), `theme` (3 themes v1
 *   en V-THEMES #6868, defaut US1), `settings` JSON (variables : couleurs,
 *   logo_id...), `custom_domain` nullable reserve phase 2, `published_at`.
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
                $table->uuid('company_id');

                $table->string('slug', 160);
                $table->string('status', 20)->default('draft');
                $table->string('theme', 60)->default('default');
                $table->json('settings')->nullable();
                $table->string('custom_domain', 190)->nullable();
                $table->timestamp('published_at')->nullable();

                $table->timestamps();

                $table->unique('company_id', 'company_showcases_company_unique');
                $table->unique('slug', 'company_showcases_slug_unique');
            });

            DB::statement("COMMENT ON TABLE company_showcases IS 'Vitrine publique du tenant - une seule par compagnie, slug unique global, statut draft|published (BC-27/#6865).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_showcases');
    }
};
