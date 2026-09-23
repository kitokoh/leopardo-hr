<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * Issue #7960 — wrappers explicites de bypass du scope tenant.
 *
 * `withoutGlobalScope('company')` brut est désormais interdit par la garde
 * CI `dev-hub/tools/check-without-global-scope.sh`. Ces tests verrouillent
 * le contrat des trois wrappers nommés du trait `BelongsToCompany` :
 *   - forCompany($company)            → UN tenant explicite, jamais un autre ;
 *   - crossTenantForPlatformAdmin()   → tous les tenants ;
 *   - crossTenantForSystemTask($why)  → tous les tenants (console/jobs).
 */
class BelongsToCompanyExplicitBypassTest extends TestCase
{
    use SwitchesTenantContext;

    private Company $alpha;

    private Company $beta;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
        } else {
            Schema::dropIfExists('employees');
        }

        Schema::create('employees', function (Blueprint $table): void {
            $table->increments('id');
            $table->uuid('company_id');
            $table->string('email', 150)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 20)->default('employee');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        $this->purgeTestCompanies();

        /** @var Company $alpha */
        $alpha = Company::factory()->create([
            'name' => 'Bypass Alpha',
            'slug' => 'bypass-alpha',
            'email' => 'alpha@bypass.test',
        ]);
        /** @var Company $beta */
        $beta = Company::factory()->create([
            'name' => 'Bypass Beta',
            'slug' => 'bypass-beta',
            'email' => 'beta@bypass.test',
        ]);
        $this->alpha = $alpha;
        $this->beta = $beta;

        foreach ([$alpha, $beta] as $company) {
            $this->withTenantContext($company, function () use ($company): void {
                $employee = new Employee(['email' => "emp@{$company->slug}.test"]);
                $employee->forceFill(['password_hash' => Hash::make('secret')])->save();
            });
        }
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('current_company');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS shared_tenants.employees CASCADE');
        } else {
            Schema::dropIfExists('employees');
        }
        $this->purgeTestCompanies();
        parent::tearDown();
    }

    private function purgeTestCompanies(): void
    {
        DB::table('public.companies')->whereIn('slug', ['bypass-alpha', 'bypass-beta'])->delete();
    }

    public function test_for_company_targets_exactly_one_explicit_tenant(): void
    {
        // Sous le tenant alpha, forCompany(beta) lit les données de beta —
        // et UNIQUEMENT celles de beta (cas webhook/device résolvant son
        // tenant hors current_company).
        app()->instance('current_company', $this->alpha);

        $emails = Employee::query()
            ->forCompany($this->beta)
            ->pluck('email');

        $this->assertSame(['emp@bypass-beta.test'], $emails->all());

        // Accepte aussi l'id scalaire.
        $count = Employee::query()->forCompany($this->beta->id)->count();
        $this->assertSame(1, $count);
    }

    public function test_cross_tenant_for_platform_admin_sees_all_tenants(): void
    {
        app()->instance('current_company', $this->alpha);

        $emails = Employee::query()
            ->crossTenantForPlatformAdmin()
            ->orderBy('email')
            ->pluck('email');

        $this->assertSame(
            ['emp@bypass-alpha.test', 'emp@bypass-beta.test'],
            $emails->all()
        );
    }

    public function test_cross_tenant_for_system_task_sees_all_tenants(): void
    {
        app()->instance('current_company', $this->alpha);

        $count = Employee::query()
            ->crossTenantForSystemTask('test du contrat wrapper (#7960)')
            ->count();

        $this->assertSame(2, $count);
    }

    public function test_global_scope_still_isolates_by_default(): void
    {
        // Non-régression : sans wrapper, chaque tenant ne voit que lui.
        app()->instance('current_company', $this->alpha);
        $this->assertSame(['emp@bypass-alpha.test'], Employee::query()->pluck('email')->all());
    }
}
