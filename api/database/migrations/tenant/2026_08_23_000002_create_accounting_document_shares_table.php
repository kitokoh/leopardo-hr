<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5225 (envoi email + portail client sécurisé).
 *
 * Table tenant (`shared_tenants`) additive :
 *   - accounting_document_shares : partage tokenisé d'un document comptable
 *     (token aléatoire, expiration, email destinataire) — accès RGPD limité
 *     au document partagé (pattern CabinetShare #1817).
 *
 * company_id uuid NON nullable (isolation tenant, garde fail-closed #3727).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_document_shares')) {
            Schema::create('accounting_document_shares', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('document_id');
                $table->string('share_token', 64)->unique();
                $table->string('shared_with_email', 255)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('document_id')
                    ->references('id')
                    ->on('accounting_documents')
                    ->cascadeOnDelete();
                $table->index(['company_id', 'document_id']);
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('accounting_document_shares')) {
            Schema::dropIfExists('accounting_document_shares');
        }
    }
};
