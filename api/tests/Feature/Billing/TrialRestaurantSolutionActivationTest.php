<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #6693 (BC-25) — activation de la solution restaurant au provisioning
 * d'un tenant self-service (signup → OTP → verify).
 *
 * Couvre : signup avec `solution=restaurant` → la solution ET ses modules
 * requis sont actifs après le provisioning (audit `solution.activated`) ;
 * signup sans solution → aucun flag restaurant ; code inconnu → 422.
 */
class TrialRestaurantSolutionActivationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function signupAndVerify(array $signupPayload): Company
    {
        $this->postJson('/api/v1/trial/signup', $signupPayload)->assertStatus(200);

        $companyRequest = CompanyRequest::query()
            ->where('email', $signupPayload['email'])
            ->where('status', 'pending')
            ->firstOrFail();

        $this->postJson('/api/v1/trial/verify', [
            'email' => $signupPayload['email'],
            'code' => $companyRequest->verification_token,
        ])->assertStatus(200)->assertJson(['success' => true]);

        /** @var Company $company */
        $company = Company::query()->where('email', $signupPayload['email'])->firstOrFail();

        return $company;
    }

    private function basePayload(string $email): array
    {
        return [
            'email' => $email,
            'company' => 'Resto Test DZ',
            'role' => 'founder',
            'employees' => '11-50',
            'country' => 'DZ',
        ];
    }

    public function test_signup_with_restaurant_solution_activates_solution_and_required_modules(): void
    {
        $company = $this->signupAndVerify(
            $this->basePayload('resto@newtech.dz') + ['solution' => 'restaurant']
        );

        $company->refresh();

        $this->assertTrue($company->hasFeature('restaurant'));
        $this->assertTrue($company->hasFeature('attendance'));
        $this->assertTrue($company->hasFeature('documents'));
        $this->assertTrue($company->hasFeature('notifications'));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_signup_without_solution_leaves_restaurant_inactive(): void
    {
        $company = $this->signupAndVerify($this->basePayload('plain@newtech.dz'));

        $company->refresh();

        $this->assertFalse($company->hasFeature('restaurant'));
        $this->assertDatabaseMissing('audit_logs', [
            'company_id' => $company->id,
            'action' => 'solution.activated',
        ]);
    }

    public function test_signup_with_unknown_solution_is_rejected_422(): void
    {
        $this->postJson('/api/v1/trial/signup', $this->basePayload('unknown@newtech.dz') + [
            'solution' => 'solution_inexistante',
        ])->assertStatus(422)->assertJsonValidationErrors('solution');
    }
}
