<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5223 (workflow documents + numérotation).
 *
 * Tables tenant (`shared_tenants`) additives :
 *   - accounting_number_counters : compteurs de numérotation par
 *     (company_id, type, série, année) — incrément ATOMIQUE via
 *     INSERT ... ON CONFLICT ... RETURNING (concurrent-safe, pattern upsert).
 *   - accounting_documents.source_document_id : lien avoir → facture source
 *     (auto-référence).
 *
 * Règles : migration additive et idempotente (garde schemaTableExists) ;
 * company_id uuid NON nullable (isolation tenant via BelongsToCompany,
 * garde fail-closed #3727).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_number_counters')) {
            Schema::create('accounting_number_counters', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('type', 30);
                $table->string('series', 20);
                $table->integer('year');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'type', 'series', 'year'], 'acc_num_counters_unique');
            });
        }

        if (! Schema::hasColumn('accounting_documents', 'source_document_id')) {
            Schema::table('accounting_documents', function (Blueprint $table): void {
                $table->unsignedBigInteger('source_document_id')->nullable()->after('contact_id');
                $table->foreign('source_document_id')
                    ->references('id')
                    ->on('accounting_documents')
                    ->nullOnDelete();
                $table->index('source_document_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounting_documents', 'source_document_id')) {
            Schema::table('accounting_documents', function (Blueprint $table): void {
                $table->dropForeign(['source_document_id']);
                $table->dropIndex(['source_document_id']);
                $table->dropColumn('source_document_id');
            });
        }

        if (schemaTableExists('accounting_number_counters')) {
            Schema::dropIfExists('accounting_number_counters');
        }
    }
};
