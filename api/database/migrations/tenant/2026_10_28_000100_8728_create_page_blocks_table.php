<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8728 (BC PAGES) — blocs de contenu d'une Page.
 *
 * ## Pourquoi une table et pas une colonne JSONB sur `pages`
 *
 * Le contrat de blocs (#8724) impose un `position` explicite et une
 * `schema_version` PAR BLOC : deux blocs d'une même Page peuvent coexister en
 * v1 et v2 pendant une migration de contrat. Une colonne JSONB unique
 * obligerait à réécrire toute la Page pour changer un bloc, et rendrait
 * impossible un `UPDATE` ciblé sur un seul bloc — donc toute édition
 * concurrente perdrait les modifications de l'autre éditeur.
 *
 * ## Invariants portés par le schéma
 *
 * - **`block_uid` unique par Page** : l'identifiant du bloc est fourni par
 *   l'éditeur (`b_01J…`) et sert de clé de réconciliation côté client. Deux
 *   blocs de même uid dans une Page rendraient le rendu non déterministe.
 * - **`position` unique par Page** : deux blocs à la même position n'ont pas
 *   d'ordre défini ; l'ordre est une donnée, pas un effet de bord du tri SQL.
 * - **`type` non vide** : un bloc sans type ne peut être ni validé ni rendu.
 *
 * Additive et idempotente. `down()` ne détruit que ce que `up()` a créé
 * (règle AGENTS.md #8207).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('page_blocks')) {
            return;
        }

        Schema::create('page_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedBigInteger('page_id');

            // Identifiant stable fourni par l'éditeur (clé de réconciliation).
            $table->string('block_uid', 64);

            // Type qualifié `<bc>.<nom>` (contrat #8724).
            $table->string('type', 80);

            // Version du contrat de CE bloc (pas de la Page).
            $table->unsignedSmallInteger('schema_version')->default(1);

            // Ordre explicite, normalisé 0..n-1 à l'écriture.
            $table->unsignedInteger('position')->default(0);

            // Contenu dans la langue par défaut + traductions par locale.
            $table->jsonb('content');
            $table->jsonb('locales')->nullable();

            $table->timestamps();

            $table->unique(['page_id', 'block_uid'], 'page_blocks_page_uid_unique');
            $table->unique(['page_id', 'position'], 'page_blocks_page_position_unique');
            $table->index('company_id', 'page_blocks_company_index');
        });

        DB::statement(
            "COMMENT ON TABLE page_blocks IS 'Blocs de contenu d une Page (BC PAGES, #8728) - ordre explicite, version de contrat par bloc.'"
        );

        DB::statement('ALTER TABLE page_blocks DROP CONSTRAINT IF EXISTS page_blocks_type_not_blank');
        DB::statement(
            'ALTER TABLE page_blocks ADD CONSTRAINT page_blocks_type_not_blank '
            ."CHECK (btrim(type) <> '')"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE page_blocks DROP CONSTRAINT IF EXISTS page_blocks_type_not_blank');

        Schema::dropIfExists('page_blocks');
    }
};
