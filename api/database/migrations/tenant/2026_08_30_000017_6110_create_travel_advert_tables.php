<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6108/#6109/#6110/#6111 (TRAVEL-905..908) — Annonces publicitaires.
 *
 * - `travel_advert_types` : types d'annonce (ex. bannière, article sponsorisé).
 * - `travel_advert_positions` : emplacements (ex. home_top, sidebar).
 * - `travel_advert_prices` : grille tarifaire (prix par image + par
 *   caractère, devise, unités mineures) — cohérente avec la devise tenant.
 * - `travel_adverts` : cycle de vie submit → paid → validated → published
 *   → expired/archived. Une annonce n'est VISIBLE qu'une fois payée ET
 *   validée (critère d'acceptation) ; expiration par job (valid_until).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_advert_types')) {
            Schema::create('travel_advert_types', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_advert_types_company_code_unique');
            });
        }

        if (! schemaTableExists('travel_advert_positions')) {
            Schema::create('travel_advert_positions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 60);
                $table->string('name', 160);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'travel_advert_positions_company_code_unique');
            });
        }

        if (! schemaTableExists('travel_advert_prices')) {
            Schema::create('travel_advert_prices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('type_id');
                $table->unsignedBigInteger('position_id');
                $table->unsignedBigInteger('price_per_image_minor')->default(0);
                $table->unsignedBigInteger('price_per_character_minor')->default(0);
                $table->char('currency', 3);
                $table->timestamps();

                $table->unique(['company_id', 'type_id', 'position_id'], 'travel_advert_prices_company_type_pos_unique');
            });
        }

        // NB: travel_adverts est créée par la migration canonique 2026_08_30_001548 (schéma #6110 TRAVEL-907/908 — contenu_redacted/price_minor/validity_days/validated).
        // Cette génération antérieure (000017) ne crée plus que travel_advert_types/positions/prices.
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_adverts');
        Schema::dropIfExists('travel_advert_prices');
        Schema::dropIfExists('travel_advert_positions');
        Schema::dropIfExists('travel_advert_types');
    }
};
