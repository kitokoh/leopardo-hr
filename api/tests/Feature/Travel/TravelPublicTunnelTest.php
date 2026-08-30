<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\PaymentStatus;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-1002 (#6115) — Tunnel d'achat public complet (recette E2E).
 *
 * Recherche → réservation (multi-passagers) → paiement en ligne (initiate)
 * → confirmation par callback signé → émission du billet → e-billet PDF
 * (accès par code) → suivi. Critère d'acceptation : l'achat public
 * fonctionne de bout en bout.
 */
class TravelPublicTunnelTest extends TestCase
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

    /**
     * @return array{trip: TravelTrip, token: string, class: int}
     */
    private function setupFixtures(Company $company): array
    {
        $data = app(TenantManager::class)->withinTenant($company, function () use ($company): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 30]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            $plain = 'tshop_tunnel_'.random_int(1000, 9999);
            TravelPublicShopToken::query()->create([
                'company_id' => $company->id,
                'token_hash' => TravelPublicShopToken::hash($plain),
                'name' => 'Tunnel',
                'active' => true,
            ]);

            return ['trip' => $trip, 'token' => $plain, 'class' => $class->id];
        });

        return $data;
    }

    public function test_full_public_purchase_tunnel(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $fx = $this->setupFixtures($company);
        $headers = ['X-Travel-Shop-Token' => $fx['token']];

        // 1. Recherche publique.
        $this->getJson('/api/v1/public/travel/shop/trips', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // 2. Réservation en ligne multi-passagers.
        $booking = $this->postJson('/api/v1/public/travel/shop/bookings', [
            'trip_id' => $fx['trip']->id,
            'idempotency_key' => 'tunnel-1',
            'passengers' => [
                ['full_name' => 'Alice', 'age_category' => 'adult', 'class_id' => $fx['class']],
                ['full_name' => 'Bob', 'age_category' => 'adult', 'class_id' => $fx['class']],
            ],
            'contact_email' => 'voyageur@public.example',
            'notify_consent' => true,
        ], $headers)->assertStatus(201)->json('data');

        $this->assertSame(30000, $booking['total_amount_minor']);

        // 3. Initiation du paiement en ligne (cash → confirmé par callback).
        $payment = $this->postJson('/api/v1/public/travel/payments/initiate', [
            'booking_reference' => $booking['reference'],
            'provider_code' => 'cash',
            'idempotency_key' => 'tunnel-pay-1',
        ], $headers)->assertStatus(201)->json('data');

        $this->assertSame('pending', $payment['status']);

        // 4. Confirmation par callback (public, signé HMAC).
        config()->set('travel.payments.callback_secret', 'test-secret');
        $callbackPayload = [
            'reference' => $booking['reference'],
            'provider_reference' => $payment['provider_reference'],
            'amount_minor' => 30000,
            'currency' => 'XAF',
            'status' => 'confirmed',
        ];
        $callbackPayload['signature'] = hash_hmac('sha256', implode('|', [
            $callbackPayload['reference'],
            $callbackPayload['provider_reference'],
            (string) $callbackPayload['amount_minor'],
            $callbackPayload['currency'],
            $callbackPayload['status'],
        ]), 'test-secret');

        $this->postJson('/api/v1/travel/payments/callback', $callbackPayload)->assertOk();

        // 5. Confirmation de la réservation (guichet interne) + émission billet.
        $this->postJson('/api/v1/travel/bookings/'.$booking['id'].'/confirm')->assertOk();
        $tickets = $this->postJson('/api/v1/travel/bookings/'.$booking['id'].'/issue-ticket')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $tickets);

        // 6. E-billet public : accès par code (mauvais code → 403, bon → PDF).
        $this->getJson('/api/v1/public/travel/tickets/'.$tickets[0]['id'].'/pdf', $headers)
            ->assertStatus(403);

        $code = app(TenantManager::class)->withinTenant($company, function () use ($tickets): string {
            $ticket = TravelTicket::query()->findOrFail($tickets[0]['id']);
            $code = $ticket->issueValidationCode();
            $ticket->save(); // persiste le hash du code (jamais le code en clair)

            return $code;
        });

        $this->getJson('/api/v1/public/travel/tickets/'.$tickets[0]['id'].'/pdf?code='.$code, $headers)
            ->assertOk()
            ->assertJsonPath('data.ticket_number', $tickets[0]['ticket_number']);

        // 7. Suivi public par référence + code.
        $this->getJson('/api/v1/public/travel/shop/bookings/'.$booking['reference'].'?code='.$code, $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }
}
