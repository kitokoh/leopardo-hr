<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6098 (TRAVEL-808) — Remboursements partiels : traçabilité par passager.
 *
 * Une réservation multi-passagers peut être remboursée partiellement (un
 * ou plusieurs passagers). Chaque passager remboursé conserve sa trace :
 * montant effectivement remboursé (pénalités déduites), date et motif —
 * idempotence : un passager déjà remboursé n'est jamais remboursé deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaHasColumn('travel_passengers', 'refunded_at')) {
            Schema::table('travel_passengers', function (Blueprint $table): void {
                $table->timestamp('refunded_at')->nullable();
                $table->unsignedBigInteger('refunded_amount_minor')->nullable();
                $table->string('refund_reason', 500)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_passengers', function (Blueprint $table): void {
            $table->dropColumn(['refunded_at', 'refunded_amount_minor', 'refund_reason']);
        });
    }
};
