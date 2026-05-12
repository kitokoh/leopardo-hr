<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cabinet_folders')) {
        Schema::create('cabinet_folders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('color', 30)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('cabinet_folders')->nullOnDelete();
        });
        }

        if (! Schema::hasTable('cabinet_documents')) {
        Schema::create('cabinet_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('folder_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->string('disk', 50)->default('local');
            $table->string('path', 500);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('folder_id')->references('id')->on('cabinet_folders')->nullOnDelete();
        });
        }

        if (! Schema::hasTable('cabinet_shares')) {
        Schema::create('cabinet_shares', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->morphs('shareable');
            $table->string('share_token', 64)->unique();
            $table->string('shared_via', 30);
            $table->string('shared_with_email', 255)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cabinet_shares');
        Schema::dropIfExists('cabinet_documents');
        Schema::dropIfExists('cabinet_folders');
    }
};
