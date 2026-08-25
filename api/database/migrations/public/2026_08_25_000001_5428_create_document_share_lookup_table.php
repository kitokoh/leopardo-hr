<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5428 (perf portail partages).
 *
 * Table PUBLIQUE de lookup `share_token → company_id` pour les partages de
 * documents comptables (#5225). La table métier `accounting_document_shares`
 * est tenant-scoped (shared_tenants) : résoudre un token obligeait à itérer
 * TOUTES les entreprises actives (O(N) bascules de search_path par requête
 * publique). Ce lookup permet une résolution en O(1) requêtes publiques,
 * puis une bascule unique vers le tenant concerné.
 *
 * RGPD : aucune donnée du document ici (juste le token + la compagnie) ; les
 * lignes sont supprimées avec le partage (commande accounting:purge-expired-
 * shares, #5430) et à la suppression du partage.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_share_lookup')) {
            Schema::create('document_share_lookup', function (Blueprint $table): void {
                $table->string('share_token', 64)->primary();
                $table->uuid('company_id');
                $table->timestamp('created_at')->nullable();

                $table->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_lookup');
    }
};
