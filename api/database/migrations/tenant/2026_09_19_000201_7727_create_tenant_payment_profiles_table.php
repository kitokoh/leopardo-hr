<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7727 (BC-21 BILLING) — profils de paiement du TENANT : clés PSP propres
 * (Stripe), coordonnées bancaires (IBAN/RIB) et mobile money, pour que le
 * client encaisse ses factures sur SON compte (routage Accounting) et affiche
 * ses coordonnées de règlement.
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, aucune FK vers
 * `public.companies` (conventions migrations tenant §2.6) ; le modèle
 * `TenantPaymentProfile` porte `BelongsToCompany` (scope + fail-closed #3727).
 *
 * Sécurité : `secrets` est chiffré au repos (cast `encrypted:array`) — clés
 * API Stripe du tenant, IBAN complet, n° mobile money. `details` ne porte que
 * du non-secret affichable (banque, titulaire, opérateur, derniers chiffres).
 *
 * `stripe_account_id` (nullable) est RÉSERVÉ pour Stripe Connect (onboarding
 * complet = lot ultérieur, ADR à écrire) — aucune logique ne l'exploite encore.
 *
 * Migration réentrante (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('tenant_payment_profiles')) {
            return;
        }

        Schema::create('tenant_payment_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // stripe_keys | bank_account | mobile_money
            $table->string('type', 30);
            $table->string('label', 120);

            // draft (saisi) → verified (contrôlé) → active (utilisé au routage).
            $table->string('status', 20)->default('draft');
            $table->boolean('is_default')->default(false);

            // Non-secret affichable : bank_name, account_holder, operator,
            // masques (iban_last4, phone_last4)…
            $table->jsonb('details')->nullable();

            // Chiffré au repos (cast `encrypted:array`) : secret_key /
            // publishable_key / webhook_secret Stripe, IBAN complet, n° mobile
            // money complet. Text : le ciphertext dépasse largement 255.
            $table->text('secrets')->nullable();

            // Réservé Stripe Connect (acct_…) — lot ultérieur.
            $table->string('stripe_account_id', 64)->nullable();

            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            // Les requêtes du routage : « le profil stripe_keys ACTIF de ce
            // tenant » et la liste par type de l'espace client.
            $table->index(['company_id', 'type', 'status'], 'tenant_payment_profiles_routing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_profiles');
    }
};
