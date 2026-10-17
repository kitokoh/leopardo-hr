<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Enums\PlatformRole;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\Platform\Infrastructure\Services\PlatformProvisioningService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8626 / #8627 (Lot 6) — Console superadmin du provisionnement.
 *
 * Ce qui est prouvé :
 *  1. la vue est REFUSÉE sans `companies.view` (403 explicite, pas 500) ;
 *  2. elle n'expose AUCUNE donnée métier client — un tenant rempli
 *     d'employés et de pointages ne produit que des faits de provisioning ;
 *  3. les états d'échec sont réels : tenant sans établissement = `blocked_no_site`,
 *     provisioning bloqué = `stalled`, provisioning en erreur = `failed` ;
 *  4. le catalogue de verticales est lu depuis les manifests (maturité
 *     réelle, pas une constante d'écran) ;
 *  5. un email de diagnostic est MASQUÉ (minimisation RGPD).
 */
class PlatformProvisioningApiTest extends TestCase
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

    public function test_un_super_admin_voit_les_etapes_et_les_blocages_de_provisionnement(): void
    {
        // Tenant A : établi (un site), onboarding démarré, un module actif.
        $healthy = Company::factory()->create([
            'features' => ['rh' => true],
            'market_type' => 'domestic',
            'trade_profile' => 'wholesaler',
        ]);
        $this->addSite($healthy->id);
        $this->addCompletedOnboardingStep($healthy->id);

        // Tenant B : né SANS établissement — il ne peut pas démarrer.
        $blocked = Company::factory()->create(['features' => ['rh' => true]]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/provisioning');

        $response->assertOk();

        $items = collect($response->json('tenants'))->keyBy('id');

        $this->assertSame('operational', $items[$healthy->id]['provisioning_step']);
        $this->assertTrue($items[$healthy->id]['has_site']);
        $this->assertSame(1, $items[$healthy->id]['sites']);
        // L'intention Trade déclarée à l'onboarding est visible côté plateforme.
        $this->assertSame('domestic', $items[$healthy->id]['market_type']);
        $this->assertSame('wholesaler', $items[$healthy->id]['trade_profile']);

        $this->assertSame('blocked_no_site', $items[$blocked->id]['provisioning_step']);
        $this->assertFalse($items[$blocked->id]['has_site']);

        $this->assertGreaterThanOrEqual(1, $response->json('summary.without_site'));
    }

    public function test_la_vue_est_refusee_sans_compte_plateforme(): void
    {
        // Tous les rôles plateforme portent `companies.view` (matrice #7553) :
        // le cas réellement discriminant est donc l'ABSENCE de compte
        // plateforme. La garde doit refuser — jamais rendre 200, jamais 500.
        $response = $this->getJson('/api/v1/platform/provisioning');

        $this->assertContains($response->status(), [401, 403], 'Un accès anonyme ne doit jamais rendre 200');
    }

    public function test_la_vue_est_ouverte_au_role_support_qui_porte_companies_view(): void
    {
        // Contre-épreuve : `support` porte bien `companies.view` (matrice #7553).
        // Un 403 ici révélerait une permission élargie par erreur.
        Sanctum::actingAs($this->superAdmin(PlatformRole::Support), ['*'], 'super_admin_api');

        $this->getJson('/api/v1/platform/provisioning')->assertOk();
    }

    public function test_aucune_donnee_metier_client_n_est_exposee(): void
    {
        $company = Company::factory()->create(['features' => ['rh' => true]]);
        $this->addSite($company->id);

        // Charge métier réelle côté client : elle ne doit PAS ressortir.
        // Passage par la factory réelle (l'insertion brute ne connaît pas les
        // colonnes non-null par défaut du schéma).
        Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Nadia',
            'last_name' => 'Secret',
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $payload = $this->getJson('/api/v1/platform/provisioning')->assertOk()->json();
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        // Un identifiant nominatif ou une adresse d'employé ne doit jamais
        // apparaître dans un écran de diagnostic de provisionnement.
        $this->assertStringNotContainsString('Nadia', $raw);
        $this->assertStringNotContainsString('nadia.secret@client.tld', $raw);
        $this->assertArrayNotHasKey('employees_active', $payload['tenants'][0] ?? []);
        $this->assertArrayNotHasKey('attendance_logs_30d', $payload['tenants'][0] ?? []);
    }

    public function test_un_provisioning_bloque_est_distingue_d_un_provisioning_en_cours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'UTC'));

        // La table publique n'existe pas dans la fixture MVP (elle survit aux
        // purges tenant, c'est justement son intérêt) : on la crée avec le
        // MÊME schéma que la migration de production.
        if (! Schema::hasTable('public.trial_provisionings')) {
            Schema::create('public.trial_provisionings', function ($table): void {
                $table->id();
                $table->string('email', 255)->index();
                $table->string('provisioning_token', 64)->unique();
                $table->string('status', 20)->default('pending');
                $table->uuid('company_id')->nullable();
                $table->string('login_url', 500)->nullable();
                $table->string('error', 500)->nullable();
                $table->timestampTz('provisioned_at')->nullable();
                $table->timestamps();
            });
        }

        DB::table('public.trial_provisionings')->delete();

        DB::table('public.trial_provisionings')->insert([
            [
                'email' => 'jean.dupont@example.com',
                'provisioning_token' => str_repeat('a', 32),
                'status' => 'pending',
                'error' => null,
                'login_url' => null,
                'created_at' => now()->subMinutes(PlatformProvisioningService::STALL_THRESHOLD_MINUTES + 5),
                'updated_at' => now()->subMinutes(PlatformProvisioningService::STALL_THRESHOLD_MINUTES + 5),
            ],
            [
                'email' => 'recent@example.com',
                'provisioning_token' => str_repeat('b', 32),
                'status' => 'pending',
                'error' => null,
                'login_url' => null,
                'created_at' => now()->subMinutes(2),
                'updated_at' => now()->subMinutes(2),
            ],
            [
                'email' => 'casse@example.com',
                'provisioning_token' => str_repeat('c', 32),
                'status' => 'failed',
                'error' => 'schema creation failed: relation "employees" already exists',
                'login_url' => null,
                'created_at' => now()->subMinutes(1),
                'updated_at' => now()->subMinutes(1),
            ],
        ]);

        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/provisioning')->assertOk();
        $failures = collect($response->json('failures'))->keyBy('reference');

        // Le provisioning TOUT RÉCENT n'est pas un incident : il progresse.
        $this->assertCount(2, $failures);

        $stalled = $failures['j***t@e***.com'] ?? null;
        $this->assertNotNull($stalled, 'Le provisioning bloqué doit apparaître, email masqué');
        $this->assertSame('stalled', $stalled['state']);

        $failed = $failures['c***e@e***.com'] ?? null;
        $this->assertNotNull($failed);
        $this->assertSame('failed', $failed['state']);
        $this->assertStringContainsString('already exists', (string) $failed['error']);

        $this->assertSame(1, $response->json('summary.stalled_provisionings'));

        // Aucune adresse en clair dans la charge utile.
        $this->assertStringNotContainsString('jean.dupont@example.com', json_encode($response->json(), JSON_THROW_ON_ERROR));

        Carbon::setTestNow();
    }

    public function test_le_catalogue_expose_la_maturite_reelle_des_verticales(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['*'], 'super_admin_api');

        $response = $this->getJson('/api/v1/platform/provisioning')->assertOk();

        $catalogue = collect($response->json('catalogue'));

        $this->assertGreaterThan(0, $catalogue->count(), 'Le catalogue des verticales ne doit pas être vide');
        // La maturité vient du manifest lui-même : elle doit être une valeur
        // du registre fermé, jamais une chaîne libre inventée par l'écran.
        foreach ($catalogue as $row) {
            $this->assertContains($row['maturity'], ['pilot', 'production', 'placeholder']);
            $this->assertNotEmpty($row['code']);
            $this->assertNotEmpty($row['industry']);
        }

        $this->assertSame($catalogue->count(), $response->json('summary.verticals'));
    }

    /**
     * Amorce une étape d'onboarding TERMINÉE pour ce tenant.
     *
     * `provisioning_step` distingue « établissement prêt » de « onboarding
     * démarré » : sans une étape réelle en base, le tenant est `site_ready`
     * et le test ne prouverait rien sur la progression.
     */
    private function addCompletedOnboardingStep(string $companyId): void
    {
        DB::table('onboarding_steps')->insert([
            'company_id' => $companyId,
            'step_key' => 'company_profile',
            'title' => 'Profil entreprise',
            'status' => 'completed',
            'completed_at' => now(),
            'order' => 1,
            'required' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ajoute un établissement via le MODÈLE réel `Site`.
     *
     * Passer par le modèle (et non par `DB::table('sites')`) garantit qu'on
     * écrit exactement ce que l'application écrit — c'est la seule façon
     * honnête de tester « ce tenant a-t-il un établissement ? ».
     */
    private function addSite(string $companyId): void
    {
        Site::query()->create([
            'company_id' => $companyId,
            'name' => 'Siège',
            'status' => Site::STATUS_ACTIVE,
        ]);
    }

    /**
     * `platform_role` est hors `$fillable` (garde #3597) : il faut le forcer.
     * Les permissions ne sont pas un tableau libre — elles dérivent du rôle
     * (matrice #7553), donc le test paramètre le RÔLE, pas une liste.
     */
    private function superAdmin(PlatformRole $role = PlatformRole::SuperAdmin): SuperAdmin
    {
        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => fake()->unique()->safeEmail(),
        ]);
        $superAdmin->forceFill([
            'password_hash' => Hash::make('password123'),
            'platform_role' => $role->value,
        ])->save();

        return $superAdmin;
    }
}
