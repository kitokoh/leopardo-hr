<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCountry;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1012 (#6125) — Pilote : tenant synthétique + préparation.
 *
 * Le rapport `leopardo:travel:pilot-check` valide les prérequis du pilote
 * (flag, géo, trajet publié, secrets, jeton) — recette signée possible.
 */
class TravelPilotCheckTest extends TestCase
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

    public function test_pilot_check_reports_readiness(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $company->setFeature('travelagency', true);
        $company->save();
        $this->principal($company);

        config()->set('travel.payments.callback_secret', 'pilot-secret');

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            TravelCountry::factory()->create(['company_id' => $company->id]);
            TravelTrip::factory()->create(['company_id' => $company->id, 'status' => 'published']);
            TravelPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => 'hash',
                'active' => true,
            ]);
        });

        $exit = Artisan::call('leopardo:travel:pilot-check', ['--tenant' => $company->id]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Préparation pilote COMPLÈTE', Artisan::output());
    }

    public function test_pilot_check_fails_on_missing_prereqs(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->principal($company);

        $exit = Artisan::call('leopardo:travel:pilot-check', ['--tenant' => $company->id]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('INCOMPLÈTE', Artisan::output());
    }
}
