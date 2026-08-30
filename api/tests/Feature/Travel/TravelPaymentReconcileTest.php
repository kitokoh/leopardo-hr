<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-410..411 (#6062/#6063) — Re-conciliation verify() + refund().
 */
class TravelPaymentReconcileTest extends TestCase
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

    private function activateTravel(Company $company): void
    {
        $company->setFeature('travelagency', true);
        $company->save();
    }

    public function test_verify_confirms_pending_payment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $paymentId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelPayment::factory()->create([
                'provider_code' => 'pvit',
                'provider_reference' => 'PVIT-VERIFY-1',
                'status' => PaymentStatus::PENDING,
            ])->id;
        });

        // PVIT sandbox : verify() confirme le paiement.
        $this->postJson("/api/v1/travel/payments/{$paymentId}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_refund_requires_confirmed_payment_and_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $paymentId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelPayment::factory()->create([
                'provider_code' => 'cash',
                'provider_reference' => 'CASH-REFUND-1',
                'status' => PaymentStatus::CONFIRMED,
            ])->id;
        });

        // Motif obligatoire.
        $this->postJson("/api/v1/travel/payments/{$paymentId}/refund")->assertStatus(422);

        $this->postJson("/api/v1/travel/payments/{$paymentId}/refund", ['reason' => 'Annulation client'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');
    }

    public function test_payment_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $paymentId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelPayment::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->getJson("/api/v1/travel/payments/{$paymentId}")->assertStatus(404);
    }
}
