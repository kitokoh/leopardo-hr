<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ATS — texte extrait du CV.
 *
 * Le score de correspondance ne lisait que la lettre de motivation et les
 * notes internes : un candidat sans lettre obtenait 0 % même avec un CV
 * parfaitement aligné sur l'offre. Cette colonne porte le texte réellement
 * extrait du document déposé, pour que le score compare ce que le candidat a
 * écrit à ce que l'offre demande.
 *
 * Colonne ADDITIVE (`Schema::table`), jamais un second `Schema::create` : la
 * garde CI `check-migration-basename-collisions.sh` (#7452) refuse deux
 * migrations qui créent la même table, et un `create` rejoué sur une base
 * existante échouerait.
 *
 * `nullable` : un CV scanné (image) ou chiffré ne rend aucun texte. `null`
 * signifie « non extrait », ce qui n'est pas « vide » — le score ATS doit
 * pouvoir distinguer les deux.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! schemaTableExists('applicants')) {
            return;
        }

        Schema::table('applicants', function (Blueprint $table): void {
            if (! schemaHasColumn('applicants', 'resume_text')) {
                $table->text('resume_text')->nullable()->after('resume_path');
            }

            if (! schemaHasColumn('applicants', 'resume_text_extracted_at')) {
                $table->timestampTz('resume_text_extracted_at')->nullable()->after('resume_text');
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('applicants')) {
            return;
        }

        Schema::table('applicants', function (Blueprint $table): void {
            if (schemaHasColumn('applicants', 'resume_text_extracted_at')) {
                $table->dropColumn('resume_text_extracted_at');
            }

            if (schemaHasColumn('applicants', 'resume_text')) {
                $table->dropColumn('resume_text');
            }
        });
    }
};
