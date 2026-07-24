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
        if (Schema::hasTable('payment_confirmations') && ! Schema::hasColumn('payment_confirmations', 'document_hash')) {
            Schema::table('payment_confirmations', function (Blueprint $table): void {
                $table->string('document_hash', 64)->nullable()->after('document_version');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_confirmations') && Schema::hasColumn('payment_confirmations', 'document_hash')) {
            Schema::table('payment_confirmations', function (Blueprint $table): void {
                $table->dropColumn('document_hash');
            });
        }
    }
};
