<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\DemoDzSeeder;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Programme FOCUS — F-23 (#1553) / F-06 (#1536) : le kit de démo DZ est
 * rejouable, idempotent et produit une paie réelle (moteur de calcul) avec
 * M-2/M-1 clôturés et le mois courant calculé.
 */
class DemoDzSeederTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_demo_dz_seeder_creates_realistic_closed_payroll(): void
    {
        (new DemoDzSeeder())->run(employeeCount: 5);

        /** @var Company $company */
        $company = Company::query()->where('slug', DemoDzSeeder::COMPANY_SLUG)->firstOrFail();
        $this->assertSame('DZ', $company->country);
        $this->assertSame('DZD', $company->currency);

        // 5 employés + 4 comptes de démo.
        $this->assertSame(9, \App\Core\Auth\Domain\Models\Employee::query()
            ->where('company_id', $company->id)
            ->count());

        // 3 runs de paie.
        $runs = PayrollRun::query()->where('company_id', $company->id)->orderBy('period_start')->get();
        $this->assertCount(3, $runs);

        $statuses = $runs->pluck('status')->all();
        $this->assertSame(['locked', 'locked', 'calculated'], $statuses);

        // Les runs clôturés portent des bulletins calculés + verrouillage.
        foreach ($runs as $run) {
            $this->assertGreaterThan(0, $run->paySlips()->count(), 'chaque run doit produire des bulletins');
            $this->assertNotNull($run->total_gross);
        }

        $locked = $runs->firstWhere('status', 'locked');
        $this->assertNotNull($locked->locked_at);
        $this->assertNotNull($locked->locked_by);
    }

    public function test_demo_dz_seeder_is_idempotent(): void
    {
        (new DemoDzSeeder())->run(employeeCount: 5);
        (new DemoDzSeeder())->run(employeeCount: 5);

        /** @var Company $company */
        $company = Company::query()->where('slug', DemoDzSeeder::COMPANY_SLUG)->firstOrFail();
        $this->assertSame(9, \App\Core\Auth\Domain\Models\Employee::query()
            ->where('company_id', $company->id)
            ->count());
        $this->assertSame(3, PayrollRun::query()->where('company_id', $company->id)->count());
    }
}
