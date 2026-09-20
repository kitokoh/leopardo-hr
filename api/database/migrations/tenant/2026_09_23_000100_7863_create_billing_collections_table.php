<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7863 (Encaissements) — encaissements enregistrés MANUELLEMENT au local
 * (espèces, TPE au comptoir) : un restaurateur/commerçant confirme un
 * encaissement sans passer par un PSP (montant, devise, mode, note, date).
 *
 * Isolation : `company_id` (uuid indexé) porte le tenant, aucune FK vers
 * `public.companies` (conventions migrations tenant §2.6) ; le modèle
 * `BillingCollection` porte `BelongsToCompany` (scope + fail-closed #3727).
 *
 * Migration réentrante (`schemaTableExists`, garde #1613) et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('billing_collections')) {
            return;
        }

        Schema::create('billing_collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // Montant encaissé, toujours positif (validation contrôleur).
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);

            // Mode d'encaissement : cash (espèces) | card_terminal (TPE au
            // comptoir). String, pas d'enum SQL (extensible sans migration).
            $table->string('method', 30)->default('cash');

            $table->string('note', 500)->nullable();

            // Date effective de l'encaissement (défaut : maintenant côté
            // contrôleur) — distincte de created_at (saisie a posteriori).
            $table->timestamp('collected_at');

            $table->unsignedInteger('created_by')->nullable();

            $table->timestamps();

            // La requête du listing : « les derniers encaissements de CE
            // tenant » triés par date effective.
            $table->index(['company_id', 'collected_at'], 'billing_collections_listing_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_collections');
    }
};
