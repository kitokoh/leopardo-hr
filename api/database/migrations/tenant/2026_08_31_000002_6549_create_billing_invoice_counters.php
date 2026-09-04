<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6549 — numérotation atomique des factures mensuelles.
 *
 * `billing:generate-invoices` numérotait via `count()+1` : deux runs
 * concurrents collisionnent, et une facture supprimée fausse la numérotation.
 *
 * Compteur incrémental atomique (pattern upsert ON CONFLICT, même famille que
 * `accounting_number_counters` #5223) : `nextSequence()` fait
 * INSERT ... ON CONFLICT DO UPDATE ... RETURNING last_number — deux appels
 * concurrents obtiennent toujours des numéros distincts.
 *
 * Le compteur est GLOBAL par année (et non par entreprise) : la colonne
 * `invoices.number` porte une contrainte UNIQUE globale (migration billing
 * d'origine) — un numéro LEO-AAAA-NNNN par entreprise créerait des
 * collisions inter-entreprises.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('billing_invoice_number_counters')) {
            return;
        }

        Schema::create('billing_invoice_number_counters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoice_number_counters');
    }
};
