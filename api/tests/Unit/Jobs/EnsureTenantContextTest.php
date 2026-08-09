<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Contracts\Queue\TenantScopedJob;
use App\Core\Tenant\TenantManager;
use App\Jobs\Middleware\EnsureTenantContext;
use App\Core\Tenant\Domain\Models\Company;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

class EnsureTenantContextTest extends TestCase
{
    use Tests\RefreshTenantDatabase;

    public function test_it_skips_jobs_that_do_not_implement_tenant_scoped_job(): void
    {
        $middleware = new EnsureTenantContext();

        $job = new class
        {
            public bool $ran = false;
        };

        $middleware->handle($job, function ($j): void {
            $j->ran = true;
        });

        $this->assertTrue($job->ran);
    }

    public function test_it_skips_context_setup_when_tenant_company_id_is_null(): void
    {
        $middleware = new EnsureTenantContext();

        $job = new class implements TenantScopedJob
        {
            public bool $ran = false;

            public function tenantCompanyId(): ?string
            {
                return null;
            }
        };

        $middleware->handle($job, function ($j): void {
            $j->ran = true;
        });

        $this->assertTrue($job->ran);
        $this->assertFalse(app()->bound('current_company') && app('current_company') instanceof Company);
    }

    public function test_it_establishes_and_tears_down_tenant_context_around_the_job(): void
    {
        $company = Company::factory()->create();

        $middleware = new EnsureTenantContext();

        $job = new class($company->id) implements TenantScopedJob
        {
            public ?Company $seenCompany = null;

            public function __construct(private readonly string $companyId) {}

            public function tenantCompanyId(): ?string
            {
                return $this->companyId;
            }
        };

        $middleware->handle($job, function ($j) use (&$job): void {
            /** @var TenantManager $manager */
            $manager = app(TenantManager::class);
            $job->seenCompany = $manager->current();
        });

        $this->assertNotNull($job->seenCompany);
        $this->assertSame($company->id, $job->seenCompany->id);

        // Context must be torn down after the job runs.
        $this->assertFalse(
            app()->bound('current_company') && app('current_company') instanceof Company
        );
    }

    public function test_it_releases_the_job_when_the_company_no_longer_exists(): void
    {
        $middleware = new EnsureTenantContext();

        $job = new class implements TenantScopedJob
        {
            public bool $ran = false;

            public bool $released = false;

            public int $releaseDelay = 0;

            public function tenantCompanyId(): ?string
            {
                return '00000000-0000-0000-0000-000000000000';
            }

            public function release(int $delay = 0): void
            {
                $this->released = true;
                $this->releaseDelay = $delay;
            }
        };

        $middleware->handle($job, function ($j): void {
            $j->ran = true;
        });

        $this->assertFalse($job->ran);
        $this->assertTrue($job->released);
        $this->assertSame(30, $job->releaseDelay);
    }
}

