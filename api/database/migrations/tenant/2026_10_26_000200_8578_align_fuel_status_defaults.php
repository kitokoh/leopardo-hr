<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #8578 — aligner le défaut SQL de `fuel_incidents.status` sur l'état de
 * naissance canonique `reported`.
 *
 * ## Pourquoi une migration et pas seulement le modèle
 *
 * Le défaut de colonne était `'open'` alors que le modèle et le service
 * produisaient `reported`. Trois défauts pour un même fait — colonne, modèle,
 * graphe — et c'est exactement cette divergence qui a produit le blocage de
 * workflow de l'issue : un incident né `open` n'avait aucune transition
 * sortante correspondante côté service.
 *
 * Aligner uniquement le code PHP laisserait le défaut SQL en place, et un
 * INSERT hors modèle (import, script d'exploitation, restauration partielle)
 * ressusciterait l'état supprimé. La base doit porter la même vérité que le
 * domaine.
 *
 * ## Les lignes existantes ne sont pas réécrites
 *
 * On ne réécrit PAS les `open` déjà en base : ce seraient des incidents
 * historiques dont la transition sortante est légitime, et un backfill
 * changerait leur sens rétroactivement. Un incident`open` reste lisible et
 * transitionnable — c'est une donnée du passé, pas une erreur à corriger.
 * Seul le DÉFAUT change, donc seules les nouvelles lignes sont concernées.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Aucun garde de schéma ici : ces tables vivent dans le schéma tenant
        // courant, comme toutes les migrations de ce répertoire. Le défaut
        // s'applique par ALTER, idempotent par nature.
        DB::statement("ALTER TABLE fuel_incidents ALTER COLUMN status SET DEFAULT 'reported'");

        // Le défaut des tâches est déjà `todo` et reste la seule valeur
        // canonique (#8578 D4) — on le réaffirme au cas où une restauration
        // partielle aurait ramené l'ancien défaut.
        DB::statement("ALTER TABLE fuel_maintenance_tasks ALTER COLUMN status SET DEFAULT 'todo'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE fuel_incidents ALTER COLUMN status SET DEFAULT 'open'");
        DB::statement("ALTER TABLE fuel_maintenance_tasks ALTER COLUMN status SET DEFAULT 'todo'");
    }
};
