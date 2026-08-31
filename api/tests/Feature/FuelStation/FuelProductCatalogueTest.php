<?php

declare(strict_types=1);

namespace Tests\Feature\FuelStation;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FuelStation\Domain\Models\FuelProduct;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Catalogue produits FuelStation — Issue #5797 (FUEL-003).
 *
 * Verrouille :
 *   1. table `fuel_products` créée dans le schéma tenant (parité migrations) ;
 *   2. code unique PAR TENANT (même code possible chez deux tenants) ;
 *   3. company_id non nullable ;
 *   4. statut allowlisté (CHECK active|inactive) ;
 *   5. unité allowlistée (CHECK l|gal).
 */
class FuelProductCatalogueTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;
    }

    public function test_fuel_products_table_exists_in_tenant_schema(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_products'));

        $row = DB::selectOne(
            'SELECT t.table_schema FROM information_schema.tables t WHERE t.table_name = ? LIMIT 1',
            ['fuel_products']
        );
        $this->assertSame('shared_tenants', $row->table_schema ?? null, 'fuel_products absente du schéma tenant');
    }

    public function test_product_code_is_unique_per_tenant(): void
    {
        FuelProduct::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'ESSENCE',
            'name' => 'Essence sans plomb',
            'unit_code' => 'l',
        ]);
        FuelProduct::query()->create([
            'company_id' => $this->companyB->id,
            'code' => 'ESSENCE',
            'name' => 'Essence (autre tenant)',
            'unit_code' => 'l',
        ]);

        $this->assertSame(2, FuelProduct::query()->count());

        try {
            DB::transaction(function (): void {
                FuelProduct::query()->create([
                    'company_id' => $this->companyA->id,
                    'code' => 'ESSENCE',
                    'name' => 'Doublon',
                    'unit_code' => 'l',
                ]);
            });
            $this->fail("L'unicité fuel_products_company_code_unique aurait dû rejeter le doublon.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_products_company_code_unique', $exception->getMessage());
        }
    }

    public function test_product_requires_company(): void
    {
        $this->expectException(QueryException::class);

        // Savepoint (#4978) : le RAISE ne doit pas empoisonner la transaction
        // RefreshDatabase (sinon 25P02 en cascade sur le tearDown).
        DB::transaction(function (): void {
            FuelProduct::query()->create(['code' => 'GPL', 'name' => 'Sans tenant']);
        });
    }

    public function test_product_status_is_allowlisted(): void
    {
        try {
            DB::transaction(function (): void {
                FuelProduct::query()->create([
                    'company_id' => $this->companyA->id,
                    'code' => 'GPL',
                    'name' => 'GPL',
                    'unit_code' => 'l',
                    'status' => 'vaporized',
                ]);
            });
            $this->fail('Le CHECK fuel_products_status_check aurait dû rejeter le statut.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_products_status_check', $exception->getMessage());
        }
    }

    public function test_product_unit_is_allowlisted(): void
    {
        try {
            DB::transaction(function (): void {
                FuelProduct::query()->create([
                    'company_id' => $this->companyA->id,
                    'code' => 'GPL',
                    'name' => 'GPL',
                    'unit_code' => 'barrel',
                ]);
            });
            $this->fail('Le CHECK fuel_products_unit_check aurait dû rejeter l\'unité.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_products_unit_check', $exception->getMessage());
        }
    }

    public function test_product_lifecycle_roundtrip(): void
    {
        /** @var FuelProduct $product */
        $product = FuelProduct::query()->create([
            'company_id' => $this->companyA->id,
            'code' => 'GAZOIL',
            'name' => 'Gasoil',
            'unit_code' => 'l',
            'status' => FuelProduct::STATUS_ACTIVE, // default DB 'active' — explicite pour l'objet mémoire
        ]);

        $this->assertTrue($product->isActive());
        $this->assertSame('GAZOIL', $product->code);

        $product->update(['status' => FuelProduct::STATUS_INACTIVE]);
        $this->assertFalse($product->isActive());
    }
}
