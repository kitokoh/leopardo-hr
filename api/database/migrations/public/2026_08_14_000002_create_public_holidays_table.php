<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1811 — Jours fériés par pays (table publique, partagée entre
 * tenants) : remplace la constante STANDARD_WORKING_DAYS = 22 du moteur de
 * paie par un calcul dynamique des jours ouvrés réels.
 *
 * - company_id NULL → férié national (tous les tenants du pays)
 * - company_id NOT NULL → férié spécifique à une entreprise (pont, fermeture)
 * - is_recurring = true → se répète chaque année (MM-DD dans month_day)
 * - holiday_type = 'islamic' → date saisie manuellement par année (l'issue
 *   #1812 ajoutera le calcul automatique)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Render peut rejouer les migrations (entrypoint) : idempotence.
        if (Schema::hasTable('public_holidays')) {
            return;
        }

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('country_code', 2);
            $table->string('name', 120);
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->boolean('is_recurring')->default(false);
            $table->string('month_day', 5)->nullable();
            $table->string('holiday_type', 30)->default('fixed');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'year']);
            $table->index(['company_id', 'country_code', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
