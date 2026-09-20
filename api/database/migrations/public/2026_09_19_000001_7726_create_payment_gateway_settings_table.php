<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7726 (BC-21 BILLING) — configuration des passerelles de paiement (Stripe,
 * Chargily) paramétrable depuis l'admin plateforme.
 *
 * Jusqu'ici, toute la configuration PSP vivait dans les variables
 * d'environnement (`config/services.php`) : changer une clé Stripe exigeait un
 * redéploiement (constat D8 de BC-21-BILLING-MATURITY). Cette table porte la
 * configuration ÉDITABLE ; `GatewaySettingsService` applique la précédence
 * BDD → fallback env (zéro rupture pour les déploiements actuels).
 *
 * Volontairement GLOBALE (schéma public, pas de company_id) : ce sont les
 * comptes PSP de la PLATEFORME, jamais ceux d'un tenant (les comptes des
 * tenants vivent dans `tenant_payment_profiles`, #7727).
 *
 * Sécurité : `secrets` est chiffré au repos (cast Laravel `encrypted:array`
 * sur le modèle `PaymentGatewaySetting`) — la colonne ne contient jamais de
 * clé en clair. L'API ne renvoie qu'un masque (`sk_live_••••1234`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_gateway_settings')) {
            return;
        }

        Schema::create('payment_gateway_settings', function (Blueprint $table): void {
            $table->id();

            // Une ligne par passerelle (stripe | chargily) : la config se met
            // à jour, elle ne se duplique pas.
            $table->string('gateway', 30)->unique();
            $table->string('mode', 10)->default('test'); // test | live

            // Configuration NON secrète (price IDs Stripe par plan, options…).
            $table->jsonb('config')->nullable();

            // Secrets chiffrés au repos (cast `encrypted:array`) : clé API,
            // webhook secret. Text : le ciphertext dépasse largement 255.
            $table->text('secrets')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit : qui a modifié la configuration en dernier (super admin).
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
