<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('invoices') || Schema::hasColumn('invoices', 'updated_at')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->timestampTz('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'updated_at')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }
};
