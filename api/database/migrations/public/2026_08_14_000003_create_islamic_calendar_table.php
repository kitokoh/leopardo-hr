<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1812 — Calendrier islamique dynamique (complémentaire à
 * `public_holidays`).
 *
 * Les fêtes islamiques sont mobiles (calendrier hégirien, −10/11 jours par an)
 * et concernent tous les pays CEMAC/CEDEAO + DZ/MA/TN. Une entrée par type de
 * fête et par année ; un admin plateforme saisit/confirme les dates
 * officielles sans changement de code.
 *
 * - holiday_key  : 'eid_al_fitr' | 'eid_al_adha' | 'mawlid' | 'tahmarit' | 'muharram'
 * - duration_days: certains pays fêtent l'Aïd 2 jours (CM, CI), d'autres 1
 * - source       : 'manual' | 'api' | 'computed' (approximatif par défaut)
 * - confirmed    : un admin peut marquer la date « officielle » une fois connue
 *
 * Table publique (schéma `public`) : les dates islamiques sont nationales.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Render peut rejouer les migrations (entrypoint) : idempotence.
        if (Schema::hasTable('islamic_calendar')) {
            return;
        }

        Schema::create('islamic_calendar', function (Blueprint $table): void {
            $table->id();
            $table->string('holiday_key', 30);
            $table->unsignedSmallInteger('year');
            $table->date('gregorian_date');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->string('source', 30)->default('manual'); // 'manual' | 'api' | 'computed'
            $table->boolean('confirmed')->default(false);
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();

            $table->unique(['holiday_key', 'year']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('islamic_calendar');
    }
};
