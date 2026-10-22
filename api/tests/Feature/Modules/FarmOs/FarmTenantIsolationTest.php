<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Modules\FarmOs\Domain\Enums\FarmOfflineOperationType;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Modules\FarmOs\Domain\Exceptions\FarmFinanceException;
use App\Modules\FarmOs\Domain\Exceptions\FarmHarvestException;
use App\Modules\FarmOs\Domain\Exceptions\FarmLivestockException;
use App\Modules\FarmOs\Domain\Models\FarmHarvestLot;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Services\FarmHarvestService;
use App\Modules\FarmOs\Domain\Services\FarmLivestockService;
use App\Modules\FarmOs\Domain\Services\FarmProfitabilityService;
use App\Modules\FarmOs\Domain\Services\FarmSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T9 : isolation multi-tenant des modules recents.
 *
 * Regression gardee ici : sur les modules materiel, cheptel, recoltes, finance
 * et hors ligne, une ligne appartenant a un AUTRE tenant ne doit jamais etre
 * exploitable, meme quand l'identifiant est valide.
 *
 * POURQUOI L'INSERTION SQL BRUTE : `BelongsToCompany` ecrase `company_id` avec
 * le tenant actif a l'ecriture (#7646). Construire l'objet etranger via
 * Eloquent le rattacherait silencieusement au tenant courant, et le test ne
 * prouverait RIEN. La ligne est donc inseree en SQL brut puis relue via
 * `crossTenantForPlatformAdmin()`.
 *
 * Ces tests exigent PostgreSQL : ils ne tournent qu'en CI.
 */
