<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COULOIR 16 — M0 · Réglages de plateforme (schéma `public`).
 *
 * Le couloir 16 exige un seuil de catégories RÉGLABLE depuis le Superadmin :
 * « une catégorie n'apparaît que si la ville compte au moins N Pages
 * publiées dedans ». Au HEAD, aucun modèle `PlatformSetting` ni table
 * `platform_settings` n'existe — le seuil ne peut donc pas être réglé, et
 * une constante codée en dur serait exactement ce que la règle d'architecture
 * n°1 interdit (une règle métier dans le canal).
 *
 * Table volontairement GÉNÉRIQUE (clé/valeur JSON) plutôt qu'une colonne par
 * réglage : les réglages de plateforme sont transverses et leur nombre croît.
 * Une colonne par réglage imposerait une migration à chaque nouveau seuil.
 *
 * Aucune donnée métier, aucun `company_id` : c'est un réglage de PLATEFORME,
 * pas de tenant. Il vit donc en schéma `public`, jamais dans un schéma tenant.
 *
 * Idempotente (règle « une table, une migration ») : `hasTable` en tête.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('public.platform_settings')) {
            return;
        }

        // Qualifié `public.` explicitement : le `search_path` applicatif est
        // `shared_tenants,public` (config/database.php:97). Sans qualification,
        // un `migrate --path` lancé hors du runner `leopardo:migrate` créerait
        // la table dans `shared_tenants` — observé en local. Le runner force
        // `search_path=public`, mais une migration ne doit pas dépendre de
        // l'ordre dans lequel on l'invoque.
        Schema::create('public.platform_settings', function (Blueprint $table): void {
            $table->string('key', 120)->primary();

            // Valeur JSON : un seuil scalaire, une carte par ville, etc.
            $table->jsonb('value');

            $table->string('description', 255)->nullable();

            // Qui a changé le réglage — trace d'audit minimale, sans FK
            // (un super admin supprimé ne doit pas casser la ligne).
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.platform_settings');
    }
};
