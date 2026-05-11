<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->string('title', 200)->default('Nouvelle conversation');
                $table->jsonb('messages')->default('[]');
                $table->jsonb('context')->default('{}');
                $table->unsignedInteger('token_count')->default(0);
                $table->timestampsTz();

                $table->foreign('user_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->index(['user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('ai_audit_logs')) {
            Schema::create('ai_audit_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('company_id')->nullable()->index();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('conversation_id')->nullable();
                $table->text('prompt');
                $table->text('response');
                $table->jsonb('tools_called')->default('[]');
                $table->string('provider', 50);
                $table->string('model', 100);
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('cost_cents')->default(0);
                $table->unsignedInteger('duration_ms')->default(0);
                $table->text('error')->nullable();
                $table->timestampTz('created_at')->useCurrent();

                $table->foreign('user_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('conversation_id')->references('id')->on('ai_conversations')->nullOnDelete();
                $table->index(['company_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ai_tool_registry')) {
            Schema::create('ai_tool_registry', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100)->unique();
                $table->text('description');
                $table->jsonb('parameters')->default('{}');
                $table->jsonb('required_permissions')->default('[]');
                $table->enum('required_role', ['employee', 'manager', 'admin', 'super_admin'])->default('employee');
                $table->string('module', 50)->default('rh');
                $table->boolean('active')->default(true);
                $table->timestampsTz();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('ai_tool_registry');
    }
};