final class FarmTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->other = Company::factory()->create();

        app()->instance('current_company', $this->company);
    }

    /**
     * RECOLTES — un lot de recolte d'un autre tenant ne peut pas recevoir de
     * mouvement : sans ce controle, un tenant pourrait consommer le stock
     * d'une autre exploitation.
     */
    public function test_a_harvest_lot_from_another_tenant_cannot_receive_a_movement(): void
    {
        DB::table('farm_harvest_lots')->insert([
            'id' => 900101,
            'company_id' => $this->other->id,
            'lot_code' => 'FOREIGN-LOT',
            'crop_name' => 'maize',
            'quantity_harvested' => '500.000',
            'unit' => 'kg',
            'quantity_remaining' => '500.000',
            'status' => 'open',
            'harvested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var FarmHarvestLot $foreign */
        $foreign = FarmHarvestLot::crossTenantForPlatformAdmin()->findOrFail(900101);

        $this->assertSame($this->other->id, $foreign->company_id);

        $this->expectException(FarmHarvestException::class);

        app(FarmHarvestService::class)->recordMovement(
            $this->company,
            $foreign,
            \App\Modules\FarmOs\Domain\Enums\FarmStorageMovementType::Loss,
            '10.000',
        );
    }

    /**
     * RECOLTES — les lots d'un tenant ne sont jamais comptes dans les
     * indicateurs d'un autre.
     */
    public function test_harvest_aggregates_are_isolated(): void
    {
        app(FarmHarvestService::class)->createLot(
            $this->company,
            'MINE-1',
            '120.000',
            'kg',
            null,
            null,
            'wheat',
        );

        DB::table('farm_harvest_lots')->insert([
            'id' => 900102,
            'company_id' => $this->other->id,
            'lot_code' => 'THEIRS-1',
            'crop_name' => 'wheat',
            'quantity_harvested' => '999.000',
            'unit' => 'kg',
            'quantity_remaining' => '999.000',
            'status' => 'open',
            'harvested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = app(FarmHarvestService::class)->remainingByCrop($this->company);

        $this->assertCount(1, $rows, 'Un seul lot doit etre agrege : celui du tenant courant.');
        $this->assertSame('wheat', $rows[0]['crop_name']);
        $this->assertSame('120.000', $rows[0]['remaining']);
    }

    /**
     * CHEPTEL — un lot d'animaux d'un autre tenant ne peut pas recevoir
     * d'evenement (mortalite, vente, naissance).
     */
    public function test_a_livestock_batch_from_another_tenant_is_refused(): void
    {
        DB::table('farm_livestock_batches')->insert([
            'id' => 900201,
            'company_id' => $this->other->id,
            'name' => 'foreign herd',
            'species' => FarmSpecies::Cattle->value,
            'head_count' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var FarmLivestockBatch $foreign */
        $foreign = FarmLivestockBatch::crossTenantForPlatformAdmin()->findOrFail(900201);

        $this->assertSame($this->other->id, $foreign->company_id);

        $this->expectException(FarmLivestockException::class);

        app(FarmLivestockService::class)->recordEvent(
            $this->company,
            FarmLivestockEventType::Death,
            1,
            $foreign,
        );
    }

    /**
     * CHEPTEL — les effectifs d'un tenant n'integrent jamais le cheptel d'un
     * autre, meme si les deux elevent la meme espece.
     */
    public function test_head_counts_are_isolated(): void
    {
        DB::table('farm_livestock_batches')->insert([
            'id' => 900202,
            'company_id' => $this->other->id,
            'name' => 'foreign herd',
            'species' => FarmSpecies::Cattle->value,
            'head_count' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counts = app(FarmLivestockService::class)->presentHeadCountBySpecies($this->company);

        $this->assertArrayNotHasKey(FarmSpecies::Cattle->value, $counts);
    }

    /**
     * FINANCE — une depense saisie par un tenant reste invisible pour l'autre,
     * et la rentabilite ne melange jamais deux exploitations.
     */
    public function test_expenses_are_isolated_between_tenants(): void
    {
        app(FarmProfitabilityService::class)->recordExpense(
            $this->company,
            FarmExpenseCategory::Seeds,
            'semences ble',
            '1500.00',
            'EUR',
            Carbon::now(),
        );

        $mine = DB::table('farm_expenses')->where('company_id', $this->company->id)->count();
        $theirs = DB::table('farm_expenses')->where('company_id', $this->other->id)->count();

        $this->assertSame(1, $mine);
        $this->assertSame(0, $theirs);
    }

    /**
     * FINANCE — un chiffre d'affaires ne peut pas etre rattache au lot de
     * recolte d'un autre tenant.
     */
    public function test_a_sale_cannot_be_linked_to_another_tenants_harvest_lot(): void
    {
        DB::table('farm_harvest_lots')->insert([
            'id' => 900103,
            'company_id' => $this->other->id,
            'lot_code' => 'FOREIGN-SALE-LOT',
            'crop_name' => 'barley',
            'quantity_harvested' => '300.000',
            'unit' => 'kg',
            'quantity_remaining' => '300.000',
            'status' => 'open',
            'harvested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(FarmFinanceException::class);

        app(FarmProfitabilityService::class)->linkSale(
            $this->company,
            4242,
            '900.00',
            'EUR',
            Carbon::now(),
            harvestLotId: 900103,
        );
    }

    /**
     * HORS LIGNE — une file de synchronisation ne voit jamais les operations
     * d'un autre tenant : le rejeu d'un identifiant client etranger ne renvoie
     * pas l'operation d'autrui.
     */
    public function test_sync_queue_of_another_tenant_is_invisible(): void
    {
        $service = app(FarmSyncService::class);

        // BelongsToCompany ecrase `company_id` avec le tenant actif (#7646) :
        // une operation soumise « pour » l'autre tenant atterrirait chez le
        // tenant courant. On insere donc la ligne etrangere en SQL brut, puis on
        // verifie que la file du tenant courant ne la voit pas.
        DB::table('farm_sync_operations')->insert([
            'id' => 900301,
            'company_id' => $this->other->id,
            'client_operation_id' => 'shared-client-id',
            'operation_type' => FarmOfflineOperationType::RecordObservation->value,
            'payload' => json_encode(['note' => 'theirs'], JSON_THROW_ON_ERROR),
            'payload_hash' => str_repeat('a', 64),
            'status' => 'pending',
            'attempt_count' => 0,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertCount(0, $service->pending($this->company));

        $mine = $service->submit(
            $this->company,
            'shared-client-id',
            FarmOfflineOperationType::RecordObservation,
            ['note' => 'mine'],
            Carbon::now(),
        );

        // L'identifiant client est unique PAR TENANT : le mien n'est pas un
        // rejeu de celui de l'autre exploitation.
        $this->assertFalse($mine['replayed']);
        $this->assertSame(1, DB::table('farm_sync_operations')->where('company_id', $this->company->id)->count());
        $this->assertSame(1, DB::table('farm_sync_operations')->where('company_id', $this->other->id)->count());
    }
}
