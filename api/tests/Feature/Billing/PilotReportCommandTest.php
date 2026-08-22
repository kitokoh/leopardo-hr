<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5156 — `pilot:report` : suivi d'usage hebdomadaire par compagnie
 * pilote. « Pilote actif » doit être mesuré, pas déclaré (gate J60).
 *
 * NB : on passe par `Artisan::call()` (exécution synchrone + `Artisan::output()`
 * réel) plutôt que `$this->artisan()->assert*()` dont les assertions ne
 * s'exécutent qu'au destructeur du PendingCommand (issue #5201).
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
        /** @var Company $pilot */
        $pilot = Company::factory()->create(['metadata' => ['pilot' => true]]);
        /** @var Company $other */
        $other = Company::factory()->create(['metadata' => ['pilot' => false]]);

        $exit = Artisan::call('pilot:report');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($pilot->name, Artisan::output());
        $this->assertStringNotContainsString($other->name ?? '', Artisan::output());
    }

    public function test_targets_specific_company_with_company_option(): void
    {
        /** @var Company $pilot */
        $pilot = Company::factory()->create(['metadata' => ['pilot' => true]]);
        Company::factory()->create(['metadata' => ['pilot' => true]]);

        $exit = Artisan::call('pilot:report', ['--company' => [$pilot->slug]]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($pilot->name, Artisan::output());
    }

    public function test_fails_when_no_pilot_company_and_no_target(): void
    {
        Company::factory()->create(['metadata' => ['pilot' => false]]);

        $exit = Artisan::call('pilot:report');

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Aucune compagnie pilote', Artisan::output());
    }

    public function test_json_output_contains_company_slug(): void
    {
        /** @var Company $pilot */
        $pilot = Company::factory()->create(['metadata' => ['is_pilot' => true]]);

        $exit = Artisan::call('pilot:report', ['--json' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($pilot->slug, Artisan::output());
    }
}
