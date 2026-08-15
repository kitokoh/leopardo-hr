<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #3811 — commissions.payment_id : index unique manquant.
 *
 * `CommissionService::recordCommissionForPayment` repose sur un check-then-create
 * (`exists()` puis `create()`) : sans contrainte unique sur `payment_id`, une
 * course entre deux requêtes concurrentes crée deux commissions pour le même
 * paiement (500 latents, double paiement partenaire). Une commission par
 * paiement est la règle d'idempotence du service → index unique.
 *
 * Motif aligné sur `2026_08_14_000004_add_unique_public_holidays.php` :
 * dédoublonnage préalable (ligne la plus ancienne conservée) et garde
 * d'idempotence `information_schema` (issue #2326 — pas de « IF NOT EXISTS »
 * natif pour `Schema::table()->unique()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('commissions');

        if ($schema === null) {
            return;
        }

        $qualified = $schema.'.commissions';

        // Nettoyage préalable des doublons : on garde la ligne la plus ancienne.
        $duplicateIds = DB::table("{$qualified} as a")
            ->join("{$qualified} as b", 'a.payment_id', '=', 'b.payment_id')
            ->whereColumn('a.id', '>', 'b.id')
            ->distinct()
            ->pluck('a.id');

        foreach ($duplicateIds->chunk(500) as $chunk) {
            DB::table($qualified)->whereIn('id', $chunk)->delete();
        }

        $constraintExists = (bool) DB::table('information_schema.table_constraints')
            ->where('constraint_name', 'commissions_payment_id_unique')
            ->where('table_name', 'commissions')
            ->whereIn('constraint_type', ['UNIQUE', 'PRIMARY KEY'])
            ->exists();

        if (! $constraintExists) {
            Schema::table($qualified, function (Blueprint $table): void {
                $table->unique('payment_id', 'commissions_payment_id_unique');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('commissions');

        if ($schema === null) {
            return;
        }

        Schema::table($schema.'.commissions', function (Blueprint $table): void {
            $table->dropUnique('commissions_payment_id_unique');
        });
    }
};
