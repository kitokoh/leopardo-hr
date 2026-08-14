<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Lang;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1872 — le registre des pays supportés expose, pour chaque pays,
 * l'avertissement de conformité localisé (payroll.confidence.*) en plus du
 * niveau de confiance : un manager ne doit jamais confondre une règle
 * pilote/placeholder avec une paie légalement certifiée, et un pays sans
 * règles (GB/US) reçoit un message neutre explicite.
 */
class SupportedCountryComplianceTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ']);

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->manager = $manager;
    }

    public function test_registry_exposes_confidence_level_and_localized_compliance_warning(): void
    {
        Sanctum::actingAs($this->manager);

        $countries = collect($this->getJson('/api/v1/supported-countries')->assertOk()->json('data'))
            ->keyBy('country');

        // DZ = pilot : avertissement localisé du catalogue (jamais vide).
        $dz = $countries->get('DZ');
        $this->assertNotNull($dz);
        $this->assertSame('pilot', $dz['confidence']);
        $this->assertTrue($dz['available']);
        $this->assertSame(
            Lang::get('payroll.confidence.pilot.message', ['country' => 'DZ']),
            $dz['compliance_warning'],
        );

        // CA = placeholder : avertissement non ambigu.
        $ca = $countries->get('CA');
        $this->assertNotNull($ca);
        $this->assertSame('placeholder', $ca['confidence']);
        $this->assertTrue($ca['available']);
        $this->assertSame(
            Lang::get('payroll.confidence.placeholder.message', ['country' => 'CA']),
            $ca['compliance_warning'],
        );

        // GB = référencé sans règles de paie : message neutre, non disponible.
        $gb = $countries->get('GB');
        $this->assertNotNull($gb);
        $this->assertSame('unknown', $gb['confidence']);
        $this->assertFalse($gb['available']);
        $this->assertSame(
            Lang::get('payroll.confidence.unknown.message', ['country' => 'GB']),
            $gb['compliance_warning'],
        );
    }

    public function test_every_registry_country_has_a_non_empty_compliance_warning(): void
    {
        Sanctum::actingAs($this->manager);

        $registry = $this->getJson('/api/v1/supported-countries')->assertOk()->json('data');

        foreach ($registry as $entry) {
            $this->assertIsString($entry['compliance_warning']);
            $this->assertNotSame('', $entry['compliance_warning'], $entry['country'].': compliance_warning must not be empty');
        }
    }
}
