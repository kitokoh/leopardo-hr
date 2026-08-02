<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-CRM-001: company_requests created via the self-service trial signup
 * form (RequestTrialSignup action) do not have a manager_name at capture
 * time — it is collected later during the qualification call.
 *
 * The legacy migration (2026_05_02_000003) created this column as NOT NULL
 * which blocks INSERT statements that omit it (e.g. PlatformCrmPipelineApiTest,
 * SelfServiceTrialController).  This migration makes it nullable without
 * touching any existing data.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! Schema::hasTable('company_requests')) {
            return;
        }

        if (! Schema::hasColumn('company_requests', 'manager_name')) {
            return;
        }

        // Only alter if the column is still NOT NULL (idempotent).
        $isNullable = (bool) DB::scalar(<<<'SQL'
            SELECT is_nullable = 'YES'
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name   = 'company_requests'
              AND column_name  = 'manager_name'
SQL);

        if (! $isNullable) {
            DB::statement('ALTER TABLE public.company_requests ALTER COLUMN manager_name DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Intentionally not reversing: setting NOT NULL back would require
        // every existing row to have a non-null value, which is not guaranteed.
    }
};
