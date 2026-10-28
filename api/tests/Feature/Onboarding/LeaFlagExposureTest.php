<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #8231 (BOS-043) — l'ecran Lea ne doit s'afficher que si le parcours est
 * REELLEMENT servi.
 *
 * Le flag `onboarding.lea.enabled` est un interrupteur **serveur** : quand il
 * est OFF, `POST /onboarding/lea/interpret` et `/confirm` repondent 404
 * (verrouille par `LeaOnboardingControllerTest`). Le front ne peut pas le
 * deduire de son build — il doit donc recevoir l'etat reel par `/auth/me`.
 *
 * Sans cette exposition, deux defauts symetriques :
 *  - flag OFF et entree affichee → l'utilisateur clique et prend un 404 ;
 *  - flag ON et entree cachee → la fonctionnalite est livree mais invisible.
 *
 * Le test verrouille les deux sens, et surtout le **defaut sur** : un flag
 * absent de la configuration doit valoir `false`, jamais `true` (on n'active
 * pas un parcours IA par omission).
 */
class LeaFlagExposureTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_auth_me_exposes_lea_enabled_true_when_the_server_flag_is_on(): void
    {
        config(['onboarding.lea.enabled' => true]);

        $company = $this->company();
        $this->actingAsManager($company);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.company.lea_enabled', true);
    }

    public function test_auth_me_exposes_lea_enabled_false_when_the_server_flag_is_off(): void
    {
        config(['onboarding.lea.enabled' => false]);

        $company = $this->company();
        $this->actingAsManager($company);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.company.lea_enabled', false);
    }

    public function test_lea_enabled_defaults_to_false_when_the_config_key_is_missing(): void
    {
        // On simule une configuration qui ne connait pas encore la cle (cas
        // d'un deploiement dont le fichier de config n'a pas ete mis a jour).
        // Le defaut doit etre ferme : pas de parcours IA par omission.
        config(['onboarding.lea' => []]);

        $company = $this->company();
        $this->actingAsManager($company);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.company.lea_enabled', false);
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'metadata' => [],
        ]);

        return $company;
    }

    private function actingAsManager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }
}
