<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7936 — Taux de prelevement a la source (PAS) personnalise par employe.
 *
 * La DSN V2 exige, pour chaque versement, le taux de PAS, son *type* et son
 * identifiant DGFiP (S21.G00.50.006/.007/.008). Sans stockage structure, le
 * generateur ne pouvait declarer que le taux neutre — c'est-a-dire prelever
 * et declarer un montant fiscal faux des que la DGFiP a transmis un taux
 * personnalise a l'employeur.
 *
 * Pourquoi des colonnes et non `extra_data` (JSONB) : le taux est une donnee
 * fiscale requise par une obligation declarative, interrogee en masse a
 * chaque campagne DSN. Un blob JSON la rend invisible a PHPStan, non
 * contrainte (un taux a 250 % passerait) et impossible a indexer.
 *
 * Colonnes nullables : un employe sans taux transmis reste valide, la DSN
 * declarant alors le taux neutre.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Les deux etapes sont gardees SEPAREMENT. Un `return` global des que
        // la colonne existe rendrait la contrainte inatteignable : une
        // migration interrompue entre les deux (ou rejouee apres un `up()`
        // partiel) laisserait la table SANS le CHECK, en silence — exactement
        // la classe de defaut des migrations no-op (#8862). La contrainte est
        // donc posee de facon independante et idempotente.
        if (! Schema::hasColumn('employees', 'pas_rate')) {
            Schema::table('employees', function (Blueprint $table): void {
                // Taux en pourcentage (12.50 = 12,50 %). La borne est appliquee
                // en base pour qu'aucune ecriture applicative ne puisse declarer
                // un taux hors [0 ; 100] a l'administration.
                $table->decimal('pas_rate', 5, 2)->nullable();
                // Identifiant de taux DGFiP (obligatoire si type 01).
                $table->string('pas_identifier', 64)->nullable();

                $table->index('pas_identifier');
            });
        }

        if (! $this->hasPasRateRangeConstraint()) {
            DB::statement(
                'ALTER TABLE employees ADD CONSTRAINT employees_pas_rate_range '
                .'CHECK (pas_rate IS NULL OR (pas_rate >= 0 AND pas_rate <= 100))'
            );
        }
    }

    /**
     * La contrainte existe-t-elle deja ? Interroge `pg_constraint` (et non un
     * `try/catch` sur l'ALTER) : c'est la seule source de verite, et cela
     * evite d'avaler une erreur de nature differente.
     */
    private function hasPasRateRangeConstraint(): bool
    {
        $exists = DB::selectOne(
            'SELECT 1 AS present FROM pg_constraint c '
            .'JOIN pg_class t ON t.oid = c.conrelid '
            .'JOIN pg_namespace n ON n.oid = t.relnamespace '
            ."WHERE c.conname = 'employees_pas_rate_range' AND t.relname = 'employees' "
            .'AND n.nspname = current_schema() LIMIT 1'
        );

        return $exists !== null;
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'pas_rate')) {
            return;
        }

        DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_pas_rate_range');

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['pas_identifier']);
            $table->dropColumn(['pas_rate', 'pas_identifier']);
        });
    }
};
