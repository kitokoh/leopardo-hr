<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6100 (TRAVEL-810) — Point de vente tablette : sessions de caisse.
 *
 * Une caisse par tenant : ouverture (solde initial), clôture avec
 * ATTENDU calculé serveur (solde initial + paiements cash confirmés depuis
 * l'ouverture) vs RÉEL saisi → écart. Critère d'acceptation : la clôture
 * est cohérente avec les paiements cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_cash_sessions')) {
            Schema::create('travel_cash_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('opened_by_user_id');
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('opening_balance_minor')->default(0);
                $table->unsignedBigInteger('expected_balance_minor')->nullable();
                $table->unsignedBigInteger('actual_balance_minor')->nullable();
                $table->bigInteger('difference_minor')->nullable();
                $table->string('status', 20)->default('open'); // open|closed
                $table->timestamps();

                $table->index(['company_id', 'status'], 'travel_cash_sessions_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_cash_sessions');
    }
};
