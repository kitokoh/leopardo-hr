<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            Schema::create('notification_preferences', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->unique();
                $table->boolean('app_enabled')->default(true);
                $table->boolean('email_enabled')->default(true);
                $table->boolean('push_enabled')->default(true);
                $table->boolean('sms_enabled')->default(false);
                $table->boolean('whatsapp_enabled')->default(false);
                $table->char('locale', 2)->nullable();
                $table->string('timezone', 64)->nullable();
                $table->json('categories')->nullable();
                $table->json('quiet_hours')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'employee_id']);
            });
        }

        if (! Schema::hasTable('communication_events')) {
            Schema::create('communication_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->unsignedInteger('notification_id')->nullable()->index();
                $table->string('event_name', 80);
                $table->string('channel', 40)->default('app');
                $table->string('status', 40)->default('recorded');
                $table->string('provider', 80)->nullable();
                $table->string('template_key', 120)->nullable();
                $table->json('metadata')->nullable();
                $table->text('error_message')->nullable();
                $table->timestampTz('occurred_at')->default(DB::raw('CURRENT_TIMESTAMP'));
                $table->timestamps();

                $table->index(['company_id', 'event_name', 'occurred_at']);
                $table->index(['company_id', 'channel', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_events');
        Schema::dropIfExists('notification_preferences');
    }
};
