<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmInputCategory;
use App\Modules\FarmOs\Domain\Enums\FarmInputUnit;
use App\Modules\FarmOs\Domain\Models\FarmInput;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9678 — `FarmDashboardController::inputQuantity()` lisait
 * `farm_inputs.quantity_on_hand`, colonne inexistante (SQLSTATE[42703]).
 *
 * Le stock réel d'un intrant est la somme des quantités de ses lots
 * (`farm_input_lots.quantity`). Ce test vérifie que le dashboard répond 200
 * et que la quantité affichée provient bien des lots.
 */
final class FarmDashboardInputQuantityTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create();
        $company->setFeature('farm_os', true);
        $company->save();
        $this->company = $company;

        app()->instance('current_company', $company);

        // La section `inputs_to_reorder` n'apparaît que si la capacité
        // `stocks_inputs` est explicitement activée (FarmCapabilityGate).
        DB::table('farm_capabilities')->insert([
            'company_id' => $company->id,
            'capability' => FarmCapability::StocksInputs->value,
            'enabled' => true,
            'source' => 'settings',
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actingManager(): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    public function test_dashboard_does_not_reference_a_missing_quantity_on_hand_column(): void
    {
        $this->actingManager();

        /** @var FarmInput $input */
        $input = FarmInput::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Blé tendre — semence',
            'category' => FarmInputCategory::Seeds->value,
            'unit' => FarmInputUnit::Kilogram->value,
            // Un seuil d'alerte force le passage par inputQuantity().
            'alert_threshold' => '50.000',
        ]);

        // Deux lots : 20 + 15 = 35 kg, sous le seuil de 50 → doit apparaître
        // dans les intrants à réapprovisionner, avec la quantité agrégée.
        DB::table('farm_input_lots')->insert([
            [
                'company_id' => $this->company->id,
                'farm_input_id' => $input->id,
                'lot_number' => 'LOT-A',
                'quantity' => '20.000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $this->company->id,
                'farm_input_id' => $input->id,
                'lot_number' => 'LOT-B',
                'quantity' => '15.000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/v1/farm/dashboard')->assertOk();

        $items = $response->json('data.sections.inputs_to_reorder.items');

        $this->assertIsArray($items);
        $this->assertCount(1, $items, 'L\'intrant sous son seuil doit être listé.');
        $this->assertSame($input->id, $items[0]['input_id']);
        $this->assertSame('35.000', $items[0]['quantity'], 'La quantité doit être la somme des lots.');
    }
}
