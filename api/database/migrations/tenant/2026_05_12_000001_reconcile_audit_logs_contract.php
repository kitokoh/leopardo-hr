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
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'user_id')) {
                $table->unsignedInteger('user_id')->nullable()->index()->after('company_id');
            }
            if (! Schema::hasColumn('audit_logs', 'auditable_type')) {
                $table->string('auditable_type', 100)->nullable()->after('action');
            }
            if (! Schema::hasColumn('audit_logs', 'auditable_id')) {
                $table->unsignedBigInteger('auditable_id')->nullable()->after('auditable_type');
            }
            if (! Schema::hasColumn('audit_logs', 'old_values')) {
                $table->jsonb('old_values')->nullable()->after('auditable_id');
            }
            if (! Schema::hasColumn('audit_logs', 'new_values')) {
                $table->jsonb('new_values')->nullable()->after('old_values');
            }
            if (! Schema::hasColumn('audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('new_values');
            }
            if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('audit_logs', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('user_agent');
            }
        });

        if (Schema::hasColumn('audit_logs', 'target_type')) {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN target_type DROP NOT NULL');
        }
        if (Schema::hasColumn('audit_logs', 'target_id')) {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN target_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // No destructive rollback: these columns are the canonical audit log
        // contract for current code and may contain production audit history.
    }
};
