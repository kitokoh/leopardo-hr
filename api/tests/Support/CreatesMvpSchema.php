<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesMvpSchema
{
    protected function setUpMvpSchema(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->string('sector');
            $table->char('country', 2);
            $table->string('city');
            $table->string('email');
            $table->unsignedInteger('plan_id')->nullable();
            $table->string('schema_name', 63);
            $table->string('tenancy_type', 20)->default('shared');
            $table->string('status', 20)->default('active');
            $table->date('subscription_start')->nullable();
            $table->date('subscription_end')->nullable();
            $table->char('language', 2)->default('fr');
            $table->string('timezone', 50)->default('Africa/Algiers');
            $table->char('currency', 3)->default('DZD');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id');
            $table->string('matricule', 20)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 20)->default('employee');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDownMvpSchema(): void
    {
        app()->forgetInstance('current_company');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');
    }
}

