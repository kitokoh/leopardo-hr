<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-ADM-006 — Secure super-admin impersonation.
 *
 * Every "log in as this employee" action taken from the platform admin
 * console is recorded here: who impersonated (super-admin), whom (company +
 * employee), why (mandatory reason, for audit), and for how long (impersonation
 * tokens are time-limited and can be ended early). This table is intentionally
 * global (public schema): the acting party is a SuperAdmin, not a tenant
 * employee, and a session can target any tenant regardless of its schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_impersonation_sessions')) {
            return;
        }

        Schema::create('platform_impersonation_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('super_admin_id');
            $table->uuid('company_id');
            $table->unsignedBigInteger('employee_id');
            // Personal access token id backing the impersonation session, so
            // ending the session (or its natural expiry) can revoke exactly
            // that token without touching the employee's other sessions.
            $table->unsignedBigInteger('personal_access_token_id')->nullable();
            // Denormalized point-in-time snapshot: `employees`/`companies` are
            // per-tenant-schema rows, but this audit table is global (public
            // schema) and lists sessions across every tenant at once. Storing
            // the display name here avoids having to switch search_path per
            // row just to render a history list, and preserves the label
            // even if the employee/company is later renamed or deleted.
            $table->string('company_name', 200)->nullable();
            $table->string('employee_name', 200)->nullable();
            $table->string('employee_email', 150)->nullable();
            $table->string('reason', 500);
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('ended_at')->nullable();
            $table->unsignedInteger('ended_by')->nullable();
            $table->timestampTz('created_at')->nullable();

            $table->index('super_admin_id');
            $table->index('company_id');
            $table->index('employee_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_impersonation_sessions');
    }
};
