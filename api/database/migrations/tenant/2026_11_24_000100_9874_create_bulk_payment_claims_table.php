<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #9874 (R4) — coordinateur de paiement en masse DANS PostgreSQL.
 *
 * Avant : `BulkPaymentController::bulkPay()` prenait son verrou anti-double-
 * dispatch dans Redis (`SET NX EX`). Quand Redis tombait, l'endpoint renvoyait
 * 503 et **la paie de tous les tenants était bloquée** — alors que PostgreSQL
 * est déjà le point de vérité des `payroll_runs` et des `pay_slips`, et qu'il
 * est de toute façon dans le chemin critique du paiement.
 *
 * Après : l'exclusion mutuelle vient d'une contrainte `UNIQUE (payroll_run_id)`
 * — l'atomicité est garantie par la base, pas par un `SET NX`. L'état
 * d'avancement vit dans la même ligne : il survit à un redémarrage Redis, à
 * un failover, et à une purge de cache.
 *
 * `expires_at` remplace la logique « statut stale » : un claim dont le bail est
 * dépassé (worker mort entre le claim et le traitement) peut être repris, donc
 * un run n'est jamais bloqué définitivement.
 *
 * Migration réentrante et `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('bulk_payment_claims')) {
            return;
        }

        Schema::create('bulk_payment_claims', function (Blueprint $table): void {
            $table->id();

            // Un seul claim par run : c'est la contrainte qui porte l'atomicité
            // de l'exclusion mutuelle (#9874).
            $table->unsignedBigInteger('payroll_run_id')->unique();
            $table->uuid('company_id')->index();

            $table->string('status', 32)->default('starting');
            $table->unsignedInteger('done')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('failures')->nullable();

            $table->unsignedBigInteger('triggered_by')->nullable();

            // Bail du claim : au-delà, un autre appelant peut reprendre la main.
            $table->timestampTz('expires_at')->index();

            $table->timestampsTz();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_payment_claims');
    }
};
