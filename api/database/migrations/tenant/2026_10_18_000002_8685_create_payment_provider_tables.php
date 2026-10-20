<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-38 PAIEMENTS (#8685) — registre de fournisseurs administrable.
 *
 * `payment_provider_instances` est la table de CONFIGURATION : une ligne = un
 * moyen de paiement disponible sur la plateforme, cree par un admin depuis le
 * Superadmin. `type_code` reference un type du CODE (registre
 * `ProviderTypeRegistry`) — ajouter un moyen de paiement d'un type deja
 * implemente ne demande donc AUCUNE ligne de code.
 *
 * `company_id` nullable : NULL = instance globale (tous les tenants), sinon
 * instance dediee a un tenant, qui surcharge l'instance globale.
 *
 * `config` est chiffree au niveau modele (cast `encrypted:array`) : la colonne
 * est du texte, illisible en clair en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_instances', function (Blueprint $table): void {
            $table->id();

            // NULL = instance globale ; sinon instance dediee au tenant.
            $table->uuid('company_id')->nullable()->index();

            // Reference un type du code (ProviderTypeRegistry).
            $table->string('type_code', 60);
            $table->string('display_name', 120);

            // Listes ISO 3166-1 alpha-2 / ISO 4217.
            $table->jsonb('countries')->nullable();
            $table->jsonb('currencies')->nullable();

            // active | inactive | sandbox
            $table->string('status', 20)->default('inactive');

            // Chiffre at-rest (cast encrypted:array) — jamais de secret en clair.
            $table->text('config')->nullable();

            // Priorite de resolution par marche (plus petit = plus prioritaire).
            $table->integer('priority')->default(100);

            $table->timestampsTz();

            $table->index(['type_code', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('payment_fee_rules', function (Blueprint $table): void {
            $table->id();

            // NULL = regle globale ; sinon regle propre au tenant.
            $table->uuid('company_id')->nullable()->index();

            // cash_in | cash_out | internal_transfer | refund
            $table->string('operation_type', 30);

            // NULL = regle valable pour toutes les instances.
            $table->unsignedBigInteger('provider_instance_id')->nullable();

            // fixed | percentage | mixed
            $table->string('mode', 20);

            // fixed : montant en plus petite unite ; percentage : points de base (1 % = 100).
            $table->bigInteger('value');
            $table->char('currency', 3)->nullable();

            $table->bigInteger('min_amount')->nullable();
            $table->bigInteger('max_amount')->nullable();

            $table->integer('priority')->default(100);

            $table->timestampsTz();

            $table->index(['company_id', 'operation_type']);
            $table->index('provider_instance_id');
        });

        Schema::create('payment_webhook_endpoints', function (Blueprint $table): void {
            $table->id();

            $table->uuid('company_id')->nullable()->index();
            $table->unsignedBigInteger('provider_instance_id');

            // Le token identifie l'instance SANS ambiguite — jamais de parsing
            // du payload ni du nom du fournisseur pour deviner qui appelle.
            $table->string('token', 64)->unique('payment_webhook_endpoints_token_unique');
            $table->string('path', 190);

            // Chiffre at-rest (cast encrypted).
            $table->text('secret_signature')->nullable();

            $table->string('status', 20)->default('active');

            $table->timestampsTz();

            $table->index(['provider_instance_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_endpoints');
        Schema::dropIfExists('payment_fee_rules');
        Schema::dropIfExists('payment_provider_instances');
    }
};
