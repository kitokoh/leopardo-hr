<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Tenant;

use App\Core\Tenant\Domain\Models\Company;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Contrat fail-closed de `Company::getSafeSearchPath()` (ADR-0028, #8055).
 *
 * Le mode « un schema par tenant » est mort : tout tenant vit dans
 * `shared_tenants`. L'ancienne implementation *nettoyait* le `schema_name`
 * puis retombait silencieusement sur `shared_tenants` — un fail-open qui
 * pouvait faire pointer une session sur le mauvais schema sans le signaler.
 *
 * Ces tests verrouillent le nouveau contrat : jamais de repli silencieux.
 */
final class CompanySearchPathTest extends TestCase
{
    private function company(?string $schemaName, string $tenancyType = 'shared'): Company
    {
        $company = new Company;
        $company->setAttribute('id', 42);
        $company->setAttribute('schema_name', $schemaName);
        $company->setAttribute('tenancy_type', $tenancyType);

        return $company;
    }

    #[Test]
    public function it_returns_the_shared_schema_when_no_schema_is_declared(): void
    {
        self::assertSame('"shared_tenants",public', $this->company(null)->getSafeSearchPath());
        self::assertSame('"shared_tenants",public', $this->company('')->getSafeSearchPath());
    }

    #[Test]
    public function it_returns_the_declared_schema_when_it_is_a_valid_identifier(): void
    {
        self::assertSame('"tenant_42",public', $this->company('tenant_42')->getSafeSearchPath());
        self::assertSame('"_legacy",public', $this->company('_legacy')->getSafeSearchPath());
    }

    #[Test]
    public function it_fails_closed_on_a_corrupted_schema_name_instead_of_falling_back(): void
    {
        foreach (['tenant-42', 'tenant 42', 'tenant;DROP SCHEMA public', 'Tenant42', '42tenant', 'tenant.42'] as $corrupted) {
            try {
                $this->company($corrupted)->getSafeSearchPath();
                self::fail(sprintf('Un schema_name corrompu (%s) doit lever, pas retomber sur shared_tenants.', $corrupted));
            } catch (RuntimeException $e) {
                self::assertStringContainsString('schema_name invalide', $e->getMessage());
                self::assertStringContainsString('#8055', $e->getMessage());
            }
        }
    }

    #[Test]
    public function it_fails_closed_when_schema_tenancy_is_declared_without_a_schema_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tenancy_type=schema sans schema_name');

        $this->company(null, 'schema')->getSafeSearchPath();
    }
}
