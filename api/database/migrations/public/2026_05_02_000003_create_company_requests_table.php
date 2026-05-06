<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (Schema::hasTable('company_requests')) {
            return;
        }

        Schema::create('company_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('employee_id')->index();
            $table->string('company_name', 100);
            $table->string('sector', 100);
            $table->char('country', 2);
            $table->string('city', 100);

            // Manager details
            $table->string('manager_name', 150);
            $table->string('manager_id_card', 50)->nullable();
            $table->string('manager_phone', 30)->nullable();

            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_requests');
    }
};
