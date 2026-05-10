<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('job_postings')) {
            Schema::create('job_postings', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->unsignedInteger('department_id')->nullable();
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                $table->unsignedInteger('position_id')->nullable();
                $table->foreign('position_id')->references('id')->on('positions')->nullOnDelete();
                $table->string('location', 200)->nullable();
                $table->enum('remote_policy', ['onsite', 'hybrid', 'remote'])->default('onsite');
                $table->enum('contract_type', ['cdi', 'cdd', 'stage', 'freelance'])->default('cdi');
                $table->decimal('salary_range_min', 12, 2)->nullable();
                $table->decimal('salary_range_max', 12, 2)->nullable();
                $table->string('currency', 3)->default('DZD');
                $table->jsonb('skills_required')->nullable();
                $table->enum('status', ['draft', 'published', 'closed', 'archived'])->default('draft');
                $table->timestampTz('published_at')->nullable();
                $table->timestampTz('closes_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestampsTz();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('applicants')) {
            Schema::create('applicants', function (Blueprint $table) {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('job_posting_id');
                $table->foreign('job_posting_id')->references('id')->on('job_postings')->cascadeOnDelete();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 255);
                $table->string('phone', 30)->nullable();
                $table->string('resume_path', 500)->nullable();
                $table->text('cover_letter')->nullable();
                $table->enum('source', ['website', 'referral', 'linkedin', 'agency', 'other'])->default('website');
                $table->enum('status', ['new', 'screening', 'interview', 'offer', 'hired', 'rejected', 'withdrawn'])->default('new');
                $table->unsignedSmallInteger('rating')->nullable();
                $table->text('notes')->nullable();
                $table->timestampTz('applied_at')->useCurrent();
                $table->timestampsTz();

                $table->index(['job_posting_id', 'status']);
            });
        }

        if (! Schema::hasTable('interviews')) {
            Schema::create('interviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('applicant_id');
                $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('interviewer_id')->nullable();
                $table->foreign('interviewer_id')->references('id')->on('employees')->nullOnDelete();
                $table->enum('type', ['phone', 'video', 'onsite', 'technical'])->default('onsite');
                $table->timestampTz('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->enum('status', ['scheduled', 'completed', 'cancelled', 'no_show'])->default('scheduled');
                $table->text('feedback')->nullable();
                $table->unsignedSmallInteger('rating')->nullable();
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('applicants');
        Schema::dropIfExists('job_postings');
    }
};
