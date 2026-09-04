<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * RESTO-702 (#6215) — Export CSV idempotent + URL signée éphémère.
 *
 * Couvre le contenu déterministe (rejouer = même fichier, flag `reused`),
 * la génération d'URL signée et le refus d'une signature invalide/expirée.
 */
class RestaurantExportTest extends TestCase
{
    use RefreshTenantDatabase;

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function activateRestaurant(Company $company): void
    {
        $company->setFeature('restaurantmanager', true);
        $company->save();
    }

    public function test_export_is_idempotent_and_signed_url_downloads(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateRestaurant($company);
        $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function (): void {
            $branch = RestaurantBranch::factory()->create();
            RestaurantOrder::factory()->create(['branch_id' => $branch->id, 'status' => 'paid', 'total_minor' => 12000]);

            $first = $this->postJson('/api/v1/restaurant/reports/export', [
                'report_type' => 'sales',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])->assertOk()
                ->assertJsonPath('data.reused', false)
                ->json('data');

            $this->assertStringStartsWith('http', $first['signed_url']);
            $this->assertStringContainsString('signature=', $first['signed_url']);

            // Rejeu : même export (idempotence), même export_id, reused=true.
            $second = $this->postJson('/api/v1/restaurant/reports/export', [
                'report_type' => 'sales',
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ])->assertOk()
                ->assertJsonPath('data.reused', true)
                ->json('data');

            $this->assertSame($first['export_id'], $second['export_id']);

            // Téléchargement signé → 200 + contenu CSV.
            $this->get($first['signed_url'])
                ->assertOk()
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertSee('revenue_minor');

            // Signature invalide → 403 (middleware signed).
            $this->get('/api/v1/restaurant/reports/export/'.$first['export_id'])
                ->assertStatus(403);
        });
    }
}
