<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6213 (RESTO-608) - RestaurantManager : rappel de reservation J-1.
 *
 * `reminder_sent_at` (nullable) rend le job de rappel idempotent : une
 * reservation confirmee n'est notifiee qu'une seule fois (« pas de double
 * rappel », critere d'acceptation RESTO-608).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_reservations')) {
            return;
        }

        Schema::table('restaurant_reservations', function (Blueprint $table): void {
            $table->timestamp('reminder_sent_at')->nullable()->after('deposit_minor');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_reservations')) {
            return;
        }

        Schema::table('restaurant_reservations', function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
