<?php

namespace Tests\Unit;

use App\Models\Company;
use PHPUnit\Framework\TestCase;

class CompanySearchPathTest extends TestCase
{
    /** @test */
    public function it_generates_safe_search_path()
    {
        // Simple case
        $this->assertEquals('"public",public', Company::getSafeSearchPath('public'));

        // Shared tenants case
        $this->assertEquals('"shared_tenants",public', Company::getSafeSearchPath('shared_tenants'));

        // Potential injection via double quotes
        // In PostgreSQL, to escape a double quote in an identifier, you double it.
        // SET search_path TO "some""schema",public
        $this->assertEquals('"some""schema",public', Company::getSafeSearchPath('some"schema'));

        // Complex malicious attempt
        // If someone tries: my_schema",public; DROP TABLE users; --
        // It should become: "my_schema"",public; DROP TABLE users; --",public
        $this->assertEquals('"my_schema"",public; DROP TABLE users; --",public', Company::getSafeSearchPath('my_schema",public; DROP TABLE users; --'));
    }
}
