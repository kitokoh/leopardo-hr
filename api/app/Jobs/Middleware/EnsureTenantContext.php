<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Tenant\TenantManager;
use App\Models\Company;
use Illuminate\Support\Facades\Log;

/**
 * Job middleware that establishes the correct multi-tenant DB context
 * (PostgreSQL `search_path` + `current_company` container binding) before
 * a tenant-scoped job runs, and tears it down afterwards — mirroring what
 * `App\Http\Middleware\TenantMiddleware` does for HTTP requests.
 *
 * Queue workers are long-running processes that serially execute jobs for
 * many different companies. Without this middleware, jobs that only filter
 * by `->where('company_id', ...)` happen to work under the current default
 * "shared schema" tenancy mode, but silently break — reading/writing the
 * wrong tenant's data, or no data at all — the moment any company is
 * switched to "schema" (physically isolated) tenancy mode, because the
 * connection's `search_path` is never updated for that job.
 *
 * Usage — implement `TenantScopedJob` and register the middleware:
 *
 *   final class MyJob implements ShouldQueue, TenantScopedJob
 *   {
 *       public function __construct(private readonly string $companyId) {}
 *
 *       public function tenantCompanyId(): ?string
 *       {
 *           return $this->companyId;
 *       }
 *
 *       public function middleware(): array
 *       {
 *           return [new EnsureTenantContext()];
 *       }
 *
 *       public function handle(): void { ... }
 *   }
 *
 * If the referenced company no longer exists, the job is released back
 * (not failed) so it can be retried — this guards against a race where the
 * job was queued just before the company row was deleted/soft-deleted in
 * the same request, without permanently losing the job's payload.
 */
final class EnsureTenantContext
{
    public function __construct(private readonly ?TenantManager $tenantManager = null) {}

    public function handle(mixed $job, callable $next): void
    {
        if (! $job instanceof TenantScopedJob) {
            $next($job);

            return;
        }

        $companyId = $job->tenantCompanyId();

        if ($companyId === null) {
            $next($job);

            return;
        }

        $manager = $this->tenantManager ?? app(TenantManager::class);

        /** @var Company|null $company */
        $company = Company::query()->withoutGlobalScopes()->find($companyId);

        if (! $company instanceof Company) {
            Log::channel('structured')->warning('queue.tenant_context.company_not_found', [
                'job' => $job::class,
                'company_id' => $companyId,
            ]);

            // Release rather than fail: the company row may not be visible
            // yet due to replication lag, or this is a genuine data issue
            // that a human should investigate via the `failed_jobs` table
            // after retries are exhausted — not a reason to swallow the job.
            if (method_exists($job, 'release')) {
                $job->release(30);
            }

            return;
        }

        $manager->withinTenant($company, function () use ($job, $next): void {
            $next($job);
        });
    }
}
