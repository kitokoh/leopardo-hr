<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('training_courses')) {
            Schema::create('training_courses', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('category', 100)->nullable();
                $table->enum('type', ['internal', 'external', 'online', 'certification'])->default('internal');
                $table->string('provider', 200)->nullable();
                $table->decimal('duration_hours', 6, 2)->nullable();
                $table->unsignedSmallInteger('max_participants')->nullable();
                $table->decimal('cost_per_participant', 12, 2)->nullable();
                $table->string('currency', 3)->default('DZD');
                $table->string('materials_path', 500)->nullable();
                $table->boolean('active')->default(true);
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('training_sessions')) {
            Schema::create('training_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('training_course_id');
                $table->foreign('training_course_id')->references('id')->on('training_courses')->cascadeOnDelete();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('trainer_id')->nullable();
                $table->foreign('trainer_id')->references('id')->on('employees')->nullOnDelete();
                $table->string('external_trainer', 200)->nullable();
                $table->date('start_date');
                $table->date('end_date');
                $table->string('location', 200)->nullable();
                $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
                $table->text('notes')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('training_enrollments')) {
            Schema::create('training_enrollments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('training_session_id');
                $table->foreign('training_session_id')->references('id')->on('training_sessions')->cascadeOnDelete();
                $table->unsignedInteger('employee_id');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->uuid('company_id')->index();
                $table->enum('status', ['enrolled', 'attended', 'completed', 'no_show', 'cancelled'])->default('enrolled');
                $table->decimal('score', 5, 2)->nullable();
                $table->string('certificate_path', 500)->nullable();
                $table->text('feedback')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampsTz();

                $table->unique(['training_session_id', 'employee_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('training_enrollments');
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('training_courses');
    }
};
