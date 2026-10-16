<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Domain\Models\FuelShift;
use App\Modules\FuelStation\Domain\Models\FuelShiftAssignment;
use App\Modules\FuelStation\Domain\Models\FuelStation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * P1-d (audit FuelStation 2026-10-16) — l'historique financier et
 * opérationnel SURVIT à une suppression.
 *
 * Avant correctif, `employees` et `fuel_cash_sessions` étaient référencés en
 * `cascadeOnDelete()` par `fuel_sales`, `fuel_cash_sessions`,
 * `fuel_shift_assignments` et `fuel_cash_session_movements`. `Employee`
 * n'ayant pas de `SoftDeletes`, supprimer un employé SUPPRIMAIT ses ventes.
 * Ces tests échouent sur le code d'avant correctif (vérifié : 4 échecs sur 5,
 * la vente disparaît) et passent après l'application de
 * `2026_10_16_130000_9002_fuel_preserve_history_on_delete`.
 *
 * Politique vérifiée ici :
 *   - `fuel_sales.cash_session_id` (nullable) → ON DELETE SET NULL : la
 *     vente survit, le rattachement est rompu.
 *   - colonnes non nullables → ON DELETE RESTRICT : la suppression est
 *     REFUSÉE, jamais silencieusement propagée.
 *
 * NOTE D'IMPLÉMENTATION — savepoint obligatoire.
 * `RefreshTenantDatabase` (donc `RefreshDatabase`) exécute chaque test dans
 * une transaction. Sous PostgreSQL, une instruction qui viole une contrainte
 * AVORTE la transaction courante (`SQLSTATE 25P02`, « current transaction is
 * aborted ») et toutes les requêtes suivantes échouent — y compris les
 * assertions de comptage. Tenter la suppression interdite dans une
 * sous-transaction (SAVEPOINT) puis revenir au savepoint rend la transaction
 * utilisable ET prouve que la suppression a bien été rejetée.
 */
class FuelHistoryPreservationTest extends TestCase
{
    use RefreshTenantDatabase;

    /**
     * @return array{0:Company,1:Employee,2:FuelStation}
     */
    private function seedTenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        /** @var FuelStation $station */
        $station = FuelStation::query()->create([
            'company_id' => $company->id,
            'code' => 'ST-HIST',
            'name' => 'Station historique',
            'timezone' => 'UTC',
            'status' => FuelStation::STATUS_ACTIVE,
        ]);

