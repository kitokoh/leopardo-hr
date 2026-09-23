<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Support\TenantCache;
use Tests\TestCase;

/**
 * #8058 — helper central des clés de cache tenant : préfixe company_id
 * systématique, fail-closed hors contexte ou sur entrée vide.
 */
class TenantCacheTest extends TestCase
{
    public function test_key_for_prefixes_with_explicit_company_id(): void
    {
        $this->assertSame(
            'tenant:company-uuid-1:hr_report:headcount',
            TenantCache::keyFor('company-uuid-1', 'hr_report:headcount')
        );
    }

    public function test_key_uses_the_current_tenant_context(): void
    {
        $company = new Company;
        $company->id = 'company-uuid-2';
        app()->instance('current_company', $company);

        $this->assertSame('tenant:company-uuid-2:foo', TenantCache::key('foo'));
    }

    public function test_key_is_fail_closed_outside_tenant_context(): void
    {
        app()->forgetInstance('current_company');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('hors contexte tenant');
        TenantCache::key('foo');
    }

    public function test_key_for_rejects_empty_company_id(): void
    {
        $this->expectException(\RuntimeException::class);
        TenantCache::keyFor('', 'foo');
    }

    public function test_key_for_rejects_empty_suffix(): void
    {
        $this->expectException(\RuntimeException::class);
        TenantCache::keyFor('company-uuid-1', '');
    }
}
