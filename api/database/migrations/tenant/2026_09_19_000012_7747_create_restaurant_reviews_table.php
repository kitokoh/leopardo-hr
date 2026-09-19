<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7747 (RESTO-902) - RestaurantManager : avis clients publics.
 *
 * `restaurant_reviews` : un avis client par commande servie/livree, soumis
 * depuis la page publique par slug (statut initial `pending`, modere par le
 * gerant avant publication) :
 * - `order_reference` : reference de la commande (`RST-` + aleatoire, deja
 *   non enumerable — meme stockage que RESTO-805) ; unique par tenant =
 *   UN SEUL avis par commande ;
 * - `rating` 1..5 (tinyint), `comment` nullable, `author_name` ;
 * - `status` : pending | published | rejected (defaut pending) ;
 * - index (branch_id, status) pour la lecture publique des avis publies.
 *
 * Tenant-scoped, sans FK vers `companies` (constitution §II). Additive et
 * reentrante : garde `schemaTableExists` (regle #5431), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('restaurant_reviews')) {
            return;
        }

        Schema::create('restaurant_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('branch_id');
            $table->string('order_reference', 40);
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('author_name', 120);
            $table->string('status', 20)->default('pending');

            $table->timestamps();

            $table->unique(['company_id', 'order_reference'], 'restaurant_reviews_company_order_reference_unique');
            $table->index(['branch_id', 'status'], 'restaurant_reviews_branch_status_idx');
            $table->index(['company_id', 'status'], 'restaurant_reviews_company_status_idx');
        });

        DB::statement("COMMENT ON TABLE restaurant_reviews IS 'Avis clients publics - un avis par commande (unique tenant+order_reference), moderation pending/published/rejected (RESTO-902/#7747).';");
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reviews');
    }
};
