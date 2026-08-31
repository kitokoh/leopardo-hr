<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HR\Infrastructure\Services\SectorTemplateService;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Régression #5967 (BC-05 WORKFORCE).
 *
 * L'index UNIQUE sur `absence_types.code` était GLOBAL (code seul) alors que le
 * schéma est partagé (`shared_tenants`). Conséquence : le seed par tenant via
 * insertOrIgnore() ne peuplait QUE le premier tenant ; tous les suivants
 * voyaient leurs inserts silencieusement ignorés.
 *
 * Avec l'index composite `(company_id, code)` (migration
 * 2026_08_31_000100_5967), deux tenants distincts peuvent avoir les mêmes
 * codes standard, et un doublon INTRA-tenant reste interdit.
 */
class AbsenceTypesTenantUniqueRegressionTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeCompany(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Co '.$slug,
            'slug' => $slug,
            'sector' => 'standard',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => $slug.'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => 'fr',
            'timezone' => 'UTC',
            'currency' => 'DZD',
        ]);
    }

    private function table(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'shared_tenants.absence_types' : 'absence_types';
    }

    public function test_two_tenants_receive_the_same_standard_absence_codes(): void
    {
        $service = new SectorTemplateService;

        $a = $this->makeCompany('tenant-a-5967');
        $b = $this->makeCompany('tenant-b-5967');

        $service->applyTemplate($a);
        $service->applyTemplate($b);

        $codesA = DB::table($this->table())
            ->where('company_id', $a->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $codesB = DB::table($this->table())
            ->where('company_id', $b->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        // Le fix garantit que le 2e tenant reçoit réellement ses codes standard.
        $this->assertContains('CA', $codesA);
        $this->assertContains('MAL', $codesA);
        $this->assertContains('CA', $codesB, 'Le 2e tenant doit recevoir le code CA (régression #5967).');
        $this->assertContains('MAL', $codesB, 'Le 2e tenant doit recevoir le code MAL (régression #5967).');
        $this->assertSame($codesA, $codesB, 'Les deux tenants standard doivent avoir le même jeu de codes.');
    }

    public function test_duplicate_code_within_same_tenant_is_rejected(): void
    {
        $a = $this->makeCompany('tenant-dup-5967');

        DB::table($this->table())->insert([
            'company_id' => $a->id,
            'name' => 'Congé Annuel',
            'code' => 'CA',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Même (company_id, code) → doit violer l'index unique composite.
        DB::table($this->table())->insert([
            'company_id' => $a->id,
            'name' => 'Congé Annuel (doublon)',
            'code' => 'CA',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }
}
