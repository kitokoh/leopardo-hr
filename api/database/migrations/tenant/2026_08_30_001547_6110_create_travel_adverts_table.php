<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-907 (#6110) — Annonces payantes (spec §3) : soumission, prix
 * calculé serveur (snapshot du tarif), paiement, validation par
 * `travel.manage`, publication horodatée avec expiration. Une annonce n'est
 * visible qu'une fois payée ET validée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_adverts')) {
            Schema::create('travel_adverts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('advert_type_id');
                $table->unsignedBigInteger('advert_position_id');
                $table->string('title', 200);
                $table->text('body_redacted');
                $table->string('image_path', 500)->nullable();
                $table->unsignedInteger('character_count')->default(0);
                $table->unsignedBigInteger('price_image_minor')->default(0);
                $table->unsignedBigInteger('price_character_minor')->default(0);
                $table->unsignedBigInteger('total_minor')->default(0);
                $table->char('currency', 3);
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('validated_by_user_id')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('moderation_note', 500)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'travel_adverts_company_status_idx');
                $table->index(['company_id', 'advert_type_id'], 'travel_adverts_company_type_idx');
                $table->index(['company_id', 'expires_at'], 'travel_adverts_company_expiry_idx');
            });

            DB::statement("ALTER TABLE travel_adverts ADD CONSTRAINT travel_adverts_status_check CHECK (status IN ('draft', 'pending_payment', 'paid', 'published', 'rejected', 'expired', 'archived'))");
            DB::statement("COMMENT ON TABLE travel_adverts IS 'Annonces payantes — visible uniquement si payee et validee (TRAVEL-907/#6110).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_adverts');
    }
};
