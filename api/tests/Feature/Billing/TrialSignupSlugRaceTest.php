<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Core\Tenant\TenantManager;
use App\Modules\Billing\Application\Actions\RequestTrialSignup;
use App\Modules\Billing\Application\Actions\VerifyTrialSignup;
use App\Modules\Billing\Infrastructure\Services\PartnerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #3895 (audit 360° 2026-08-15) — collision de slug entre deux signups trial
 * simultanés : resolveUniqueSlug() (while-exists) n'est pas sérialisé, la
 * violation d'unicité companies.slug (23505) doit déclencher un retry borné
 * avec un nouveau candidat au lieu d'un 500.
 */
class TrialSignupSlugRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * Sous-classe qui simule la course : le premier appel de resolveUniqueSlug
     * retourne un slug DÉJÀ PRIS (comme si l'exists() de l'autre requête
     * concurrente n'avait pas encore vu l'insertion), puis un candidat libre.
     */
    private function actionWithCollision(): VerifyTrialSignup
    {
        return new class(app(TenantManager::class), app(PartnerService::class), app(RequestTrialSignup::class)) extends VerifyTrialSignup
        {
            public int $calls = 0;

            protected function resolveUniqueSlug(string $baseSlug): string
            {
                $this->calls++;

                return $this->calls === 1 ? 'collision-co' : 'collision-co-1';
            }
        };
    }

    public function test_slug_collision_triggers_bounded_retry_instead_of_500(): void
    {
        Mail::fake();
        DB::statement('SET search_path TO public');

        // Le slug « collision-co » est déjà pris (autre signup concurrent).
        Company::create([
            'name' => 'Collision Co',
            'slug' => 'collision-co',
            'sector' => 'RH',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'owner@collision.dz',
            'plan_id' => 1,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'trial',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addDays(14)->toDateString(),
            'language' => 'fr',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ]);

        $request = CompanyRequest::create([
            'email' => 'founder@collision.dz',
            'company_name' => 'Collision Co',
            'status' => 'pending',
            'verification_token' => '123456',
            'verification_expires_at' => now()->addMinutes(30),
            'sector' => 'RH',
            'country' => 'DZ',
            'city' => 'Alger',
            'employees_range' => '11-50',
            'signup_payload' => [
                'country' => 'DZ',
                'role' => 'founder',
                'employees' => '11-50',
                'phone' => '+213550000000',
            ],
        ]);

        $action = $this->actionWithCollision();
        $result = $action->execute('founder@collision.dz', '123456');

        $this->assertTrue($result['success'], 'Le signup aurait dû réussir après retry.');
        $this->assertSame('collision-co-1', $result['company']->slug);
        $this->assertGreaterThanOrEqual(2, $action->calls, 'Le retry sur collision 23505 aurait dû être déclenché.');

        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame($result['company']->id, $request->fresh()->approved_company_id);
    }
}
