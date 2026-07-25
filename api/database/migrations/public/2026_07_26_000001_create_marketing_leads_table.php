<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-MKT-007 — Marketing acquisition funnel leads.
 *
 * Persists every lead captured by the public vitrine forms (signup, demo
 * request, contact, newsletter — see `front/web/src/app/api/forms/*`) so the
 * platform CRM pipeline (PA2-ADM-004, `PlatformCrmPipelineController`) has a
 * durable, queryable source of truth beyond best-effort external CRM/email
 * webhook forwarding (`captureMarketingLead()` in
 * `front/web/src/app/api/forms/_lib/lead-capture.ts`).
 *
 * Intentionally global (lives in the `public` schema, not per-tenant): a
 * lead does not belong to any company yet — it is pre-tenant acquisition
 * data, the same pattern already used by `company_requests` and
 * `platform_announcements`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_leads')) {
            return;
        }

        Schema::create('marketing_leads', function (Blueprint $table): void {
            $table->id();
            // Client-generated id from lead-capture.ts (e.g. "signup_...",
            // "demo_request_..."), kept for correlation with structured logs
            // and external CRM/email webhook payloads.
            $table->string('external_id', 80)->unique();
            $table->string('type', 30);
            $table->string('email', 255);
            $table->string('locale', 5)->default('fr');
            $table->string('country', 2)->nullable();
            $table->string('page', 300)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->jsonb('payload')->nullable();
            // Admin-facing pipeline tracking (PA2-ADM-004 builds the update
            // endpoints on top of these columns).
            $table->string('status', 20)->default('new');
            $table->text('note')->nullable();
            $table->uuid('converted_company_id')->nullable();
            $table->boolean('crm_forwarded')->default(false);
            $table->boolean('email_forwarded')->default(false);
            $table->timestampTz('captured_at')->nullable();
            $table->timestamps();

            $table->index(['type']);
            $table->index(['source']);
            $table->index(['status']);
            $table->index(['created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_type_check CHECK (type IN ('signup', 'demo_request', 'newsletter', 'contact'))");
            DB::statement("ALTER TABLE marketing_leads ADD CONSTRAINT marketing_leads_status_check CHECK (status IN ('new', 'contacted', 'qualified', 'converted', 'rejected'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
