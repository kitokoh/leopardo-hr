<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 (BC-17 RETAIL, epic Leopardo Marche) — Comptes acheteurs grand
 * public de la marketplace.
 *
 * Table volontairement CENTRALE (schema public, PAS de company_id) : la
 * marketplace est cross-tenant, un acheteur n'appartient a aucun vendeur —
 * meme pattern que `marketing_leads` / `acquisition_funnel_events`
 * (pre-tenant / hors-tenant). Inscription legere : email unique + mot de
 * passe hashe + nom + telephone optionnel (RGPD, donnees minimales).
 *
 * Sans FK, idempotente (regle « une table, une migration »).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_buyers')) {
            return;
        }

        Schema::create('marketplace_buyers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160);
            $table->string('email', 160)->unique();
            $table->string('password');
            $table->string('phone', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_buyers');
    }
};
