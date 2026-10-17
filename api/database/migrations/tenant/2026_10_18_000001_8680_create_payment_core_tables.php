<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-38 PAIEMENTS (#8680) — socle domaine : wallets, transactions, ledger.
 *
 * Trois tables, un seul invariant structurant : **le solde d'un wallet n'est
 * jamais stocke**, il est derive par agregation du ledger
 * (`SUM(credits) - SUM(debits)`). Toute colonne de solde serait une seconde
 * source de verite et deriverait tot ou tard du journal.
 *
 * Nommage : `payment_ledger_entries` et non `ledger_entries` — cette derniere
 * existe deja et appartient au module Payroll (PA2-PAY-007, journal financier
 * employe). Deux journaux differents, deux tables differentes.
 *
 * Montants : `bigint` en plus petite unite de la devise (centimes). Jamais de
 * `decimal`/`float` — l'arithmetique flottante sur de l'argent est un bug
 * latent, pas un choix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_wallets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // platform | tenant | client — le proprietaire logique du solde.
            $table->string('owner_type', 20);
            $table->string('owner_id', 64);

            $table->char('currency', 3);

            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            // Unicite metier : un seul wallet par (proprietaire, devise).
            $table->unique(['company_id', 'owner_type', 'owner_id', 'currency'], 'payment_wallets_owner_currency_unique');
            $table->index(['company_id', 'currency']);
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            // cash_in | cash_out | internal_transfer | refund
            $table->string('type', 30);
            // pending | processing | succeeded | failed | cancelled
            $table->string('status', 20)->default('pending');

            // NULL pour un transfert strictement interne (aucun rail externe).
            $table->unsignedBigInteger('provider_instance_id')->nullable();

            $table->unsignedBigInteger('wallet_id')->nullable();
            $table->unsignedBigInteger('counterparty_wallet_id')->nullable();

            // Plus petite unite de la devise (entier).
            $table->bigInteger('amount');
            $table->bigInteger('fee_amount')->default(0);
            $table->char('currency', 3);

            // Idempotence : la cle est unique PAR TENANT, en base.
            $table->string('idempotency_key', 120);
            $table->string('external_reference', 190)->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['company_id', 'idempotency_key'], 'payment_transactions_idempotency_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'type']);
            $table->index('provider_instance_id');
            $table->index('external_reference');
        });

        Schema::create('payment_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->unsignedBigInteger('wallet_id');

            // debit | credit
            $table->string('direction', 6);

            $table->bigInteger('amount');
            $table->char('currency', 3);

            $table->unsignedBigInteger('transaction_id')->nullable();

            // Les deux ecritures d'un meme mouvement partagent ce groupe :
            // c'est lui qui porte l'invariant de partie double.
            $table->uuid('transfer_group_id');

            $table->string('reference', 190)->nullable();
            $table->jsonb('metadata')->nullable();

            // Immuable : created_at seulement, jamais d'updated_at.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'wallet_id', 'created_at']);
            $table->index('transfer_group_id');
            $table->index('transaction_id');
            $table->index(['company_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_ledger_entries');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_wallets');
    }
};
