<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leopardo Library — progression de lecture persistée côté serveur (#9242).
 *
 * Jusqu'ici la progression vivait uniquement en `localStorage`
 * (`front/web/src/modules/library/lib/progress.ts`) : elle était perdue au
 * changement d'appareil ou au nettoyage du navigateur. Cette table permet à un
 * employé authentifié de retrouver sa position sur n'importe quel appareil.
 *
 * Contrat :
 * - **tenant-scoped** (`company_id`) : la progression d'un employé n'est jamais
 *   visible d'un autre tenant ;
 * - **une ligne par (employé, ressource)** : `unique(company_id, employee_id,
 *   resource_slug)` — la ressource est référencée par SLUG, pas par clé
 *   étrangère, car le catalogue public Leopardo (`company_id` NULL) n'est pas
 *   dans le périmètre tenant et une ressource peut être dépubliée sans perdre
 *   la position de lecture ;
 * - **page bornée** : `page >= 1` et `page <= total_pages` quand le total est
 *   connu (contrainte CHECK) — une position aberrante ne peut pas être écrite ;
 * - **anonymes** : aucune ligne n'est créée sans authentification ; le
 *   `localStorage` reste la source de vérité pour les visiteurs anonymes.
 *
 * Idempotente + `down()` complet (conventions migrations tenant §2.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('library_reading_progress')) {
            return;
        }

        Schema::create('library_reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();

            // Ressource référencée par slug : le catalogue public n'est pas
            // tenant-scoped, une FK casserait à la dépublication.
            $table->string('resource_slug', 210);

            $table->unsignedInteger('page')->default(1);
            $table->unsignedInteger('total_pages')->default(0);

            $table->timestamps();

            $table->unique(
                ['company_id', 'employee_id', 'resource_slug'],
                'library_reading_progress_unique'
            );
            $table->index(
                ['company_id', 'employee_id', 'updated_at'],
                'library_reading_progress_recent_idx'
            );

            $table->foreign('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
        });

        // Une position de lecture aberrante ne doit jamais être persistée.
        DB::statement(
            'ALTER TABLE library_reading_progress '
            .'ADD CONSTRAINT library_reading_progress_page_check '
            .'CHECK (page >= 1 AND (total_pages = 0 OR page <= total_pages))'
        );

        DB::statement(
            "COMMENT ON TABLE library_reading_progress IS 'Progression de lecture par employe et ressource (page courante, total de pages) - tenant-scoped, une ligne par couple.';"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('library_reading_progress');
    }
};
