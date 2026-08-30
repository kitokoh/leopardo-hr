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
    use RefreshTenantDatabase;

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'manager',
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
        $this->manager($company);

        /** @var RestaurantBranch $branch */
        $branch = RestaurantBranch::factory()->create(['company_id' => $company->id]);

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
        $this->get($downloadUrl)
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/csv')
            ->assertSee('"date","orders_count","revenue_minor"', false)
            ->assertSee('"1","2500"', false);

        // Signature invalide → 403.
        $this->get($downloadUrl.'&signature=deadbeef')
            ->assertStatus(403);
    }
}
