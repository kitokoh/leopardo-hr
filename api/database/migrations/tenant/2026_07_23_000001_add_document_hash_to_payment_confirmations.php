<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PA2-PAY-016 - Simple digital signature: timestamped consent + document
     * hash, without introducing a premature PKI/certificate stack.
     */
    public function up(): void
    {
        $schema = resolveTableSchema('payment_confirmations');
        if ($schema !== null && ! schemaHasColumn('payment_confirmations', 'document_hash')) {
            Schema::table("{$schema}.payment_confirmations", function (Blueprint $table): void {
                $table->string('document_hash', 64)->nullable()->after('document_version');
            });
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('payment_confirmations');
        if ($schema !== null && schemaHasColumn('payment_confirmations', 'document_hash')) {
            Schema::table("{$schema}.payment_confirmations", function (Blueprint $table): void {
                $table->dropColumn('document_hash');
            });
        }
    }
};
