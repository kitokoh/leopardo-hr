<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Carbon\Carbon;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7491 — Session client 30 jours glissants.
 *
 * Le login doit émettre un token dont l'échéance est ~30 jours (43 200 min,
 * défaut `sanctum.expiration`). Avant #7491, la durée était de 7 jours
 * (10 080 min) : un utilisateur qui fermait son navigateur devait se
 * reconnecter chaque semaine — exactement le comportement que le
 * propriétaire a interdit pour le tunnel d'acquisition.
 */
class SessionDurationTest extends TestCase
{
    use CreatesMvpSchema;

    private Company $company;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        $company = Company::factory()->create();
        $this->company = $company;

        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->manager = $manager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_login_issues_a_token_expiring_in_thirty_days(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->manager->email,
            'password' => 'password123', // défaut EmployeeFactory
        ]);

        $response->assertOk();

        $expiresAt = $response->json('data.token_expires_at');
        $this->assertIsString($expiresAt);

        $expected = now()->addMinutes(43200);
        $actual = Carbon::parse($expiresAt);

        // Tolérance de 5 minutes (temps d'exécution de la requête).
        $this->assertTrue(
            $actual->diffInMinutes($expected) <= 5,
            "Le token expire à {$actual->toIso8601String()} au lieu de ~30 jours ({$expected->toIso8601String()}).",
        );
    }

    public function test_sanctum_default_expiration_is_thirty_days(): void
    {
        // Garde de configuration : si le défaut retombe à 7 jours, ce test
        // le signale avant même le premier login.
        $this->assertSame(43200, (int) config('sanctum.expiration'));
    }
}
