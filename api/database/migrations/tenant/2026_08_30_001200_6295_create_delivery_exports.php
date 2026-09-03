<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_exports')) {
            Schema::create('delivery_exports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('status', 20)->default('pending'); // pending | generating | done | failed
                $table->date('from_date');
                $table->date('to_date');
                $table->string('filename', 255)->nullable();
                $table->string('error_message', 500)->nullable();
                $table->unsignedBigInteger('requested_by')->nullable(); // employee id par valeur
                $table->timestamp('completed_at')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'delivery_exports_company_status_idx');
            });

            DB::statement("COMMENT ON TABLE delivery_exports IS 'Exports async des livraisons (BC-26-D07/#6295) - pending/generating/done/failed, tenant-scoped, retry borne (pattern BankExport).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_exports');
    }
};
