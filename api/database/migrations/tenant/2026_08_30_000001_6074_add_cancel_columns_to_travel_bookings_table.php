<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6074 (TRAVEL-504) — Colonnes d'annulation sur travel_bookings.
 *
 * `cancelled_at` + `cancel_reason` rendent le motif d'annulation requêtable
 * (rapport des annulations par motif, spec §7.6) — jusqu'ici le motif
 * n'était porté que par l'événement outbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable()->after('expires_at');
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropColumn(['cancelled_at', 'cancel_reason']);
        });
    }
};
