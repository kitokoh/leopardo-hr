<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8450 — Rotations multi-sites : rattacher un planning à un établissement.
 *
 * ## Décision (fondateur / PM)
 *
 * Les rotations existaient pour Restaurant (#8430) et FuelStation (#8436) mais
 * le socle `schedules` (module Planning) n'avait AUCUN axe d'établissement :
 * un tenant multi-sites ne pouvait pas dire « cette rotation est celle de la
 * station A » et la vue gérant ne pouvait pas filtrer par site. Plutôt que de
 * dupliquer un modèle de rotation par verticale, on ajoute l'axe manquant au
 * socle existant — c'est exactement ce que demande l'issue (« extraire le
 * socle Rotations en capacité transversale »).
 *
 * ## Pourquoi nullable et sans backfill
 *
 * `site_id` NULL signifie « rotation valable pour tout le tenant » : c'est le
 * comportement historique, conservé à l'identique. Aucun backfill n'est
 * possible ni souhaitable — deviner l'établissement d'une rotation existante
 * serait un choix silencieux (même règle que #8727). Les tenants mono-site
 * n'ont rien à faire.
 *
 * ## Pourquoi `nullOnDelete` et non `cascadeOnDelete`
 *
 * Supprimer un établissement ne doit pas effacer l'historique de planning
 * (les pointages y font référence). La rotation redevient simplement
 * « tenant-wide ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('schedules') || schemaHasColumn('schedules', 'site_id')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            $table->unsignedInteger('site_id')->nullable()->after('company_id');
            $table->index(['company_id', 'site_id'], 'schedules_company_site_index');
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
        });

        DB::statement(
            "COMMENT ON COLUMN schedules.site_id IS "
            ."'#8450 — Établissement de la rotation. NULL = rotation valable pour tout le tenant (comportement historique).'"
        );
    }

    public function down(): void
    {
        // Un `down()` ne détruit que ce que le `up()` du MÊME fichier a défini
        // (leçon #8207) : la colonne, l'index et la contrainte posés ci-dessus.
        if (! schemaTableExists('schedules') || ! schemaHasColumn('schedules', 'site_id')) {
            return;
        }

        Schema::table('schedules', function (Blueprint $table): void {
            $table->dropForeign(['site_id']);
            $table->dropIndex('schedules_company_site_index');
            $table->dropColumn('site_id');
        });
    }
};
