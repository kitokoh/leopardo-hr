<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('webhook_endpoints')) {
            Schema::create('webhook_endpoints', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('url', 500);
                $table->jsonb('events');
                $table->text('secret');
                $table->boolean('active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestampTz('last_triggered_at')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('webhook_deliveries')) {
            Schema::create('webhook_deliveries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id');
                $table->foreign('webhook_endpoint_id')->references('id')->on('webhook_endpoints')->cascadeOnDelete();
                $table->string('event', 100);
                $table->jsonb('payload');
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestampTz('delivered_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
    }
};
