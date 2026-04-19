<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_enrollment_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedInteger('approver_employee_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->boolean('requested_face_enabled')->default(false);
            $table->boolean('requested_fingerprint_enabled')->default(false);
            $table->string('requested_face_reference_path', 255)->nullable();
            $table->string('requested_fingerprint_reference_path', 255)->nullable();
            $table->string('requested_fingerprint_device_id', 100)->nullable();
            $table->string('request_source', 30)->default('mobile');
            $table->text('employee_note')->nullable();
            $table->text('manager_note')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('attendance_kiosks', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id')->index();
            $table->string('name', 100);
            $table->string('location_label', 120)->nullable();
            $table->string('device_code', 40)->unique();
            $table->string('status', 20)->default('active');
            $table->string('biometric_mode', 30)->default('fingerprint');
            $table->string('trusted_device_label', 120)->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_kiosks');
        Schema::dropIfExists('biometric_enrollment_requests');
    }
};
