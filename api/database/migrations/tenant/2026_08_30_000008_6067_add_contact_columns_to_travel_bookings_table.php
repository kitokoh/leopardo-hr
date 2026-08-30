<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6067 (TRAVEL-415) — Coordonnées de contact voyageur sur la réservation.
 *
 * PII : email/téléphone du contact à notifier, capturés au guichet ou à la
 * boutique avec un consentement explicite (`notify_consent`). Colonnes
 * optionnelles — aucune notification sans canal configuré ET consentement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaHasColumn('travel_bookings', 'contact_email')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->string('contact_email', 255)->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->boolean('notify_consent')->default(false);
                $table->timestamp('consent_recorded_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropColumn([
                'contact_email',
                'contact_phone',
                'notify_consent',
                'consent_recorded_at',
            ]);
        });
    }
};
