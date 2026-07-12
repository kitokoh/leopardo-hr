<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            
            $table->string('current_step')->nullable()->default('welcome');
            $table->boolean('is_completed')->default(false);
            $table->json('completed_steps')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();

            $table->unique(['employee_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progresses');
    }
};
