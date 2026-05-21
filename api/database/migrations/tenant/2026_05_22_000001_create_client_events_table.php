<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('client_events')) {
            return;
        }

        Schema::create('client_events', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->nullable()->index();
            $table->string('event_name', 80);
            $table->string('surface', 40)->default('web');
            $table->string('session_id', 120)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['company_id', 'event_name', 'occurred_at']);
            $table->index(['company_id', 'surface', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_events');
    }
};
