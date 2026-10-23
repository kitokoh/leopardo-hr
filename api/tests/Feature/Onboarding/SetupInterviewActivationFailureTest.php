<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\SolutionActivator;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Onboarding\Application\Actions\CompleteSetupInterview;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-ONBOARDING — l'échec d'activation d'une verticale n'est plus AVALÉ.
 *
 * Avant ce correctif, `CompleteSetupInterview` capturait `\Throwable` sans
 * rien journaliser ni remonter : l'utilisateur découvrait un espace sans
 * module, sans message ni trace exploitable par le support.
 *
 * Désormais :
 *  - l'échec reste NON BLOQUANT pour l'entretien (l'espace demeure utilisable) ;
 *  - il est JOURNALISÉ (`setup_interview.solution_activation_failed`) ;
 *  - il est REMONTÉ au client dans `activated.failed_details` (code + raison).
 */
class SetupInterviewActivationFailureTest extends TestCase
{
    use RefreshTenantDatabase;

    private function actingAsPrincipal(Company $company): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);
    }

    /**
     * Persiste une reponse d'entretien, seul chemin par lequel
     * `CompleteSetupInterview` prend connaissance du secteur declare (lecture
     * fraiche de `companies.metadata.setup_interview.answers`).
     */
    private function recordSectorAnswer(Company $company, string $sector): void
    {
        $fresh = Company::query()->findOrFail($company->id);
        $metadata = $fresh->metadata ?? [];
        $metadata['setup_interview'] = [
            'status' => 'in_progress',
            'answers' => ['sector' => $sector],
        ];
        $fresh->forceFill(['metadata' => $metadata])->save();
    }

    public function test_activation_failure_is_logged_and_surfaced_to_the_client(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        $this->actingAsPrincipal($company);

        // L'entretien doit avoir DECLARE le secteur restaurant : c'est
        // `SetupInterviewPlanner::plan()` qui traduit `sector=restaurant` en
        // code de verticale `restaurant`. Sans reponse, le plan est vide,
        // aucune activation n'est tentee et le test ne prouverait que la
        // vacuite — pas la remontee d'echec.
        $this->recordSectorAnswer($company, 'restaurant');

        // L'activateur echoue pour la verticale : on simule une panne
        // d'infrastructure (dépendance manquante, catalogue indisponible…).
        $this->mock(SolutionActivator::class, function ($mock): void {
            $mock->shouldReceive('activateWithDependencies')
                ->andThrow(new \RuntimeException('catalogue indisponible'));
        });

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'setup_interview.solution_activation_failed'
                    && ($context['solution'] ?? null) === 'restaurant'
                    && ($context['message'] ?? null) === 'catalogue indisponible';
            });

        $result = app(CompleteSetupInterview::class)->execute($company, null);

        // Non bloquant : l'entretien est bien clôturé.
        $this->assertSame('completed', $result['status']);
        $this->assertFalse($result['already_completed']);

        // L'échec est remonté, avec sa raison — plus d'échec silencieux.
        $this->assertContains('restaurant', $result['activated']['failed']);
        $this->assertNotEmpty($result['activated']['failed_details']);

        $detail = collect($result['activated']['failed_details'])
            ->firstWhere('code', 'restaurant');

        $this->assertNotNull($detail, 'Le détail de l\'échec doit porter le code de la solution.');
        $this->assertSame('catalogue indisponible', $detail['reason']);
    }

    public function test_successful_activation_reports_no_failure_details(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);

        $this->actingAsPrincipal($company);

        $result = app(CompleteSetupInterview::class)->execute($company, null);

        $this->assertSame('completed', $result['status']);
        $this->assertSame([], $result['activated']['failed']);
        $this->assertSame([], $result['activated']['failed_details']);
    }
}
