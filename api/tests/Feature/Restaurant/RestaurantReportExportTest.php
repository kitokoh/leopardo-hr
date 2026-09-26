<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\Support\AssignsResourceAccess;
use Tests\TestCase;

/**
 * RESTO-702 (#6215) — Export CSV idempotent + URL signée.
 *
 * Couvre : contenu du CSV (colonnes allowlistées), idempotence (rejeu =
 * même fichier), téléchargement via l'URL signée éphémère et rejet d'une
 * signature invalide.
 */
class RestaurantReportExportTest extends TestCase
{
    use AssignsResourceAccess;
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
    {
        // #7599 — les rapports exigent le niveau `manage` sur au moins une
        // succursale (la valeur 'manager' de manager_role est morte côté
        // policies) ; l'assignation est posée par les tests sur la branche.
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('restaurantmanager', true);
        $company->save();

        return $company;
    }

    public function test_export_is_idempotent_and_downloadable_via_signed_url(): void
    {
        Storage::fake('local');

        $company = $this->company();
        $manager = $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);
        $this->assignResourceAccess($manager, 'restaurant_branch', $branch->id, 'manage');

        RestaurantOrder::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'status' => 'paid',
            'total_minor' => 2500,
            'currency' => 'XAF',
        ]);

        // Export 1.
        $first = $this->postJson('/api/v1/restaurant/reports/export', [
            'report_type' => 'sales',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ])->assertStatus(200)
            ->assertJsonPath('data.filename', 'restaurant_sales_'.now()->startOfMonth()->toDateString().'_'.now()->endOfMonth()->toDateString().'_all.csv');

        $filename = $first->json('data.filename');
        $downloadUrl = $first->json('data.download_url');

        // Rejeu : même fichier (idempotence).
        $second = $this->postJson('/api/v1/restaurant/reports/export', [
            'report_type' => 'sales',
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ])->assertStatus(200);

        $this->assertSame($filename, $second->json('data.filename'), 'Export rejouable = même fichier.');

        Storage::disk('local')->assertExists('restaurant/exports/'.$company->id.'/'.$filename);

        // Téléchargement via l'URL signée : CSV avec entête + ligne de ventes.
        // NB 1 : Symfony normalise tout Content-Type `text/*` en y ajoutant
        //        le charset (Response::prepare) — forme normalisée attendue.
        // NB 2 : le téléchargement est STREAMÉ (StreamedResponse) — le corps
        //        est asserté via l'API dédiée, pas assertSee (getContent vide).
        $download = $this->get($downloadUrl);
        $download->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $csv = $download->streamedContent();
        $this->assertStringContainsString('"date","orders_count","revenue_minor"', $csv);
        $this->assertStringContainsString('"1","2500"', $csv);

        // Signature invalide → 403.
        $this->get($downloadUrl.'&signature=deadbeef')
            ->assertStatus(403);
    }
}
