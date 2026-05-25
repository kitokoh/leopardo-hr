<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('attendance_correction_requests')) {
            return;
        }

        Schema::create('attendance_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id')->index();
            $table->unsignedBigInteger('attendance_log_id')->nullable()->index();
            $table->date('date')->index();
            $table->timestampTz('requested_check_in');
            $table->timestampTz('requested_check_out')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected', 'applied'])->default('pending')->index();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('attendance_log_id')->references('id')->on('attendance_logs')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
