<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelPayment;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-210 (#6023) — Billets nominatifs et paiements.
 *
 * Couvre l'unicité de `ticket_number`/`reference`, le hash du code de
 * validation (jamais en clair) et l'isolation cross-tenant.
 */
class TravelTicketPaymentTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->companyB = $companyB;

        $this->tenants = app(TenantManager::class);
    }

    public function test_ticket_number_is_generated_automatically(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $ticket = TravelTicket::factory()->create();

            $this->assertStringStartsWith('#GV-', $ticket->ticket_number);
        });
    }

    public function test_ticket_number_unique_is_tenant_scoped(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelTicket::factory()->create(['ticket_number' => '#GV-DUP001']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelTicket::factory()->create(['ticket_number' => '#GV-DUP001']);
            $this->assertSame(1, TravelTicket::query()->count());
        });
    }

    public function test_validation_code_round_trip_never_stores_plaintext(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $ticket = TravelTicket::factory()->create();
            $plainCode = $ticket->issueValidationCode();
            $ticket->save();

            $this->assertNotSame($plainCode, $ticket->validation_code);
            $this->assertTrue($ticket->refresh()->validationCodeMatches($plainCode));
            $this->assertFalse($ticket->validationCodeMatches('WRONG-CODE'));
        });
    }

    public function test_validation_code_is_hidden_from_array_serialization(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $ticket = TravelTicket::factory()->create();

            $this->assertArrayNotHasKey('validation_code', $ticket->toArray());
        });
    }

    public function test_payment_reference_is_generated_automatically(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $payment = TravelPayment::factory()->create();

            $this->assertStringStartsWith('PAY-', $payment->reference);
        });
    }

    public function test_payment_amount_must_be_positive(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelPayment::factory()->create(['amount_minor' => 0]));
        });
    }

    public function test_payments_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelPayment::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelPayment::query()->count());
        });
    }
}
