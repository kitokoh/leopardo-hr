<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCancellationPolicy;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Infrastructure\Services\TravelCancellationPolicyService;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * TRAVEL-813 (#6103) — Politiques d'annulation configurables.
 *
 * Couvre le CRUD, la résolution par spécificité (trajet/classe/défaut),
 * l'application des pénalités calculées serveur et l'isolation cross-tenant.
 */
class TravelCancellationPolicyApiTest extends TestCase
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

    public function test_principal_can_create_policy(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/cancellation-policies', [
            'penalty_percent' => 25,
            'cancel_before_hours' => 24,
            'refundable' => true,
            'description' => 'Annulation sous 24 h : pénalité 25 %.',
        ])->assertStatus(201)
            ->assertJsonFragment(['penalty_percent' => 25, 'cancel_before_hours' => 24]);
    }

    public function test_penalty_percent_is_bounded(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);
        $this->principal($company);

        $this->postJson('/api/v1/travel/cancellation-policies', [
            'penalty_percent' => 150,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('penalty_percent');
    }

    public function test_non_manager_cannot_create_policy(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
        ]);
        Sanctum::actingAs($employee);

        $this->postJson('/api/v1/travel/cancellation-policies', [
            'penalty_percent' => 10,
        ])->assertStatus(403);
    }

    public function test_policy_of_another_tenant_returns_404(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($companyA);
        $this->principal($companyA);

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $policyId = app(TenantManager::class)->withinTenant($companyB, function (): int {
            return TravelCancellationPolicy::factory()->create()->id;
        });

        $this->getJson("/api/v1/travel/cancellation-policies/{$policyId}")->assertStatus(404);
    }

    public function test_most_specific_policy_wins_and_penalty_is_applied(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $ids = app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create();
            $class = TravelClass::factory()->create();

            // Règle globale : 50 %.
            TravelCancellationPolicy::factory()->global(50)->create();
            // Règle spécifique (trajet, classe) : 10 %.
            $specific = TravelCancellationPolicy::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'penalty_percent' => 10,
            ]);

            return ['trip' => $trip->id, 'class' => $class->id, 'policy' => $specific->id];
        });

        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()->findOrFail($ids['trip']);

        $penalty = app(TravelCancellationPolicyService::class)
            ->penaltyFor($trip, $ids['class'], now()->addDay());

        $this->assertSame(10, $penalty['penalty_percent']);
        $this->assertSame($ids['policy'], $penalty['policy']?->id);
    }

    public function test_policy_outside_deadline_applies_no_penalty(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $this->activateTravel($company);

        $tripId = app(TenantManager::class)->withinTenant($company, function (): int {
            TravelCancellationPolicy::factory()->global(50, cancelBeforeHours: 24)->create();

            return TravelTrip::factory()->create([
                'departure_date' => now()->addDays(10)->toDateString(),
                'departure_time' => '08:00',
            ])->id;
        });

        /** @var TravelTrip $trip */
        $trip = TravelTrip::query()->findOrFail($tripId);

        // Annulation 10 jours avant le départ → hors délai → aucune pénalité.
        $penalty = app(TravelCancellationPolicyService::class)
            ->penaltyFor($trip, null, $trip->departure_date->copy()->setTimeFromTimeString('08:00'));

        $this->assertSame(0, $penalty['penalty_percent']);
    }
}
