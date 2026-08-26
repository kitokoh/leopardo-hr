<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Core\Auth\Domain\Models\User;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Billing\Domain\Models\Partner;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * Issue #5585 — les tests Growth doivent être DÉTERMINISTES : chaque
 * endpoint a un contrat réel unique (plus de `assertContains($status,
 * [200, 403, 404])` qui passait presque quoi qu'il arrive).
 *
 * Contrats réels (routes/modules/growth.php + contrôleurs) :
 *   - GET  /growth/partner/dashboard : 401 sans auth · 404 sans partenaire
 *     (NOT_A_PARTNER) · 200 avec partenaire (referral_code/stats/…)
 *   - GET  /growth/partner/stats     : 401 sans auth · 403 sans partenaire
 *     (NOT_A_PARTNER) · 200 avec partenaire
 *   - POST /growth/partner/apply     : 401 sans auth · 422 payload invalide
 *     · 201 succès · 400 doublon (ALREADY_EXISTS)
 *   - GET  /platform/growth/partners : 401 sans token super_admin (y compris
 *     token employé) · 200 pour un super admin (data + meta)
 */
class GrowthControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected Company $company;
    protected Company $otherCompany;
    protected Employee $manager;
    protected Employee $employee;
    protected Employee $otherManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create();
        $this->otherCompany = $otherCompany;

        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $this->employee = $employee;

        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $this->otherCompany->id]);
        $this->otherManager = $otherManager;
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /** @test */
    public function unauthenticated_cannot_access_growth(): void
    {
        $this->getJson('/api/v1/growth/partner/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/growth/partner/stats')->assertStatus(401);
        $this->postJson('/api/v1/growth/partner/apply', ['type' => 'individual'])->assertStatus(401);
        $this->getJson('/api/v1/platform/growth/partners')->assertStatus(401);
    }

    /** @test */
    public function partner_dashboard_returns_404_without_partner_record(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/growth/partner/dashboard')
            ->assertStatus(404)
            ->assertJsonPath('error', 'NOT_A_PARTNER');
    }

    /** @test */
    public function partner_stats_returns_403_without_partner_record(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/growth/partner/stats')
            ->assertStatus(403)
            ->assertJsonPath('error', 'NOT_A_PARTNER');
    }

    /** @test */
    public function partner_dashboard_returns_200_with_partner_record(): void
    {
        $this->createPartnerFor($this->manager);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/growth/partner/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'referral_code',
                'stats'   => ['total_conversions', 'total_earned', 'pending_approval', 'approved_upcoming'],
                'recent_commissions',
            ])
            ->assertJsonPath('stats.total_conversions', 0);
    }

    /** @test */
    public function cross_tenant_isolation_on_partner_data(): void
    {
        $partnerA = $this->createPartnerFor($this->manager);
        $codeB    = $this->createPartnerFor($this->otherManager)->referral_code;

        // Tenant A a une entreprise référencée → total_conversions=1 ; B n'en a
        // aucune. Si l'isolation fuitait, B verrait les conversions de A.
        Company::factory()->create(['referrer_partner_id' => $partnerA->id]);

        // otherManager (tenant B) ne doit voir QUE ses propres données.
        Sanctum::actingAs($this->otherManager);
        $otherResponse = $this->getJson('/api/v1/growth/partner/dashboard');
        $otherResponse->assertOk();
        $otherResponse->assertJsonPath('referral_code', $codeB);
        $this->assertNotSame($partnerA->referral_code, $otherResponse->json('referral_code'));
        $this->assertSame(0, $otherResponse->json('stats.total_conversions'));

        // manager (tenant A) voit ses données, pas celles de B.
        Sanctum::actingAs($this->manager);
        $thisResponse = $this->getJson('/api/v1/growth/partner/dashboard');
        $thisResponse->assertOk();
        $thisResponse->assertJsonPath('referral_code', $partnerA->referral_code);
        $this->assertNotSame($codeB, $thisResponse->json('referral_code'));
        $this->assertSame(1, $thisResponse->json('stats.total_conversions'));
    }

    /** @test */
    public function manager_cannot_access_admin_growth_routes(): void
    {
        // Le garde `auth:super_admin_api` rejette un token employé (401),
        // même manager : les routes plateforme sont réservées aux super admins.
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/platform/growth/partners')->assertStatus(401);
    }

    /** @test */
    public function super_admin_can_access_growth_admin_routes(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/growth/partners')
            ->assertOk()
            ->assertJsonStructure(['data' => [], 'meta' => ['total', 'current_page']]);
    }

    /** @test */
    public function apply_returns_422_for_invalid_payload(): void
    {
        Sanctum::actingAs($this->manager);

        // `type` est requis → 422 déterministe (pas de 403/404 au choix).
        $this->postJson('/api/v1/growth/partner/apply', [])
            ->assertStatus(422)
            ->assertJsonPath('error', 'VALIDATION_ERROR');
    }

    /** @test */
    public function apply_returns_400_for_duplicate_application(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/growth/partner/apply', ['type' => 'individual'])->assertStatus(201);

        // Seconde candidature → doublon (400 ALREADY_EXISTS, déterministe).
        $this->postJson('/api/v1/growth/partner/apply', ['type' => 'agency'])
            ->assertStatus(400)
            ->assertJsonPath('error', 'ALREADY_EXISTS');
    }

    private function makeSuperAdmin(): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name'  => 'Platform Admin',
            'email' => 'admin@leopardo-rh.com',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('admin')])->save();

        return $superAdmin;
    }

    /**
     * Crée le User global + le partenaire lié à l'email de l'employé
     * (même chemin que resolveGlobalUser()). Retourne le partenaire.
     */
    private function createPartnerFor(Employee $employee): Partner
    {
        $user = User::factory()->create(['email' => $employee->email]);

        return Partner::create([
            'user_id'       => $user->id,
            'referral_code' => 'P-'.uniqid(),
        ]);
    }
}
