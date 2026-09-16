<?php

namespace Tests\Feature\Billing;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #7430 (BC-21 BILLING) — une offre tarifaire est PARAMÉTRABLE depuis l'admin,
 * sans déploiement.
 *
 * Constat : la table `plans` n'était alimentée que par `PlanSeeder`, et l'admin
 * n'exposait qu'une lecture (`GET /platform/plans`). Changer un prix, une
 * limite d'employés ou la matrice de features exigeait un déploiement.
 *
 * Contrat verrouillé ici :
 *   1. création (201) + persistance réelle en base + **audit** ;
 *   2. nom d'offre unique (422) ;
 *   3. édition partielle (prix, limite, features, activation) + audit ;
 *   4. duplication → copie **archivée** (jamais publiée par accident) ;
 *   5. archivage (`is_active` = false) ;
 *   6. **une offre utilisée ne se supprime pas** (409, aucune suppression) —
 *      ni par un tenant (`companies.plan_id`) ni par un abonnement
 *      (`subscriptions.plan`) ;
 *   7. une offre libre se supprime, et la suppression est auditée ;
 *   8. bornes de validation (prix négatif, essai > 365 j).
 */
class PlatformPlanAdminApiTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        // La fixture MVP est partielle : `subscriptions` (migration Billing)
        // n'y figure pas, alors que le refus de suppression d'une offre
        // utilisée la consulte (un abonnement référence le code de plan).
        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->string('plan', 50)->default('trial');
                $table->string('status', 30)->default('trial');
                $table->timestamps();
            });
        }

        $superAdmin = new SuperAdmin([
            'name' => 'Platform Admin',
            'email' => 'plans-admin@leopardo.test',
        ]);
        $superAdmin->forceFill(['password_hash' => Hash::make('password123')])->save();

        Sanctum::actingAs($superAdmin, ['*'], 'super_admin_api');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_creates_a_plan_and_it_is_persisted_and_audited(): void
    {
        $response = $this->postJson('/api/v1/platform/plans', [
            'name' => 'Business Plus',
            'price_monthly' => 199,
            'price_yearly' => 1990,
            'max_employees' => 300,
            'trial_days' => 30,
            'features' => ['rh' => true, 'payroll' => true, 'cameras' => false],
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Business Plus')
            ->assertJsonPath('data.price_monthly', 199)
            ->assertJsonPath('data.max_employees', 300)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.features.payroll', true);

        $id = (int) $response->json('data.id');

        // Le vrai contrat : la ligne existe en base, pas seulement dans la réponse.
        $row = DB::table('plans')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('Business Plus', $row->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'plan.created',
            'auditable_type' => 'plan',
            'auditable_id' => $id,
        ]);
    }

    public function test_plan_name_must_be_unique(): void
    {
        $this->insertPlan('Free');

        $this->postJson('/api/v1/platform/plans', [
            'name' => 'Free',
            'price_monthly' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(1, DB::table('plans')->where('name', 'Free')->count());
    }

    public function test_update_applies_partial_changes_and_is_audited(): void
    {
        $id = $this->insertPlan('Pilot', priceMonthly: 29, maxEmployees: 10, features: ['rh' => true]);

        $this->patchJson("/api/v1/platform/plans/{$id}", [
            'price_monthly' => 39,
            'max_employees' => null,
            'features' => ['rh' => true, 'accounting' => true],
        ])
            ->assertOk()
            ->assertJsonPath('data.price_monthly', 39)
            ->assertJsonPath('data.max_employees', null)
            ->assertJsonPath('data.features.accounting', true);

        // `->value()` plutôt que `->first()` : une colonne ciblée, jamais un
        // `stdClass|null` dont PHPStan strict refuse l'accès aux propriétés.
        $this->assertSame('39.00', (string) DB::table('plans')->where('id', $id)->value('price_monthly'));
        $this->assertNull(DB::table('plans')->where('id', $id)->value('max_employees'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'plan.updated',
            'auditable_id' => $id,
        ]);

        // Le journal porte l'avant/après (une modification de prix doit être
        // reconstructible, c'est le sens de l'audit demandé par l'issue).
        $log = AuditLog::query()->where('auditable_id', $id)->where('action', 'plan.updated')->firstOrFail();
        $this->assertSame(29.0, (float) ($log->old_values['price_monthly'] ?? 0));
        $this->assertSame(39.0, (float) ($log->new_values['price_monthly'] ?? 0));
    }

    public function test_update_renames_a_plan_but_refuses_a_taken_name(): void
    {
        $id = $this->insertPlan('Pilot');
        $this->insertPlan('Operations');

        $this->patchJson("/api/v1/platform/plans/{$id}", ['name' => 'Operations'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->patchJson("/api/v1/platform/plans/{$id}", ['name' => 'Pilot Plus'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pilot Plus');
    }

    public function test_duplicate_creates_an_archived_copy(): void
    {
        $id = $this->insertPlan('Operations', priceMonthly: 79, maxEmployees: 200, features: ['rrh' => true]);

        $response = $this->postJson("/api/v1/platform/plans/{$id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Operations (copie)')
            // Une copie ne doit JAMAIS apparaître publiée dans le tunnel.
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.price_monthly', 79)
            ->assertJsonPath('data.max_employees', 200);

        $this->assertNotSame($id, (int) $response->json('data.id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.duplicated']);
    }

    public function test_archive_deactivates_without_deleting(): void
    {
        $id = $this->insertPlan('Legacy Plan', isActive: true);

        $this->postJson("/api/v1/platform/plans/{$id}/archive")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, (int) DB::table('plans')->where('id', $id)->value('is_active'));
        $this->assertDatabaseHas('plans', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.archived']);
    }

    public function test_delete_is_refused_when_the_plan_is_used_by_a_company(): void
    {
        $id = $this->insertPlan('Operations');
        Company::factory()->create(['features' => ['rh' => true], 'plan_id' => $id]);

        $response = $this->deleteJson("/api/v1/platform/plans/{$id}");

        $response->assertStatus(409)->assertJsonPath('data.companies', 1);

        $this->assertDatabaseHas('plans', ['id' => $id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'plan.deleted']);
    }

    public function test_delete_is_refused_when_a_subscription_references_the_plan_code(): void
    {
        $id = $this->insertPlan('Pilot');

        DB::table('subscriptions')->insert([
            'company_id' => Company::factory()->create(['features' => ['rh' => true]])->id,
            'plan' => 'pilot',
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/v1/platform/plans/{$id}");

        $response->assertStatus(409)->assertJsonPath('data.subscriptions', 1);
        $this->assertDatabaseHas('plans', ['id' => $id]);
    }

    public function test_delete_removes_an_unused_plan_and_is_audited(): void
    {
        $id = $this->insertPlan('Dead Offer');

        $this->deleteJson("/api/v1/platform/plans/{$id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('plans', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.deleted', 'auditable_id' => $id]);
    }

    public function test_validation_bounds_reject_absurd_prices_and_trial_lengths(): void
    {
        $this->postJson('/api/v1/platform/plans', [
            'name' => 'Bad Offer',
            'price_monthly' => -10,
        ])->assertStatus(422)->assertJsonValidationErrors('price_monthly');

        $this->postJson('/api/v1/platform/plans', [
            'name' => 'Bad Trial',
            'trial_days' => 9999,
        ])->assertStatus(422)->assertJsonValidationErrors('trial_days');
    }

    /**
     * @param  array<string, bool>  $features
     */
    private function insertPlan(
        string $name,
        float $priceMonthly = 0,
        ?int $maxEmployees = null,
        array $features = ['rh' => true],
        bool $isActive = true,
    ): int {
        return (int) DB::table('plans')->insertGetId([
            'name' => $name,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceMonthly * 10,
            'max_employees' => $maxEmployees,
            'features' => json_encode($features),
            'trial_days' => 14,
            'is_active' => $isActive,
        ]);
    }
}
