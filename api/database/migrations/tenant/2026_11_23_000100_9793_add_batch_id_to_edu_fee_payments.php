<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9793 — reçu PDF par encaissement de lot.
 *
 * Un lot d'encaissements (une classe qui règle le même jour) n'était
 * identifiable que par le préfixe de `external_id` (`{lot}:{charge_id}`), et
 * seulement quand l'appelant fournissait un `external_id`. Deux lots sans
 * référence explicite étaient donc indistinguables : impossible de réimprimer
 * le reçu d'un dépôt de guichet.
 *
 * `batch_id` matérialise l'appartenance d'un encaissement à un lot :
 * - renseigné par `RecordEduFeeBatchPaymentsAction` (l'`external_id` du lot
 *   s'il existe, sinon un UUID généré) ;
 * - `NULL` pour un encaissement unitaire — un paiement isolé n'a pas de reçu
 *   de lot, il a son reçu de charge.
 *
 * Additive et réentrante (garde `Schema::hasColumn`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_fee_payments')) {
            return;
        }

        Schema::table('edu_fee_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('edu_fee_payments', 'batch_id')) {
                $table->string('batch_id', 100)->nullable();
            }
        });

        // Index (company_id, batch_id) : le reçu charge TOUS les encaissements
        // d'un lot d'un coup, toujours borné au tenant.
        $schema = resolveTableSchema('edu_fee_payments');
        if ($schema !== null) {
            DB::statement(
                "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = 'edu_fee_payments_company_batch_idx') "
                ."THEN CREATE INDEX edu_fee_payments_company_batch_idx ON \"{$schema}\".\"edu_fee_payments\" (company_id, batch_id); END IF; END $$"
            );
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('edu_fee_payments')) {
            return;
        }

        $schema = resolveTableSchema('edu_fee_payments');
        if ($schema !== null) {
            DB::statement('DROP INDEX IF EXISTS "'.$schema.'".edu_fee_payments_company_batch_idx');
        }

        Schema::table('edu_fee_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('edu_fee_payments', 'batch_id')) {
                $table->dropColumn('batch_id');
            }
        });
    }
};
