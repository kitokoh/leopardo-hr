<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('delivery_cod_settlements', 'collected_at')) {
            Schema::table('delivery_cod_settlements', function (Blueprint $table): void {
                $table->timestamp('collected_at')->nullable()->after('commission_minor');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_cod_settlements', 'collected_at')) {
            Schema::table('delivery_cod_settlements', function (Blueprint $table): void {
                $table->dropColumn('collected_at');
            });
        }
    }
};
