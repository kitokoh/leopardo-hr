<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6110 (TRAVEL-907) — Annonces : cycle de paiement + validation + modération.
 *
 * `travel_adverts` : soumission → paiement (prix calculé SERVEUR depuis la
 * grille `travel_advert_prices`) → validation par `travel.manage` →
 * publication. Une annonce n'est visible qu'une fois payée ET validée ;
 * `expires_at` porte la durée de validité (TRAVEL-908/#6111).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_adverts')) {
            return;
        }

        Schema::create('travel_adverts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedBigInteger('advert_type_id');
            $table->unsignedBigInteger('advert_position_id');
            $table->string('title', 160);
            $table->string('content_redacted', 2000);
            $table->unsignedBigInteger('image_asset_id')->nullable();

            // Prix calculé serveur (jamais accepté du client) — minor units.
            $table->unsignedInteger('price_minor');
            $table->char('currency', 3);

            // draft → submitted → paid → validated | rejected → expired/archived.
            $table->string('status', 20)->default('draft');
            $table->string('payment_reference', 60)->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->unsignedBigInteger('validated_by_user_id')->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->string('rejected_reason', 500)->nullable();

            $table->unsignedInteger('validity_days')->default(30);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->index(['company_id', 'status', 'expires_at'], 'travel_adverts_company_status_expiry_idx');
        });

        DB::statement("ALTER TABLE travel_adverts ADD CONSTRAINT travel_adverts_status_check CHECK (status IN ('draft', 'submitted', 'paid', 'validated', 'rejected', 'expired', 'archived'))");
        DB::statement('ALTER TABLE travel_adverts ADD CONSTRAINT travel_adverts_price_check CHECK (price_minor > 0)');
        DB::statement("COMMENT ON TABLE travel_adverts IS 'Annonces payantes — prix serveur, paiement puis validation, durée de validité (TRAVEL-907/#6110, TRAVEL-908/#6111).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_adverts');
    }
};
