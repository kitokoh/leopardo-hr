<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-209 (#6022) — Réservations multi-passagers et PII chiffrée.
 *
 * Couvre la génération de référence, l'unicité de `idempotency_key`, et le
 * chiffrement/hash du n° de pièce d'identité (jamais en clair).
 */
class TravelBookingTest extends TestCase
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

    public function test_reference_is_generated_automatically(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            $booking = TravelBooking::factory()->create();

            $this->assertNotEmpty($booking->reference);
            $this->assertStringStartsWith('GV-', $booking->reference);
        });
    }

    public function test_idempotency_key_is_unique_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelBooking::factory()->create(['idempotency_key' => 'replay-key-001']);

            $this->expectException(QueryException::class);
            DB::transaction(fn () => TravelBooking::factory()->create(['idempotency_key' => 'replay-key-001']));
        });
    }

    public function test_same_idempotency_key_allowed_across_tenants(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelBooking::factory()->create(['idempotency_key' => 'shared-key']);
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            TravelBooking::factory()->create(['idempotency_key' => 'shared-key']);
            $this->assertSame(1, TravelBooking::query()->count());
        });
    }

    public function test_bookings_are_isolated_per_tenant(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            TravelBooking::factory()->create();
        });

        $this->tenants->withinTenant($this->companyB, function (): void {
            $this->assertSame(0, TravelBooking::query()->count());
        });
    }

    public function test_document_number_is_never_stored_in_plaintext(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::factory()->make();
            $passenger->setDocumentNumber('AB1234567');
            /** @var int<0, max> $bookingId */
            $bookingId = TravelBooking::factory()->create()->id;
            $passenger->booking_id = $bookingId;
            $passenger->save();

            $raw = DB::table('travel_passengers')->where('id', $passenger->id)->first();

            $this->assertNotNull($raw);
            $this->assertStringNotContainsString('AB1234567', (string) $raw->document_number_encrypted);
            $this->assertSame(hash('sha256', 'AB1234567'), $raw->document_number_hash);
            $this->assertSame('AB1234567', $passenger->refresh()->getDocumentNumber());
        });
    }

    public function test_document_number_is_hidden_from_array_serialization(): void
    {
        $this->tenants->withinTenant($this->companyA, function (): void {
            /** @var TravelPassenger $passenger */
            $passenger = TravelPassenger::factory()->make();
            $passenger->setDocumentNumber('CD9999999');
            /** @var int<0, max> $bookingId */
            $bookingId = TravelBooking::factory()->create()->id;
            $passenger->booking_id = $bookingId;
            $passenger->save();

            $array = $passenger->refresh()->toArray();

            $this->assertArrayNotHasKey('document_number_encrypted', $array);
            $this->assertArrayNotHasKey('document_number_hash', $array);
        });
    }
}
