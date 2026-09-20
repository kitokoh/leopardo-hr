<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Solutions\Contracts\DemoDataKit;
use App\Core\Solutions\DemoDataRegistry;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7865 — jeu de données de démonstration à la demande du client.
 *
 * Contrat verrouillé :
 *  1. l'index liste UNE entrée par verticale ACTIVE du tenant (code,
 *     disponibilité d'un kit, statut allowlisté) — verticale inactive absente ;
 *  2. fail-closed : verticale inactive ou code inconnu → 422, aucune
 *     écriture ; verticale active SANS kit enregistré → 422 à l'import ;
 *  3. l'import exécute le kit, persiste `metadata.demo_data[code]` (statut,
 *     horodatage, acteur) et trace l'audit `demo_data.imported` ;
 *  4. rejouer l'import est un no-op qui NE rejoue PAS le seeder (compteur) ;
 *  5. `dismiss` est idempotent et sans effet sur un kit déjà importé ;
 *  6. RBAC : employé → 403 ; non authentifié → 401 ;
 *  7. les kits réels `restaurant`/`travelagency` sont bien enregistrés par
 *     les providers des modules (`DemoDataRegistry::has`).
 */
class DemoDataControllerTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @param  array<string, mixed>  $features
     * @param  array<string, mixed>  $metadata
     */
    private function company(array $features = [], array $metadata = []): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'features' => $features,
            'metadata' => $metadata,
        ]);

        return $company;
    }

    private function actingAsRole(Company $company, string $role, ?string $managerRole = null): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    /**
     * Kit factice à compteur : prouve que l'import n'exécute le seeder
     * qu'une seule fois (idempotence côté contrôleur, critère #7865) sans
     * rejouer le lourd seeder réel de la verticale.
     */
    private function registerCountingKit(string $code): CountingDemoDataKit
    {
        $kit = new CountingDemoDataKit;

        app(DemoDataRegistry::class)->register($code, static fn (): DemoDataKit => $kit);

        return $kit;
    }

    /**
     * Relecture qualifiée de `public.companies` (piège search_path documenté).
     *
     * @return array<string, mixed>
     */
    private function persistedMetadata(Company $company): array
    {
        $table = DB::getDriverName() === 'pgsql' ? 'public.companies' : 'companies';
        $row = DB::table($table)->where('id', $company->id)->first();

        $decoded = json_decode((string) ($row->metadata ?? '{}'), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function test_real_vertical_kits_are_registered(): void
    {
        $registry = app(DemoDataRegistry::class);

        $this->assertTrue($registry->has('restaurant'));
        $this->assertTrue($registry->has('travelagency'));
    }

    public function test_index_lists_one_entry_per_active_vertical(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'manager', 'principal');

        $response = $this->getJson('/api/v1/demo-data');

        // Une seule verticale active → une seule entrée (la verticale
        // INACTIVE `restaurant` n'est jamais listée).
        $response->assertOk()
            ->assertJsonCount(1, 'data.kits')
            ->assertJsonPath('data.kits.0.code', 'travelagency')
            ->assertJsonPath('data.kits.0.available', true)
            ->assertJsonPath('data.kits.0.status', 'not_imported')
            ->assertJsonPath('data.kits.0.imported_at', null)
            ->assertJsonPath('data.kits.0.dismissed_at', null);
    }

    public function test_import_rejected_when_vertical_inactive(): void
    {
        $company = $this->company();
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/demo-data/travelagency/import')->assertStatus(422);

        $this->assertArrayNotHasKey(
            'demo_data',
            $this->persistedMetadata($company),
            'Un import refusé ne doit produire AUCUNE écriture.'
        );
    }

    public function test_import_rejected_on_unknown_code(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->postJson('/api/v1/demo-data/pirate_kit/import')->assertStatus(422);
    }

    public function test_import_rejected_when_no_kit_registered_for_active_vertical(): void
    {
        // `fuel_station` est au catalogue mais aucun kit de démo n'est
        // enregistré : l'index l'affiche `available: false` et l'import
        // est refusé (fail-closed).
        $company = $this->company(['fuel_station' => true]);
        $this->actingAsRole($company, 'manager', 'principal');

        $this->getJson('/api/v1/demo-data')
            ->assertOk()
            ->assertJsonPath('data.kits.0.code', 'fuel_station')
            ->assertJsonPath('data.kits.0.available', false);

        $this->postJson('/api/v1/demo-data/fuel_station/import')->assertStatus(422);
    }

    public function test_import_runs_kit_persists_state_and_audits(): void
    {
        $company = $this->company(['travelagency' => true]);
        $actor = $this->actingAsRole($company, 'manager', 'principal');
        $kit = $this->registerCountingKit('travelagency');

        $response = $this->postJson('/api/v1/demo-data/travelagency/import');
        $response->assertOk()
            ->assertJsonPath('data.code', 'travelagency')
            ->assertJsonPath('data.status', 'imported');

        $this->assertSame(1, $kit->runs);

        // L'état vit côté SERVEUR (`public.companies.metadata.demo_data`).
        $persisted = (array) data_get($this->persistedMetadata($company), 'demo_data.travelagency', []);
        $this->assertSame('imported', $persisted['status'] ?? null);
        $this->assertSame($actor->id, $persisted['imported_by'] ?? null);
        $this->assertNotEmpty($persisted['imported_at'] ?? null);

        // Audit tracé (pattern `SolutionActivator`).
        $this->assertSame(
            1,
            AuditLog::query()
                ->where('action', 'demo_data.imported')
                ->where('company_id', $company->id)
                ->count()
        );
    }

    public function test_replaying_import_never_reruns_the_seeder(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'manager', 'principal');
        $kit = $this->registerCountingKit('travelagency');

        $first = $this->postJson('/api/v1/demo-data/travelagency/import');
        $first->assertOk()->assertJsonPath('data.status', 'imported');

        $second = $this->postJson('/api/v1/demo-data/travelagency/import');
        $second->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.imported_at', $first->json('data.imported_at'));

        // Idempotence : le seeder n'a tourné qu'UNE fois.
        $this->assertSame(1, $kit->runs);
    }

    public function test_dismiss_is_idempotent(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'manager', 'principal');

        $first = $this->postJson('/api/v1/demo-data/travelagency/dismiss');
        $first->assertOk()->assertJsonPath('data.status', 'dismissed');
        $this->assertNotEmpty($first->json('data.dismissed_at'));

        $second = $this->postJson('/api/v1/demo-data/travelagency/dismiss');
        $second->assertOk()
            ->assertJsonPath('data.status', 'dismissed')
            ->assertJsonPath('data.dismissed_at', $first->json('data.dismissed_at'));
    }

    public function test_dismiss_after_import_is_a_no_op(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'manager', 'principal');
        $this->registerCountingKit('travelagency');

        $this->postJson('/api/v1/demo-data/travelagency/import')
            ->assertOk()->assertJsonPath('data.status', 'imported');

        // Un « non merci » tardif ne masque pas des données déjà installées.
        $this->postJson('/api/v1/demo-data/travelagency/dismiss')
            ->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.dismissed_at', null);
    }

    public function test_rbac_employee_forbidden(): void
    {
        $company = $this->company(['travelagency' => true]);
        $this->actingAsRole($company, 'employee');

        $this->getJson('/api/v1/demo-data')->assertStatus(403);
        $this->postJson('/api/v1/demo-data/travelagency/import')->assertStatus(403);
        $this->postJson('/api/v1/demo-data/travelagency/dismiss')->assertStatus(403);
    }

    public function test_guest_is_rejected(): void
    {
        $this->getJson('/api/v1/demo-data')->assertUnauthorized();
        $this->postJson('/api/v1/demo-data/travelagency/import')->assertUnauthorized();
        $this->postJson('/api/v1/demo-data/travelagency/dismiss')->assertUnauthorized();
    }
}

/**
 * Kit factice à compteur (#7865) : prouve que rejouer l'import n'exécute
 * jamais le seeder deux fois (pattern classe dédiée en fin de fichier,
 * cf. `ScriptedReplyLlm` / CommunicationReplyTest).
 */
final class CountingDemoDataKit implements DemoDataKit
{
    public int $runs = 0;

    public function seed(Company $company): void
    {
        $this->runs++;
    }
}
