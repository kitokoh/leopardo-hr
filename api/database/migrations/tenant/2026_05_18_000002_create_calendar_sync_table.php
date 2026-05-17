<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_connections')) {
            return;
        }

        Schema::create('calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('provider', ['google', 'outlook', 'caldav'])->default('google');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('calendar_id', 255)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('sync_leaves')->default(true);
            $table->boolean('sync_training')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'provider']);
        });

        if (Schema::hasTable('calendar_events')) {
            return;
        }

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('external_event_id', 255)->nullable();
            $table->string('provider', 20)->default('google');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'failed', 'deleted'])->default('pending');
            $table->timestamps();

            $table->index(['employee_id', 'starts_at']);
            $table->index(['sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendar_connections');
    }
};
