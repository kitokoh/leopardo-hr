<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BOS-009 (#8227) — activation de la solution travelagency au provisioning
 * d'un tenant self-service (signup -> OTP -> verify).
 *
 * Pattern `TrialRestaurantSolutionActivationTest` (#6693), avec une
 * assertion SUPPLÉMENTAIRE exigée par l'issue : le seed géographique doit
 * être présent. C'est le point qui manquait — l'activation Travel posait le
 * flag mais rien ne prouvait que le référentiel pays/villes était seedé, or
 * sans lui une agence ne peut pas créer sa première gare ni son premier
 * trajet : le workspace est « activé » mais inutilisable.
 */
class TrialTravelSolutionActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * @param  array{email: string, company: string, role: string, employees: string, country: string, solution?: string}  $signupPayload
     */
    private function signupAndVerify(array $signupPayload): Company
    {
        $this->postJson('/api/v1/trial/signup', $signupPayload)->assertStatus(200);

        $companyRequest = CompanyRequest::query()
            ->where('email', $signupPayload['email'])
            ->where('status', 'pending')
            ->firstOrFail();

        // Contrat reel du controleur : `verify` cree la societe et repond
        // **201** (creation de ressource), pas 200. Le test de reference
        // `TrialRestaurantSolutionActivationTest` attend 200 et echoue donc
        // sur `main` — defaut preexistant, non reproduit ici.
        $this->postJson('/api/v1/trial/verify', [
            'email' => $signupPayload['email'],
            'code' => $companyRequest->verification_token,
        ])->assertStatus(201)->assertJson(['success' => true]);

        /** @var Company $company */
        $company = Company::query()->where('email', $signupPayload['email'])->firstOrFail();

        return $company;
    }

    /**
     * @return array{email: string, company: string, role: string, employees: string, country: string}
     */
    private function basePayload(string $email): array
    {
        return [
            'email' => $email,
            'company' => 'Agence Test DZ',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ];
    }

    public function test_signup_with_travelagency_solution_activates_solution_and_seeds_geo(): void
    {
        $company = $this->signupAndVerify(
            $this->basePayload('agence@newtech.dz') + ['solution' => 'travelagency']
        );

        $company->refresh();

        $this->assertTrue(
            $company->hasFeature('travelagency'),
            'Le flag companies.features.travelagency doit etre pose au provisioning.'
        );

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);

        // Le seed geo est la condition d'utilisabilite : sans pays ni villes,
        // l'agence ne peut creer ni gare ni trajet. On interroge la BASE
        // (tenant-scoped) et non un retour de methode.
        $countries = DB::table('travel_countries')
            ->where('company_id', $company->id)
            ->count();
        $cities = DB::table('travel_cities')
            ->where('company_id', $company->id)
            ->count();

        $this->assertGreaterThan(
            0,
            $countries,
            'Le referentiel pays doit etre seede a l\'activation (sinon le workspace est active mais inutilisable).'
        );
        $this->assertGreaterThan(
            0,
            $cities,
            'Le referentiel villes doit etre seede a l\'activation.'
        );
    }

    public function test_signup_without_solution_leaves_travelagency_inactive(): void
    {
        $company = $this->signupAndVerify($this->basePayload('plain-travel@newtech.dz'));

        $company->refresh();

        $this->assertFalse($company->hasFeature('travelagency'));
        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }
}
