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

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('password_hash')->nullable();
                $table->string('google_id')->nullable()->unique();
                $table->string('avatar_url')->nullable();
                $table->string('provider')->default('email');
                $table->string('preferred_language', 2)->default('fr');
                $table->string('status')->default('active');
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->integer('failed_login_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('company_name');
                $table->string('sector')->nullable();
                $table->string('country')->nullable();
                $table->string('city')->nullable();
                $table->string('email');
                $table->string('phone')->nullable();
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->foreignUuid('approved_company_id')->nullable()->constrained('companies');
                $table->text('admin_notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_employee_links')) {
            Schema::create('user_employee_links', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->foreignUuid('company_id')->constrained('companies');
                $table->string('status')->default('pending');
                $table->timestamp('linked_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'company_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_employee_links');
        Schema::dropIfExists('company_requests');
        Schema::dropIfExists('users');
    }
};
