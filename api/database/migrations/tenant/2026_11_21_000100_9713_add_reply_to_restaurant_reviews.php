<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9713 (RESTO-902, volet gestion) — Réponse du restaurateur à un avis.
 *
 * Ajoute à `restaurant_reviews` :
 * - `reply` : réponse publique du restaurateur (nullable, texte libre) ;
 * - `replied_at` : horodatage de la réponse (nullable) ;
 * - `replied_by` : employé auteur de la réponse (nullable, pas de FK — la
 *   table `employees` vit dans le schéma public, constitution §II).
 *
 * La réponse n'est exposée publiquement que si l'avis est `published` : un
 * avis rejeté ne publie jamais sa réponse. Additive et réentrante (garde
 * `Schema::hasColumn`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_reviews')) {
            return;
        }

        Schema::table('restaurant_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_reviews', 'reply')) {
                $table->text('reply')->nullable();
            }

            if (! Schema::hasColumn('restaurant_reviews', 'replied_at')) {
                $table->timestamp('replied_at')->nullable();
            }

            if (! Schema::hasColumn('restaurant_reviews', 'replied_by')) {
                $table->unsignedBigInteger('replied_by')->nullable();
            }
        });

        DB::statement("COMMENT ON COLUMN restaurant_reviews.reply IS 'Reponse publique du restaurateur a l avis (null = pas de reponse) - #9713.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_reviews')) {
            return;
        }

        Schema::table('restaurant_reviews', function (Blueprint $table): void {
            foreach (['reply', 'replied_at', 'replied_by'] as $column) {
                if (Schema::hasColumn('restaurant_reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