        return [$company, $operator, $station];
    }

    /**
     * Tente la suppression dans un SAVEPOINT et affirme qu'elle est refusée.
     * Le savepoint est TOUJOURS annulé (`rollBack`) : sans cela la transaction
     * de test resterait avortée et polluerait les assertions suivantes.
     */
    private function assertDeletionRefused(string $table, int|string $id, string $message): void
    {
        $level = DB::transactionLevel();

        DB::beginTransaction();

        $refused = false;

        try {
            DB::table($table)->where('id', $id)->delete();
        } catch (QueryException $e) {
            $refused = true;
        }

        DB::rollBack($level);

        $this->assertTrue($refused, $message);
    }

    /**
     * Supprimer la session de caisse NE DOIT PAS effacer les ventes : la vente
     * reste au registre, seule la référence de session est mise à NULL.
     *
     * C'est le test qui ÉCHOUAIT avant correctif (« Failed asserting that a
     * row in the table [fuel_sales] matches the attributes »).
     */
    public function test_deleting_cash_session_keeps_sales_and_nulls_the_link(): void
    {
        [$company, $operator, $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        /** @var int $sessionId */
        $sessionId = $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 1000])
            ->assertStatus(201)
            ->json('data.id');

        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Gazole',
            'quantity' => 20,
            'unit_price' => 140,
            'cash_session_id' => $sessionId,
        ])->assertStatus(200);

        /** @var FuelSale $sale */
        $sale = FuelSale::query()->firstOrFail();
        $this->assertSame($sessionId, $sale->cash_session_id);

        // Purge physique de la session (scénario « correction » qui, avant
        // correctif, emportait la vente avec elle).
        DB::table('fuel_cash_session_movements')->where('session_id', $sessionId)->delete();
        DB::table('fuel_cash_sessions')->where('id', $sessionId)->delete();

        // La vente est TOUJOURS là — c'est l'assertion centrale.
        $this->assertDatabaseHas('fuel_sales', ['id' => $sale->id]);
        $this->assertSame(1, FuelSale::query()->count());

        /** @var FuelSale $survivor */
        $survivor = FuelSale::query()->firstOrFail();
        $this->assertNull($survivor->cash_session_id);
        // Le montant et l'opérateur restent traçables : rien n'a été blanchi.
        $this->assertSame($operator->id, $survivor->employee_id);
    }

    /**
     * Supprimer un employé qui a enregistré une vente est REFUSÉ, pas propagé.
     * Sur `fuel_sales.employee_id` (non nullable), blanchir l'opérateur d'une
     * pièce financière serait une perte de traçabilité : on échoue franchement.
     */
    public function test_deleting_employee_with_sales_is_refused(): void
    {
        [$company, $operator, $station] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'station_id' => $station->id,
            'product' => 'Essence',
            'quantity' => 10,
            'unit_price' => 150,
        ])->assertStatus(200);

        $this->assertSame(1, FuelSale::query()->count());

        $this->assertDeletionRefused(
            'employees',
            $operator->id,
            'La suppression d\'un employé référencé par une vente doit être refusée (RESTRICT), jamais propagée.'
        );

        // Et la vente est intacte après le refus.
        $this->assertSame(1, FuelSale::query()->count());
        $this->assertDatabaseHas('fuel_sales', ['employee_id' => $operator->id]);
    }

    /**
     * Même garantie pour la session de caisse : son ouvreur ne peut pas être
     * supprimé tant que la session existe.
     */
    public function test_deleting_employee_who_opened_cash_session_is_refused(): void
    {
        [$company, $operator] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/cash-sessions', ['opening_balance' => 250])
            ->assertStatus(201);

        $this->assertSame(1, FuelCashSession::query()->count());

        $this->assertDeletionRefused(
            'employees',
            $operator->id,
            'La suppression de l\'ouvreur d\'une session de caisse doit être refusée (RESTRICT).'
        );

        $this->assertSame(1, FuelCashSession::query()->count());
    }

    /**
     * Contrôle de non-régression : une vente SANS session de caisse reste
     * enregistrable, et son rattachement est bien NULL.
     */
    public function test_sale_without_cash_session_still_works(): void
    {
        [$company, $operator] = $this->seedTenant();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/sales', [
            'product' => 'Gazole',
            'quantity' => 3,
            'unit_price' => 140,
        ])->assertStatus(200);

        $this->assertSame(1, FuelSale::query()->count());
        $this->assertNull(FuelSale::query()->firstOrFail()->cash_session_id);
    }

    /**
     * Les affectations de poste protègent aussi l'employé référencé.
     */
    public function test_shift_assignment_protects_employee(): void
    {
        [$company, $operator, $station] = $this->seedTenant();

        /** @var FuelShift $shift */
        $shift = FuelShift::query()->create([
            'company_id' => $company->id,
            'station_id' => $station->id,
            'name' => 'Matin',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        FuelShiftAssignment::query()->create([
            'company_id' => $company->id,
            'shift_id' => $shift->id,
            'employee_id' => $operator->id,
            'assignment_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->assertSame(1, FuelShiftAssignment::query()->count());

        $this->assertDeletionRefused(
            'employees',
            $operator->id,
            'Un employé affecté à un poste ne doit pas pouvoir être supprimé (RESTRICT).'
        );

        $this->assertSame(1, FuelShiftAssignment::query()->count());
    }
}
