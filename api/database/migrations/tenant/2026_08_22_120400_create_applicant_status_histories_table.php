<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('applicant_status_histories')) {
            return;
        }

        Schema::create('applicant_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('actor_type')->default('company');
            $table->text('note')->nullable();
            $table->timestampTz('changed_at')->useCurrent();
            $table->timestampsTz();
            $table->foreign('applicant_id')->references('id')->on('applicants')->cascadeOnDelete();
            $table->index(['applicant_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_status_histories');
    }
};
