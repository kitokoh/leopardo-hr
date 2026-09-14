<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * EduManager — réconciliation (suite) des deux générations de schéma.
 *
 * Complète `2026_09_14_000002_5819_repair_edu_schema_drift` : après l'ajout des
 * colonnes manquantes, deux divergences de CONTRAINTE empêchaient encore le
 * module de fonctionner (constat 2026-09-14, audit du parcours client
 * « propriétaire d'école »).
 *
 * 1. `edu_admissions_status_check` — la génération réellement appliquée borne
 *    `status` à `pending|admitted|rejected|enrolled|cancelled`, alors que le
 *    modèle (`EduAdmission::STATUSES`) et l'API utilisent
 *    `new|document_pending|review|accepted|waitlisted|rejected|cancelled|converted`.
 *    Créer une inscription avec `review` (le statut par défaut du service !)
 *    échouait en **SQLSTATE 23514** → 11 tests rouges et un parcours
 *    « inscriptions » inutilisable. La contrainte est remplacée par l'**UNION**
 *    des deux vocabulaires : tout ce qui était accepté le reste, tout ce que le
 *    code écrit est accepté. Aucune donnée existante n'est invalidée.
 *    `NOT VALID` : les lignes déjà présentes ne sont pas revalidées (migration
 *    non bloquante sur une base de production volumineuse) — les nouvelles
 *    écritures, elles, sont bien contrôlées.
 *
 * 3. `edu_assessments.type` — NOT NULL dans la génération appliquée, alors que la
 *    couche API/tests écrit `assessment_type` (colonne ajoutée par la migration
 *    précédente). La colonne historique est rendue nullable : le code qui écrit
 *    encore `type` continue de fonctionner, celui qui écrit `assessment_type`
 *    aussi.
 *
 * Additive et idempotente (contrainte recréée après DROP IF EXISTS, DROP NOT NULL
 * conditionné à `is_nullable = 'NO'`) — rejouable sans effet sur une base saine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_admissions')) {
            return;
        }

        $schema = resolveTableSchema('edu_admissions');

        if ($schema === null) {
            return;
        }

        $qualified = '"'.$schema.'"."edu_admissions"';

        // 1) Union des vocabulaires de statut (génération historique ∪ modèle).
        DB::statement("ALTER TABLE {$qualified} DROP CONSTRAINT IF EXISTS edu_admissions_status_check");

        DB::statement(
            "ALTER TABLE {$qualified} ADD CONSTRAINT edu_admissions_status_check "
            .'CHECK (status IN ('
            ."'pending','admitted','rejected','enrolled','cancelled',"
            ."'new','document_pending','review','accepted','waitlisted','converted'"
            .')) NOT VALID'
        );

        // 2) `edu_students.metadata` : la génération appliquée l'a créé en
        // `jsonb` alors que le modèle le caste `encrypted:array` (blob JSON
        // chiffré au repos, cf. docblock de la migration d'origine) : la chaîne
        // chiffrée était refusée par PostgreSQL
        // (`SQLSTATE 22P02 invalid input syntax for type json`) → création et
        // conversion d'élève en échec. La colonne repasse en `text` (type
        // déclaré par la révision la plus récente), les valeurs existantes
        // étant converties telles quelles.
        if (schemaTableExists('edu_students') && schemaHasColumn('edu_students', 'metadata')) {
            $schemaStudents = resolveTableSchema('edu_students');

            if ($schemaStudents !== null) {
                $row = DB::selectOne(
                    'SELECT data_type FROM information_schema.columns
                      WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                    [$schemaStudents, 'edu_students', 'metadata']
                );

                if ($row !== null && (string) $row->data_type === 'jsonb') {
                    DB::statement(
                        'ALTER TABLE "'.$schemaStudents.'"."edu_students" '
                        .'ALTER COLUMN "metadata" TYPE text USING "metadata"::text'
                    );
                }
            }
        }

        // 3) `edu_assessments.type` nullable (l'API écrit `assessment_type`).
        if (schemaTableExists('edu_assessments') && schemaHasColumn('edu_assessments', 'type')) {
            $schemaAssessments = resolveTableSchema('edu_assessments');

            if ($schemaAssessments !== null) {
                $row = DB::selectOne(
                    'SELECT is_nullable FROM information_schema.columns
                      WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                    [$schemaAssessments, 'edu_assessments', 'type']
                );

                if ($row !== null && (string) $row->is_nullable === 'NO') {
                    DB::statement('ALTER TABLE "'.$schemaAssessments.'"."edu_assessments" ALTER COLUMN "type" DROP NOT NULL');
                }
            }
        }
    }

    public function down(): void
    {
        // Non destructif : aucune contrainte n'est ré-appliquée (des lignes
        // écrites entre-temps avec le vocabulaire du modèle seraient
        // invalidées).
    }
};
