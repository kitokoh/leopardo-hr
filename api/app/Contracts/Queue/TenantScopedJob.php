<?php

declare(strict_types=1);

namespace App\Contracts\Queue;

/**
 * Contract for queued jobs that must execute within a specific tenant's
 * context (PostgreSQL `search_path` + `current_company` container binding).
 *
 * Any job that touches tenant-scoped Eloquent models (models using
 * `App\Shared\Traits\BelongsToCompany`, or querying tables that live under
 * a per-tenant PostgreSQL schema once "schema isolation" mode is enabled)
 * MUST implement this interface and declare
 * `App\Jobs\Middleware\EnsureTenantContext` in its `middleware()` method.
 *
 * Without this, a job that resolves its tenant only via an explicit
 * `->where('company_id', ...)` filter works today under the default
 * "shared schema" tenancy mode (all companies share one PostgreSQL schema,
 * logically isolated by a `company_id` column), but will silently query the
 * wrong schema — or nothing at all — the moment a company is switched to
 * "schema" (physically isolated) tenancy mode, since `search_path` is never
 * updated for that job's DB connection.
 *
 * @see \App\Core\Tenant\TenantManager::withinTenant()
 * @see \App\Jobs\Middleware\EnsureTenantContext
 */
interface TenantScopedJob
{
    /**
     * The UUID of the company (tenant) this job must run under, or null if
     * the job has no tenant context to establish (e.g. platform-wide jobs).
     */
    public function tenantCompanyId(): ?string;
}
