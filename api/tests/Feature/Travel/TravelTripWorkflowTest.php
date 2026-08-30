<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Enums\TripStatus;
use App\Modules\TravelAgency\Domain\Models\TravelOutboxEvent;
use App\Modules\TravelAgency\Domain\Models\TravelRoute;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-310 (#6040) — POST /travel/trips/{trip}/publish · /cancel.
 *
 * Transitions validées (draft|scheduled → published ; → cancelled),
 * événements outbox `travel.trip.published.v1` / `travel.trip.cancelled.v1`
 * publiés après commit, motif obligatoire à l'annulation, idempotence.
 */
class TravelTripWorkflowTest extends TestCase
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

    public function test_publish_transitions_draft_to_published(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create(['status' => 'draft'])->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.published_at', fn ($value) => $value !== null);

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOutboxEvent::query()
                ->where('event_type', 'travel.trip.published.v1')
                ->count();
        }));
    }

    public function test_publish_is_idempotent(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create(['status' => 'published'])->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_cancel_requires_reason(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/cancel")
            ->assertStatus(422);
    }

    public function test_cancel_transitions_to_cancelled_and_publishes_event(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelTrip::factory()->create(['status' => 'published'])->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/cancel", [
            'reason' => 'Panne de véhicule',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(1, app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelOutboxEvent::query()
                ->where('event_type', 'travel.trip.cancelled.v1')
                ->where('payload_redacted->reason', 'Panne de véhicule')
                ->count();
        }));
    }

    public function test_trip_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $tripId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelTrip::factory()->create()->id;
        });

        $this->principal($companyA);

        $this->postJson("/api/v1/travel/trips/{$tripId}/publish")->assertStatus(404);
    }

    public function test_outbox_events_are_isolated_per_tenant(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        $tripId = app(TenantManager::class)->withinTenant($companyA, function (): int {
            return TravelTrip::factory()->create(['status' => 'draft'])->id;
        });

        $this->postJson("/api/v1/travel/trips/{$tripId}/publish")->assertOk();

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->assertSame(0, app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelOutboxEvent::query()->count();
        }));
    }

    public function test_trip_status_defaults_to_draft_through_api(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $routeId = app(TenantManager::class)->withinTenant($company, function (): int {
            return TravelRoute::factory()->create()->id;
        });

        $this->postJson('/api/v1/travel/trips', [
            'code' => 'TRP-DRAFT-1',
            'route_id' => $routeId,
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'arrival_date' => now()->addDays(3)->toDateString(),
            'arrival_time' => '12:30',
            'total_seats' => 40,
        ])->assertStatus(201)
            ->assertJsonPath('data.status', TripStatus::DRAFT->value);
    }
}
