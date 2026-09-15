<?php

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Modules\Billing\Application\Services\HorizontalToolSelection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7423 — PLANCHER d'accès garanti.
 *
 * Un indépendant (`solo`) n'avait accès à AUCUN outil RH : le verrou d'équipe
 * (#7235, `Company::TEAM_TOOLS` / `SOLO_HIDDEN_MODULE_KEYS`) couvrait les sept
 * clés du catalogue, donc un menu RH vide. Le plancher arbitré par l'issue
 * (`Company::SOLO_FLOOR_MODULES` : pointage, congés, paie) est désormais
 * garanti quel que soit le profil ET quelle que soit la sélection :
 *
 *  - côté PROVISIONING (`HorizontalToolSelection::resolve`) : le plancher est
 *    réactivé après le verrou d'équipe ;
 *  - côté LECTURE (`Company::moduleSelection`, consommé par `/auth/me`) : les
 *    tenants `solo` provisionnés AVANT le correctif portent une sélection où
 *    le plancher est à `false` — il est forcé à `true` pour les rattraper ;
 *  - un tenant `company` reste strictement inchangé ;
 *  - l'inscription self-service persiste réellement `metadata.company_type` ET
 *    `metadata.modules` (vérifié en base, pas sur la réponse HTTP).
 */
class SoloModuleFloorTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Accept-Language', 'fr');
    }

    /**
     * Outils d'équipe hors plancher : ils restent verrouillés pour un solo.
     *
     * @return list<string>
     */
    private function nonFloorTeamTools(): array
    {
        return array_values(array_diff(Company::TEAM_TOOLS, Company::SOLO_FLOOR_MODULES));
    }

    public function test_solo_provisioning_selection_always_carries_the_floor(): void
    {
        $selection = app(HorizontalToolSelection::class)
            ->resolve(['accounting', 'reports'], Company::TYPE_SOLO);

        $this->assertIsArray($selection);

        // Le plancher n'a PAS été demandé : il est pourtant là.
        foreach (Company::SOLO_FLOOR_MODULES as $tool) {
            $this->assertTrue($selection[$tool], "L'outil {$tool} doit être garanti au profil solo (#7423).");
        }

        // Les autres outils d'équipe restent désactivés.
        foreach ($this->nonFloorTeamTools() as $tool) {
            $this->assertFalse($selection[$tool], "L'outil {$tool} reste hors plancher pour un profil solo.");
        }

        // La sélection déclarée reste honorée.
        $this->assertTrue($selection['accounting']);
        $this->assertTrue($selection['reports']);
    }

    public function test_company_provisioning_selection_is_not_forced_to_the_floor(): void
    {
        $selection = app(HorizontalToolSelection::class)
            ->resolve(['accounting'], Company::TYPE_COMPANY);

        $this->assertIsArray($selection);

        // Un tenant `company` garde la sélection qu'il a faite : aucune
        // non-régression du contrat #7235 (« la sélection fait autorité »).
        foreach (Company::SOLO_FLOOR_MODULES as $tool) {
            $this->assertFalse($selection[$tool], "Le plancher solo ne doit pas s'appliquer à un profil company.");
        }
    }

    public function test_already_provisioned_solo_tenant_selection_keeps_the_floor(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Solo Rattrapage',
            'slug' => 'solo-rattrapage',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'trial',
            'metadata' => [
                'company_type' => Company::TYPE_SOLO,
                // Sélection persistée AVANT #7423 : tout l'équipe à false.
                'modules' => [
                    'employees' => false,
                    'attendance' => false,
                    'absences' => false,
                    'contracts' => false,
                    'payroll' => false,
                    'training' => false,
                    'accounting' => true,
                ],
            ],
        ]);

        $selection = $company->moduleSelection();

        $this->assertIsArray($selection);

        foreach (Company::SOLO_FLOOR_MODULES as $tool) {
            $this->assertTrue($selection[$tool], "Le plancher {$tool} doit rattraper un solo déjà provisionné (#7423).");
        }

        foreach ($this->nonFloorTeamTools() as $tool) {
            $this->assertFalse($selection[$tool], "L'outil {$tool} reste verrouillé pour un solo.");
        }

        $this->assertTrue($selection['accounting']);
    }

    public function test_legacy_solo_tenant_without_selection_stays_unlocked(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'solo-sans-selection',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'metadata' => ['company_type' => Company::TYPE_SOLO],
        ]);

        // Contrat #7235 préservé : pas de sélection déclarée ⇒ null, donc
        // aucun verrouillage rétroactif (le front garde son comportement
        // historique, le plancher y étant de toute façon appliqué).
        $this->assertNull($company->moduleSelection());
    }

    public function test_auth_me_exposes_the_solo_floor_modules(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Indépendant Compta',
            'slug' => 'independant-compta',
            'sector' => 'Services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'solo-me@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'trial',
            'metadata' => [
                'company_type' => Company::TYPE_SOLO,
                // Sélection hostile : le solo a décoché son propre socle.
                'modules' => [
                    'employees' => false,
                    'attendance' => false,
                    'absences' => false,
                    'contracts' => false,
                    'payroll' => false,
                    'training' => false,
                    'reports' => true,
                ],
            ],
        ]);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Solo',
            'last_name' => 'Manager',
            'email' => 'solo-me@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.company.type', Company::TYPE_SOLO);

        foreach (Company::SOLO_FLOOR_MODULES as $tool) {
            $response->assertJsonPath("data.company.modules.{$tool}", true);
        }

        foreach ($this->nonFloorTeamTools() as $tool) {
            $response->assertJsonPath("data.company.modules.{$tool}", false);
        }
    }

    public function test_auth_me_does_not_change_a_company_tenant_selection(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'Entreprise Classique',
            'slug' => 'entreprise-classique',
            'sector' => 'Commerce',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'company-me@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'metadata' => [
                'company_type' => Company::TYPE_COMPANY,
                'modules' => [
                    'employees' => true,
                    'attendance' => false,
                    'absences' => true,
                    'contracts' => true,
                    'payroll' => false,
                    'training' => false,
                    'accounting' => true,
                ],
            ],
        ]);

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Company',
            'last_name' => 'Manager',
            'email' => 'company-me@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonPath('data.company.type', Company::TYPE_COMPANY);

        // Non-régression : la sélection d'une entreprise n'est jamais complétée
        // par le plancher solo.
        $response->assertJsonPath('data.company.modules.attendance', false);
        $response->assertJsonPath('data.company.modules.payroll', false);
        $response->assertJsonPath('data.company.modules.employees', true);
        $response->assertJsonPath('data.company.modules.absences', true);
    }

    public function test_self_service_solo_signup_persists_profile_and_modules_in_database(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/trial/signup', [
            'email' => 'selfservice-solo@newtech.dz',
            'company' => 'Self Service Solo',
            'role' => 'founder',
            'employees' => '1-10',
            'country' => 'DZ',
            'company_type' => 'solo',
            'modules' => ['accounting', 'reports'],
        ])->assertStatus(200);

        $companyRequest = CompanyRequest::where('email', 'selfservice-solo@newtech.dz')
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($companyRequest);
        $otp = $companyRequest->verification_token;

        $this->postJson('/api/v1/trial/verify', [
            'email' => 'selfservice-solo@newtech.dz',
            'code' => $otp,
        ])->assertStatus(201);

        // Vérifié EN BASE (pas sur la réponse HTTP) : le profil ET la sélection
        // demandés à l'inscription sont bien persistés.
        $company = Company::query()->where('email', 'selfservice-solo@newtech.dz')->first();

        $this->assertNotNull($company);
        $this->assertSame(Company::TYPE_SOLO, $company->companyType());
        $this->assertTrue($company->isSolo());

        $modules = $company->metadata['modules'] ?? null;

        $this->assertIsArray($modules, 'metadata.modules doit être persisté en base.');
        $this->assertTrue($modules['accounting']);
        $this->assertTrue($modules['reports']);
        $this->assertFalse($modules['marketing']);

        // Le plancher est persisté à l'écriture, pas seulement rattrapé à la
        // lecture.
        foreach (Company::SOLO_FLOOR_MODULES as $tool) {
            $this->assertTrue($modules[$tool], "Le plancher {$tool} doit être persisté pour un solo self-service.");
        }

        foreach ($this->nonFloorTeamTools() as $tool) {
            $this->assertFalse($modules[$tool], "L'outil {$tool} reste verrouillé pour un solo self-service.");
        }
    }
}
