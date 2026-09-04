<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Database\Seeders\Concerns\GuardsPilotSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * MAT-012 (#5870) — Seed pilote FuelStation : environnement reproductible,
 * non sensible et démontrable (BC-15 FUEL, runbook RUNBOOK_PILOT_FUELSTATION).
 *
 * Crée le tenant déterministe `fuel-pilot-001` avec :
 *  - un pompiste (manager principal) ;
 *  - une station (code ST-ALG-01) + produits (Essence 95, Diesel, GPLc) +
 *    pompes + cuves + un shift ;
 *  - 3 ventes synthétiques aux montants calculés à la main ;
 *  - zéro donnée réelle, zéro secret, valeurs 100 % déterministes.
 *
 * Réentrant : si le tenant pilote existe déjà, il est conservé (skip).
 * Idempotent et nettoyable : `pilot:seed --solution=fuel --clean` supprime
 * le tenant pilote et ses lignes.
 *
 * Usage : php artisan db:seed --class=FuelStationPilotSeeder
 *         php artisan pilot:seed --solution=fuel [--clean]
 */
class FuelStationPilotSeeder extends Seeder
{
    use GuardsPilotSeeding;

    public const SLUG = 'fuel-pilot-001';

    private const SHARED_SCHEMA = 'shared_tenants';

    public function run(): void
    {
        $this->assertPilotEnvironmentAllowed('fuel');

        $existing = Company::query()->where('slug', self::SLUG)->first();

        if ($existing instanceof Company) {
            $this->command?->warn("Pilote {$this->slug()} déjà présent — skip (réentrant).");

            return;
        }

        if (! Schema::hasTable('fuel_stations')) {
            $this->command?->warn('Tables FuelStation absentes — seed pilote ignoré (en attente des migrations BC-15).');

            return;
        }

        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => 'FuelStation Pilot 001',
            'slug' => self::SLUG,
            'schema_name' => self::SHARED_SCHEMA,
            'tenancy_type' => 'shared',
            'country' => 'DZ',
            'currency' => 'DZD',
            'status' => 'active',
            'features' => ['rh' => true, 'fuel_station' => true],
        ]);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            $this->seedFuelData($company);
        });

        $this->command?->info('Seed pilote FuelStation créé : '.self::SLUG);
    }

    private function seedFuelData(Company $company): void
    {
        $companyId = (string) $company->id;
        $domain = 'fuel.pilot.leopardo.test';
        $now = now();

        // Mot de passe DÉMO documenté (parcours pilote) — jamais un secret réel.
        $demoHash = Hash::make('pilot123');

        $pompisteId = DB::table('employees')->insertGetId([
            'company_id' => $companyId,
            'first_name' => 'Sofiane',
            'last_name' => 'Pompiste',
            'email' => "pompiste@{$domain}",
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
            'password_hash' => $demoHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $stationId = DB::table('fuel_stations')->insertGetId([
            'company_id' => $companyId,
            'code' => 'ST-ALG-01',
            'name' => 'Station Pilote Alger',
            'address' => 'Zone pilote, Alger',
            'phone' => '+213555010101',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $products = [
            ['code' => 'ESS95', 'name' => 'Essence 95', 'unit_code' => 'l'],
            ['code' => 'DIESEL', 'name' => 'Diesel', 'unit_code' => 'l'],
            ['code' => 'GPLC', 'name' => 'GPLc', 'unit_code' => 'l'],
        ];

        foreach ($products as $product) {
            DB::table('fuel_products')->insert([
                'company_id' => $companyId,
                'code' => $product['code'],
                'name' => $product['name'],
                'unit_code' => $product['unit_code'],
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['P1', 'P2'] as $pumpCode) {
            DB::table('fuel_pumps')->insert([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'code' => $pumpCode,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('fuel_tanks')->insert([
            'company_id' => $companyId,
            'station_id' => $stationId,
            'code' => 'TK-01',
            'product_type' => 'ESS95',
            'capacity_minor' => 10000,
            'current_level_minor' => 7500,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('fuel_shifts')->insert([
            'company_id' => $companyId,
            'station_id' => $stationId,
            'name' => 'Matin',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'status' => 'active',
            'notes' => 'Shift pilote synthétique',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Ventes synthétiques — montants calculés à la main :
        //   ESS95 : 40,000 l × 145,00 = 5 800,00 · Diesel : 50,000 l × 135,00 = 6 750,00
        //   GPLc  : 20,000 l × 90,00  = 1 800,00
        $sales = [
            ['product' => 'ESS95', 'quantity' => 40.000, 'unit_price' => 145.00, 'amount' => 5800.00, 'external_id' => 'fuel-pilot-sale-001'],
            ['product' => 'DIESEL', 'quantity' => 50.000, 'unit_price' => 135.00, 'amount' => 6750.00, 'external_id' => 'fuel-pilot-sale-002'],
            ['product' => 'GPLC', 'quantity' => 20.000, 'unit_price' => 90.00, 'amount' => 1800.00, 'external_id' => 'fuel-pilot-sale-003'],
        ];

        foreach ($sales as $sale) {
            DB::table('fuel_sales')->insert([
                'company_id' => $companyId,
                'station_id' => $stationId,
                'employee_id' => $pompisteId,
                'product' => $sale['product'],
                'quantity' => $sale['quantity'],
                'unit_price' => $sale['unit_price'],
                'amount' => $sale['amount'],
                'sale_time' => $now,
                'source' => 'manual',
                'external_id' => $sale['external_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function slug(): string
    {
        return self::SLUG;
    }
}
