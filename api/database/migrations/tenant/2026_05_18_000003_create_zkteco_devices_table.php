<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('zkteco_devices')) {
            return;
        }

        Schema::create('zkteco_devices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('serial_number', 100)->unique();
            $table->string('name', 120);
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('port')->default(4370);
            $table->enum('protocol', ['tcp', 'udp', 'cloud_api'])->default('tcp');
            $table->string('location_label', 120)->nullable();
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('offline');
            $table->string('model', 60)->nullable();
            $table->string('firmware_version', 60)->nullable();
            $table->unsignedInteger('employee_capacity')->default(1000);
            $table->unsignedInteger('fingerprint_capacity')->default(3000);
            $table->unsignedInteger('face_capacity')->default(500);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        if (Schema::hasTable('zkteco_sync_logs')) {
            return;
        }

        Schema::create('zkteco_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('zkteco_device_id')->constrained('zkteco_devices')->cascadeOnDelete();
            $table->enum('direction', ['pull', 'push'])->default('pull');
            $table->enum('sync_type', ['attendance', 'users', 'fingerprints', 'faces'])->default('attendance');
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['zkteco_device_id', 'created_at']);
        });

        if (Schema::hasTable('kiosk_announcements')) {
            return;
        }

        Schema::create('kiosk_announcements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('title', 200);
            $table->text('body');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_announcements');
        Schema::dropIfExists('zkteco_sync_logs');
        Schema::dropIfExists('zkteco_devices');
    }
};
