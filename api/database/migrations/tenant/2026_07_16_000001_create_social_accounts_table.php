<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Marketing — Phase 1.
 *
 * social_accounts : connexion d'un tenant a un profil d'agregateur de
 * publication reseaux sociaux (Ayrshare ou equivalent). On ne stocke
 * volontairement PAS de tokens OAuth Meta/LinkedIn/X bruts ici : c'est
 * l'agregateur qui gere le cycle de vie OAuth et le refresh cote lui.
 * On ne persiste que la reference chiffree au profil (ex: Ayrshare
 * Profile Key) necessaire pour appeler leur API en son nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_accounts')) {
            return;
        }

        Schema::create('social_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // Fournisseur d'agregation (ayrshare, buffer, ...). Permet de changer
            // de fournisseur plus tard sans migration de schema.
            $table->string('provider', 30)->default('ayrshare');

            // Reference chiffree au profil cote agregateur (ex: Ayrshare Profile Key).
            // Chiffre via cast Eloquent 'encrypted', jamais logue en clair.
            $table->text('provider_profile_ref');

            // Plateformes activees pour ce profil (linkedin, facebook_page,
            // facebook_group, twitter, ...). Informatif, la verite reste cote
            // agregateur mais utile pour l'UI sans appel reseau.
            $table->json('connected_platforms')->nullable();

            $table->string('display_name', 120)->nullable();
            $table->string('status', 20)->default('active'); // active|revoked|error
            $table->text('last_error')->nullable();
            $table->timestampTz('connected_at')->nullable();

            $table->unsignedInteger('created_by')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['company_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
