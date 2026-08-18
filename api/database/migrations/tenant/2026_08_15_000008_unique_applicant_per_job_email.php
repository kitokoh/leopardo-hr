<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #3860 — `applicants` n'a aucun index unique (job_posting_id, email) : les
 * candidatures en double sont illimitées (double-clic, spam, retry, import
 * concurrent). On dédoublonne d'abord (garde la plus ancienne candidature)
 * puis on pose un index unique (job_posting_id, email) — la table vit dans le
 * schéma tenant, donc job_posting_id identifie déjà le poste de l'entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('applicants') || ! schemaHasColumn('applicants', 'email')) {
            return;
        }

        // Dédoublonnage préalable : ne garder que la plus ancienne candidature
        // par (job_posting_id, email) — les doublons historiques sont supprimés
        // avant la contrainte (sinon CREATE UNIQUE INDEX échoue).
        DB::statement('
            DELETE FROM applicants a
            USING applicants b
            WHERE a.job_posting_id = b.job_posting_id
              AND a.email = b.email
              AND a.id > b.id
        ');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS applicants_job_posting_email_unique
            ON applicants (job_posting_id, email)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS applicants_job_posting_email_unique');
    }
};
