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
        // F-17 (#1595/#1933) : garde d'existence résolue via le search_path
        // courant (`current_schemas(false)`), pas `Schema::hasTable()` nu qui
        // ne voit que `current_schema()` — le search_path diffère entre CI et
        // local, un garde au nom nu peut répondre faux à tort et sauter
        // silencieusement la migration. Création QUALIFIÉE dans `public` :
        // table partagée entre tous les tenants, schéma indépendant du
        // search_path.
        if (schemaTableExists('islamic_calendar')) {
            return;
        }

        Schema::create('public.islamic_calendar', function (Blueprint $table): void {
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
        Schema::dropIfExists('public.islamic_calendar');
    }
};
