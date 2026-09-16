<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7475 (reliquat) — la justification opérateur d'une suppression de tenant est
 * PERSISTÉE sur la piste d'audit.
 *
 * La console validait déjà `reason` (`PlatformCompanyDeletionController`), mais
 * la valeur n'était transmise à aucun moment : ni au service, ni à la table.
 * Résultat : la seule question à laquelle une suppression de tenant doit
 * répondre après coup — « pourquoi ? » — n'avait pas de réponse en base, alors
 * que l'inventaire chiffré, lui, survivait bien à la purge.
 *
 * Migration additive et idempotente (`schemaHasColumn`) : Render rejoue des
 * migrations, et une colonne déjà présente ne doit pas faire échouer un
 * déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('tenant_deletion_audits')) {
            return;
        }

        if (schemaHasColumn('tenant_deletion_audits', 'reason')) {
            return;
        }

        Schema::table('tenant_deletion_audits', function (Blueprint $table): void {
            // Même borne que la validation d'entrée (500 caractères) : la
            // colonne ne peut pas être plus permissive que l'API.
            $table->string('reason', 500)->nullable()->after('deleted_counts');
        });

        DB::statement("COMMENT ON COLUMN tenant_deletion_audits.reason IS 'Justification opérateur de la suppression (#7475).'");
    }

    public function down(): void
    {
        if (! schemaTableExists('tenant_deletion_audits') || ! schemaHasColumn('tenant_deletion_audits', 'reason')) {
            return;
        }

        Schema::table('tenant_deletion_audits', function (Blueprint $table): void {
            $table->dropColumn('reason');
        });
    }
};
