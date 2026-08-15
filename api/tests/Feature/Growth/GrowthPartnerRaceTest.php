<?php

declare(strict_types=1);

namespace Tests\Feature\Growth;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Billing\Domain\Models\Partner;
use App\Modules\Billing\Domain\Models\PartnerPayoutRequest;
use App\Modules\Payroll\Domain\Models\Commission;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * QA expert5 #2999 — races Growth :
 * - double candidature partenaire → 1 seul partner (contrainte unique + 400 propre) ;
 * - demandes de payout concurrentes ne dépassent pas le solde (lock transaction) ;
 * - jamais de message d'exception brut exposé au client.
 */
class GrowthPartnerRaceTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->employee = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($this->employee);
    }

    public function test_double_apply_is_blocked_with_clean_400(): void
    {
        $payload = ['type' => 'individual'];

        $first = $this->postJson('/api/v1/growth/partner/apply', $payload);
        $first->assertCreated();

        // Seconde candidature (même user) → 400 ALREADY_EXISTS, pas 201, pas de doublon.
        $second = $this->postJson('/api/v1/growth/partner/apply', $payload);
        $second->assertStatus(400)
            ->assertJsonPath('error', 'ALREADY_EXISTS');

        $this->assertSame(1, Partner::where('user_id', $this->employee->id)->count());
    }

    public function test_payout_does_not_expose_raw_exception_message(): void
    {
        $partner = $this->createApprovedPartner(10000); // 100,00 de commissions approuvées

        $response = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 999999,
            'currency' => 'DZD',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_BALANCE')
            ->assertJsonMissing(['message' => 'Solde insuffisant.']);
    }

    public function test_consecutive_payouts_cannot_overdraw_balance(): void
    {
        $partner = $this->createApprovedPartner(10000); // solde disponible 100,00

        $first = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 8000,
            'currency' => 'DZD',
        ]);
        $first->assertCreated();

        // Solde restant 20,00 → une seconde demande de 50,00 doit être refusée.
        $second = $this->postJson('/api/v1/growth/partner/payout', [
            'amount' => 5000,
            'currency' => 'DZD',
        ]);
        $second->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_BALANCE');

        $this->assertSame(1, PartnerPayoutRequest::where('partner_id', $partner->id)->count());
    }

    public function test_service_apply_is_idempotent_under_unique_constraint(): void
    {
        $service = app(\App\Modules\Billing\Infrastructure\Services\PartnerService::class);

        $first = $service->apply((int) $this->employee->id, ['type' => 'individual']);
        $this->assertInstanceOf(Partner::class, $first);

        $this->expectException(\App\Exceptions\DomainException::class);
        $this->expectExceptionMessage('Déjà partenaire.');
        $service->apply((int) $this->employee->id, ['type' => 'individual']);
    }

    private function createApprovedPartner(int $commissionsCents): Partner
    {
        $partner = Partner::create([
            'user_id' => $this->employee->id,
            'referral_code' => 'QA-'.strtoupper(substr(uniqid(), -6)),
            'application_status' => 'approved',
            'status' => 'active',
            'type' => 'individual',
            'payout_threshold' => 0,
        ]);

        Commission::create([
            'partner_id' => $partner->id,
            'company_id' => $this->company->id,
            'payment_id' => 1,
            'amount' => $commissionsCents,
            'currency' => 'DZD',
            'applied_rate' => 1000,
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return $partner;
    }
}
