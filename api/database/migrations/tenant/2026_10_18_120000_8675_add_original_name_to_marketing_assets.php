<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #8675 — Nom d'origine des ressources de la médiathèque (Marketing OS, §C/§F).
 *
 * `MarketingAssetStorage::sanitizeOriginalName()` calcule depuis toujours un
 * nom d'origine propre (séparateurs de chemin neutralisés, longueur bornée),
 * mais `storeAsset()` ne le persistait pas : la base ne gardait que `disk`,
 * `path`, `mime_type`, `extension` et `size`. Deux conséquences concrètes :
 *
 *   - un téléchargement ne pouvait pas proposer de nom lisible à l'opérateur
 *     (le stockage utilise un nom aléatoire de 32 caractères hexadécimaux) ;
 *   - l'écran de médiathèque ne pouvait pas afficher « affiche-lancement.jpg »
 *     mais seulement un identifiant opaque, alors que le brief §C demande
 *     « nom et description » et « emplacement du fichier ».
 *
 * La colonne est NULLABLE et aucun backfill n'est fait : les ressources déjà
 * téléversées n'ont pas cette information et restent valides. Le code de
 * lecture retombe alors sur un nom dérivé du titre (`Str::slug(name)` + extension),
 * donc un fichier reste téléchargeable même sans nom d'origine — dégradation
 * du CONFORT de lecture, jamais de la fonction.
 *
 * GARDES RESPECTÉES :
 *  - additive : une colonne nullable, aucune donnée touchée, aucun backfill ;
 *  - idempotente : `schemaHasColumn()` évite un second `ADD COLUMN` au rejeu ;
 *  - « une table, une migration » (#7452) : aucune table créée ici ;
 *  - convention #1613 / CONVENTIONS §2.6 : garde par `schemaTableExists()` /
 *    `schemaHasColumn()` (helpers globaux) et non par `Schema::hasTable()` ;
 *  - `down()` ne retire QUE la colonne ajoutée par ce même fichier (#8207).
 *
 * COÛT : une colonne VARCHAR(180) nullable sur une table de médiathèque
 * (milliers de lignes par tenant). Aucun index : la colonne n'est pas un
 * critère de filtre, seulement une donnée de présentation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('marketing_assets')) {
            return;
        }

        if (schemaHasColumn('marketing_assets', 'original_name')) {
            return;
        }

        Schema::table('marketing_assets', function (Blueprint $table): void {
            $table->string('original_name', 180)->nullable();
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('marketing_assets')) {
            return;
        }

        if (! schemaHasColumn('marketing_assets', 'original_name')) {
            return;
        }

        Schema::table('marketing_assets', function (Blueprint $table): void {
            $table->dropColumn('original_name');
        });
    }
};
