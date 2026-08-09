<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $schema = resolveTableSchema('audit_logs');
        if ($schema === null) {
            return;
        }

        Schema::table("{$schema}.audit_logs", function (Blueprint $table): void {
            if (! schemaHasColumn('audit_logs', 'user_id')) {
                $table->unsignedInteger('user_id')->nullable()->index()->after('company_id');
            }
            if (! schemaHasColumn('audit_logs', 'auditable_type')) {
                $table->string('auditable_type', 100)->nullable()->after('action');
            }
            if (! schemaHasColumn('audit_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }
            if (! schemaHasColumn('audit_logs', 'old_values')) {
                $table->jsonb('old_values')->nullable()->after('auditable_id');
            }
            if (! schemaHasColumn('audit_logs', 'new_values')) {
                $table->jsonb('new_values')->nullable()->after('old_values');
            }
            if (! schemaHasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('new_values');
            }
            if (! schemaHasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            }
            if (! schemaHasColumn('audit_logs', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('user_agent');
            }
        });

        if (schemaHasColumn('audit_logs', 'target_type')) {
            DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN target_type DROP NOT NULL");
        }
        if (schemaHasColumn('audit_logs', 'target_id')) {
            DB::statement("ALTER TABLE \"{$schema}\".\"audit_logs\" ALTER COLUMN target_id DROP NOT NULL");
        }
    }

    public function down(): void
    {
        // No destructive rollback: these columns are the canonical audit log
        // contract for current code and may contain production audit history.
    }
};
