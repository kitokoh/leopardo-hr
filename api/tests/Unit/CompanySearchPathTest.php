<?php

namespace Tests\Unit;

use App\Models\Company;
use PHPUnit\Framework\TestCase;

class CompanySearchPathTest extends TestCase
{
    public function test_it_escapes_single_schema_name()
    {
        $this->assertEquals('"public"', Company::getSafeSearchPath('public'));
        $this->assertEquals('"my_schema"', Company::getSafeSearchPath('my_schema'));
    }

    public function test_it_escapes_array_of_schema_names()
    {
        $this->assertEquals('"tenant1","public"', Company::getSafeSearchPath(['tenant1', 'public']));
    }

    public function test_it_prevents_sql_injection_by_escaping_double_quotes()
    {
        // "schema_name"; DROP TABLE users; --
        $malicious = 'schema_name"; DROP TABLE users; --';
        $expected = '"schema_name""; DROP TABLE users; --"';

        $this->assertEquals($expected, Company::getSafeSearchPath($malicious));
    }

    public function test_it_handles_complex_malicious_input()
    {
        $malicious = ['normal', '"; SET search_path TO "other'];
        $expected = '"normal","""; SET search_path TO ""other"';

        $this->assertEquals($expected, Company::getSafeSearchPath($malicious));
    }
}
