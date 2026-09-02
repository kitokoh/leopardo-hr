<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6067 (TRAVEL-415) — consentement de notification du client.
 *
 * `travel_bookings.notify_consent` : consentement explicite du client
 * voyageur à recevoir des notifications (confirmation/annulation/paiement)
 * via les canaux BC-13 configurés. Défaut FALSE → « pas d'envoi par
 * défaut » (critère d'acceptation TRAVEL-415). Aucun canal n'est sollicité
 * sans ce consentement ; le contenu reste un résumé minimal (jamais de
 * données financières dans WhatsApp).
 *
 * Migration additive et réentrante (garde schemaHasColumn, #1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_bookings') && ! schemaHasColumn('travel_bookings', 'notify_consent')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->boolean('notify_consent')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('travel_bookings') && schemaHasColumn('travel_bookings', 'notify_consent')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropColumn('notify_consent');
            });
        }
    }
};
