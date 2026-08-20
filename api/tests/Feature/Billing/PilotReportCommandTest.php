<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5156 — `pilot:report` : suivi d'usage hebdomadaire par compagnie
 * pilote. « Pilote actif » doit être mesuré, pas déclaré (gate J60).
 */
class PilotReportCommandTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_resolves_companies_marked_pilot_in_metadata(): void
    {
        $pilot = Company::factory()->create(['metadata' => ['pilot' => true]]);
        $other = Company::factory()->create(['metadata' => ['pilot' => false]]);

        $this->artisan('pilot:report')
            ->expectsOutputToContain($pilot->name)
            ->doesntExpectOutputToContain($other->name ?? '')
            ->assertExitCode(0);
    }

    public function test_targets_specific_company_with_company_option(): void
    {
        $pilot = Company::factory()->create(['metadata' => ['pilot' => true]]);
        Company::factory()->create(['metadata' => ['pilot' => true]]);

        $this->artisan('pilot:report', ['--company' => [$pilot->slug]])
            ->expectsOutputToContain($pilot->name)
            ->assertExitCode(0);
    }

    public function test_fails_when_no_pilot_company_and_no_target(): void
    {
        Company::factory()->create(['metadata' => ['pilot' => false]]);

        $this->artisan('pilot:report')
            ->expectsOutputToContain('Aucune compagnie pilote')
            ->assertExitCode(1);
    }

    public function test_json_output_contains_company_slug(): void
    {
        $pilot = Company::factory()->create(['metadata' => ['is_pilot' => true]]);

        $this->artisan('pilot:report', ['--json' => true])
            ->expectsOutputToContain($pilot->slug)
            ->assertExitCode(0);
    }
}
