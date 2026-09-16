<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7475 — Piste d'audit des suppressions de tenant (BC-01 PLATFORM / BC-02 TENANT).
 *
 * Table **plateforme** (schéma `public`) et non tenant : c'est précisément le
 * cas d'usage. La purge efface les données du tenant, y compris son schéma
 * `shared_tenants` (`audit_logs` compris) ; la preuve de l'opération doit
 * survivre à la purge, sinon on ne peut plus répondre à « qui a supprimé quoi,
 * quand, et avec quel volume ».
 *
 * L'`inventory` conserve le volume détruit **au moment** de l'opération
 * (employés, bulletins, pointages, documents…) : la ligne reste la seule trace
 * chiffrée après la disparition des données.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('tenant_deletion_audits')) {
            return;
        }

        Schema::create('tenant_deletion_audits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->string('company_name', 200);
            $table->string('company_slug', 120)->nullable();

            // 'purge'      : effacement complet (RGPD art. 17).
            // 'anonymize'  : effacement sauf paie conservée, identités hachées.
            $table->string('mode', 20);

            // 'completed' | 'refused' | 'failed'
            $table->string('status', 20);

            $table->jsonb('inventory')->default('{}');
            $table->jsonb('deleted_counts')->default('{}');

            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_email', 255)->nullable();
            $table->string('request_id', 80)->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index(['company_id', 'created_at'], 'tenant_deletion_audits_company_created_idx');
            $table->index('status', 'tenant_deletion_audits_status_idx');
        });

        // Hors de la closure : `Schema::create()` n'exécute le DDL qu'après le
        // retour de la closure — un COMMENT placé dedans viserait une table
        // inexistante (constaté au premier essai, « relation does not exist »).
        DB::statement("COMMENT ON TABLE tenant_deletion_audits IS 'Piste d''audit des suppressions de tenant — survit à la purge (#7475).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_deletion_audits');
    }
};
