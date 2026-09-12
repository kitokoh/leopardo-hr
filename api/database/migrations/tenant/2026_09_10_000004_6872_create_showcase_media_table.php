<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6872 (BC-27 SHOWCASE, V-MEDIA) - Medias de vitrine : table
 * `showcase_media`.
 *
 * - `kind` : `logo` (identite visuelle du tenant) ou `section` (image
 *   rattachee a une section publique) ;
 * - lien vers la vitrine et la section par identifiants STABLES
 *   (`showcase_id`, `section_id`) — jamais de chemin absolu cote client ;
 * - `disk` + `path` = emplacement de stockage interne (disk existant
 *   `local`, hors webroot) ; le fichier est servi par le controleur public
 *   (vitrine publiee uniquement), jamais expose statiquement ;
 *   ces colonnes ne sortent JAMAIS du DTO prive (test de non-fuite) ;
 * - `original_name` = nom d'origine sanitise, `file_name` = nom stocke
 *   genere (aleatoire + extension whitelistee) ;
 * - `showcase_id` / `section_id` references internes SANS FK (conventions
 *   migrations tenant §2.6) ; l'isolation reste portee par `company_id`
 *   (BelongsToCompany).
 *
 * Idempotente + down() complet (conventions #1613 / Render pre-vol #6916).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('showcase_media')) {
            Schema::create('showcase_media', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('showcase_id');
                $table->unsignedBigInteger('section_id')->nullable();

                $table->string('kind', 20);
                $table->string('original_name', 255);
                $table->string('file_name', 255);
                $table->string('mime_type', 120);
                $table->string('extension', 12);
                $table->unsignedBigInteger('size');
                $table->string('disk', 40)->default('local');
                $table->string('path', 512);

                $table->timestamps();

                $table->index('company_id', 'showcase_media_company_index');
                $table->index(['showcase_id', 'kind'], 'showcase_media_showcase_kind_index');
                $table->index('section_id', 'showcase_media_section_index');
            });

            DB::statement("COMMENT ON TABLE showcase_media IS 'Medias de la vitrine publique du tenant - logo et images de sections, stockes hors webroot et servis par la route publique (BC-27/#6872).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_media');
    }
};
