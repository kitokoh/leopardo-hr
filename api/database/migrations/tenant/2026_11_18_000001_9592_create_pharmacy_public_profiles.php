<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vitrine publique Pharmacie — opt-in de publication (#9592).
 *
 * ## Pourquoi cette table
 *
 * Le module Pharmacy (BC PharmaManager) n'avait AUCUNE sémantique de
 * publication : ni `is_public`, ni `public_slug`. Conséquence mesurable :
 * `GET /api/v1/public/discover?type=pharmacy` ne pouvait rien renvoyer, et la
 * vitrine `/pharmacies` affichait « bientôt disponible » en permanence.
 *
 * Les autres verticales ont déjà leur opt-in (Restaurant :
 * `is_published_online` ; Retail : `retail_online_settings.enabled` ; Trade :
 * `trade_company_profiles.is_public`). Cette table est l'équivalent pour
 * l'officine, et suit le même patron que `retail_online_settings` : une ligne
 * par tenant, `company_id` UNIQUE, create-or-update.
 *
 * ## Invariants
 *
 * - `enabled` est l'opt-in **fail-closed** : défaut `false`. Tant qu'il est
 *   faux, l'officine n'apparaît nulle part côté public, même si les autres
 *   colonnes sont remplies.
 * - `public_slug` est l'identifiant public (jamais `company_id`).
 * - Aucune donnée réglementée ici : ni stock, ni lots, ni ordonnances, ni
 *   prix. La vitrine présente l'officine, elle ne vend pas de médicament.
 *
 * Tenant-scoped, sans FK (conventions migrations tenant §2.6).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (schemaTableExists('pharmacy_public_profiles')) {
            return;
        }

        Schema::create('pharmacy_public_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('company_id')->unique();

            // Opt-in de publication — défaut false, jamais publié par accident.
            $table->boolean('enabled')->default(false);

            // Identité publique de l'officine.
            $table->string('public_slug')->nullable();
            $table->string('public_name')->nullable();
            $table->string('public_city')->nullable();
            $table->text('public_description')->nullable();
            $table->string('public_phone')->nullable();

            // Garde de nuit : information de service public, pas une donnée
            // commerciale. `null` = inconnu (la vitrine n'invente pas).
            $table->boolean('on_duty')->default(false);

            // Services déclarés (liste libre : vaccination, orthopédie…).
            $table->jsonb('services')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['enabled', 'public_slug']);
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('pharmacy_public_profiles')) {
            return;
        }

        Schema::drop('pharmacy_public_profiles');
    }
};
