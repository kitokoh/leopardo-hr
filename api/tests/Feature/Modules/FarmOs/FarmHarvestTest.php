<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType;
use App\Modules\FarmOs\Domain\Exceptions\FarmHarvestException;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Services\FarmHarvestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T3.2 : coherence RECOLTE / STOCK / VENTE.
 *
 * Ces tests exercent le service metier sur un schema tenant. Ils exigent
 * PostgreSQL et ne tournent donc qu'en CI.
 *
 * INVARIANT VERIFIE ICI : une meme quantite n'est JAMAIS comptabilisee deux
 * fois. Le rapprochement `dispatched + remaining == harvested` est verifie apres
 * chaque scenario, et une vente ne peut pas depasser le stock restant.
 */
final class FarmHarvestTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);
    }

    public function test_lot_starts_with_the_full_harvested_quantity_available(): void
    {
        $lot = $this->makeLot('100.000');

        $this->assertSame('100.000', $lot->quantity_harvested);
        $this->assertSame('100.000', $lot->quantity_remaining);
        $this->assertSame('open', $lot->status);
    }

    public function test_harvested_quantity_is_never_updated_by_a_movement(): void
    {
        $lot = $this->makeLot('100.000');

        app(FarmHarvestService::class)->recordMovement(
            $this->company,
            $lot,
            FarmStorageMovementType::Loss,
            '10.000',
        );

        // La reference de tracabilite reste FIGEE.
        $this->assertSame('100.000', $lot->refresh()->quantity_harvested);
        $this->assertSame('90.000', $lot->quantity_remaining);
    }

    public function test_sale_decreases_remaining_and_requires_a_sale_document(): void
    {
        $lot = $this->makeLot('100.000');

        $movement = app(FarmHarvestService::class)->recordMovement(
            $this->company,
            $lot,
            FarmStorageMovementType::Sale,
            '40.000',
            null,
            null,
            555,
        );

        $this->assertSame('40.000', $movement->quantity);
        $this->assertSame('60.000', $movement->quantity_after);
        // Reference vers le service de VENTES EXISTANT.
        $this->assertSame(555, $movement->sale_document_id);
        $this->assertSame('60.000', $lot->refresh()->quantity_remaining);
    }

    /**
     * Farm OS ne fabrique JAMAIS de document commercial : une vente sans
     * reference au service de ventes existant est refusee.
     */
    public function test_sale_without_a_sale_document_is_refused(): void
    {
        $lot = $this->makeLot('100.000');

        $this->expectException(FarmHarvestException::class);

        app(FarmHarvestService::class)->recordMovement(
            $this->company,
            $lot,
            FarmStorageMovementType::Sale,
            '10.000',
        );
    }

    /**
     * GARDE ANTI-DOUBLE-COMPTAGE : on ne peut pas vendre plus que le stock
     * restant. Sans cette garde, la meme recolte serait vendue deux fois et le
     * chiffre d'affaires serait fictif.
     */
    public function test_sale_cannot_exceed_the_remaining_stock(): void
    {
        $lot = $this->makeLot('100.000');
        $service = app(FarmHarvestService::class);

        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '60.000', null, null, 1);

        $this->expectException(FarmHarvestException::class);

        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '50.000', null, null, 2);
    }

    public function test_loss_cannot_exceed_the_remaining_stock(): void
    {
        $lot = $this->makeLot('10.000');

        $this->expectException(FarmHarvestException::class);

        app(FarmHarvestService::class)->recordMovement($this->company, $lot, FarmStorageMovementType::Loss, '11.000');
    }

    /**
     * Un TRANSFERT ne retire rien du lot : la marchandise change d'emplacement.
     * S'il retirait du stock, un simple demenagement ferait disparaitre de la
     * marchandise et casserait le rapprochement.
     */
    public function test_transfer_does_not_change_the_remaining_quantity(): void
    {
        $lot = $this->makeLot('100.000');

        $movement = app(FarmHarvestService::class)->recordMovement(
            $this->company,
            $lot,
            FarmStorageMovementType::Transfer,
            '100.000',
            1,
            2,
        );

        $this->assertSame('100.000', $movement->quantity_after);
        $this->assertSame('100.000', $lot->refresh()->quantity_remaining);
        // L'emplacement courant du lot suit le transfert.
        $this->assertSame(2, $lot->storage_location_id);
    }

    public function test_an_inbound_movement_cannot_exceed_the_harvested_quantity(): void
    {
        $lot = $this->makeLot('100.000');
        $service = app(FarmHarvestService::class);

        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Loss, '30.000');

        // 70 restants + 40 = 110 > 100 recoltes : refuse.
        $this->expectException(FarmHarvestException::class);

        $service->recordMovement($this->company, $lot, FarmStorageMovementType::In, '40.000');
    }

    public function test_an_emptied_lot_is_closed_but_never_deleted(): void
    {
        $lot = $this->makeLot('50.000');

        app(FarmHarvestService::class)->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '50.000', null, null, 9);

        $lot->refresh();
        $this->assertSame('0.000', $lot->quantity_remaining);
        $this->assertSame('closed', $lot->status);
        // La tracabilite reste : le lot existe toujours.
        $this->assertSame(1, DB::table('farm_harvest_lots')->where('company_id', $this->company->id)->count());
    }

    /**
     * IDEMPOTENCE CRITIQUE : le rejeu d'une ecriture hors ligne ne doit JAMAIS
     * re-appliquer la sortie de stock — sinon une vente saisie deux fois
     * sortirait deux fois la meme marchandise.
     */
    public function test_movement_recording_is_idempotent_on_client_id(): void
    {
        $lot = $this->makeLot('100.000');
        $service = app(FarmHarvestService::class);

        $first = $service->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '25.000', null, null, 7, null, null, null, null, null, null, 'offline-sale-1');
        $second = $service->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '25.000', null, null, 7, null, null, null, null, null, null, 'offline-sale-1');

        $this->assertSame($first->id, $second->id);
        // Le stock n'a ete decremente QU'UNE fois.
        $this->assertSame('75.000', $lot->refresh()->quantity_remaining);
        $this->assertSame(1, DB::table('farm_storage_movements')->where('company_id', $this->company->id)->where('client_movement_id', 'offline-sale-1')->count());
    }

    /**
     * LE TEST DE COHERENCE CENTRAL : apres un scenario mixte (vente, perte,
     * transfert, consommation), la somme des sorties plus le restant doit egaler
     * exactement la quantite recoltee. C'est la preuve qu'aucune quantite n'est
     * comptee deux fois entre recolte, stock et vente.
     */
    public function test_reconciliation_proves_no_double_counting(): void
    {
        $lot = $this->makeLot('1000.000');
        $service = app(FarmHarvestService::class);

        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Sale, '300.000', null, null, 11);
        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Loss, '50.000');
        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Consumption, '20.000');
        // Un transfert ne doit PAS apparaitre dans les sorties.
        $service->recordMovement($this->company, $lot, FarmStorageMovementType::Transfer, '630.000', 1, 2);

        $reconciliation = $service->reconcile($this->company, $lot->refresh());

        $this->assertSame('1000.000', $reconciliation['harvested']);
        $this->assertSame('630.000', $reconciliation['remaining']);
        $this->assertSame('370.000', $reconciliation['dispatched']);
        $this->assertSame('300.000', $reconciliation['by_type']['sale']);
        $this->assertSame('50.000', $reconciliation['by_type']['loss']);
        $this->assertSame('20.000', $reconciliation['by_type']['consumption']);
        // Le transfert n'est pas une sortie.
        $this->assertArrayNotHasKey('transfer', $reconciliation['by_type']);
        $this->assertTrue($reconciliation['is_consistent']);
    }

    public function test_remaining_by_crop_aggregates_open_lots_only(): void
    {
        $open = $this->makeLot('100.000', ['crop_name' => 'wheat', 'unit' => 't']);
        $this->makeLot('50.000', ['crop_name' => 'wheat', 'unit' => 't']);

        // Un lot epuise ne compte plus dans le stock restant.
        app(FarmHarvestService::class)->recordMovement($this->company, $open, FarmStorageMovementType::Sale, '100.000', null, null, 3);

        $rows = app(FarmHarvestService::class)->remainingByCrop($this->company);

        $this->assertCount(1, $rows);
        $this->assertSame('50.000', $rows[0]['remaining']);
        $this->assertSame(1, $rows[0]['lots']);
    }

    public function test_losses_are_reported_per_lot(): void
    {
        $lot = $this->makeLot('100.000', ['lot_code' => 'LOT-LOSS-1']);

        app(FarmHarvestService::class)->recordMovement($this->company, $lot, FarmStorageMovementType::Loss, '12.500');

        $losses = app(FarmHarvestService::class)->losses($this->company);

        $this->assertCount(1, $losses);
        $this->assertSame('LOT-LOSS-1', $losses[0]['lot_code']);
        $this->assertSame('12.500', $losses[0]['lost']);
    }

    public function test_lot_of_another_tenant_is_refused(): void
    {
        $other = Company::factory()->create();

        // Le trait BelongsToCompany ECASE `company_id` avec le tenant actif sur
        // tout `creating` Eloquent (#7646) : construire le modele en PHP ne
        // produit donc jamais une ligne appartenant a un autre tenant. Pour
        // eprouver VRAIMENT le controle d'appartenance du service, la ligne
        // etrangere est inseree en SQL brut puis relue cross-tenant.
        DB::table('farm_harvest_lots')->insert([
            'id' => 900001,
            'company_id' => $other->id,
            'lot_code' => 'FOREIGN-1',
            'quantity_harvested' => '10.000',
            'quantity_remaining' => '10.000',
            'unit' => 't',
            'status' => 'open',
            'harvested_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        /** @var FarmHarvestLot $foreign */
        $foreign = FarmHarvestLot::crossTenantForPlatformAdmin()->findOrFail(900001);

        $this->assertSame($other->id, $foreign->company_id);

        $this->expectException(FarmHarvestException::class);

        app(FarmHarvestService::class)->recordMovement($this->company, $foreign, FarmStorageMovementType::Loss, '1.000');
    }

    public function test_harvest_data_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        $this->makeLot('10.000');

        $this->assertSame(1, DB::table('farm_harvest_lots')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('farm_harvest_lots')->where('company_id', $other->id)->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeLot(string $quantity, array $attributes = []): FarmHarvestLot
    {
        return app(FarmHarvestService::class)->createLot(
            $this->company,
            $attributes['lot_code'] ?? 'LOT-'.random_int(10000, 99999),
            $quantity,
            $attributes['unit'] ?? 't',
            null,
            null,
            $attributes['crop_name'] ?? null,
        );
    }
}
