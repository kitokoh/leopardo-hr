<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Auth\Domain\Models\Employee;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #3369 — syncTrips : plage de dates bornée (max 90 jours, pas de
 * futur, from <= to). Sans véhicules Traccar, aucun appel externe n'est
 * déclenché : la validation se fait en tête de contrôleur.
 */
class TrackingSyncTripsDateRangeTest extends TestCase
{
    use RefreshTenantDatabase;

    protected Company $company;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Company $company */
        $company = Company::factory()->create();
        $this->company = $company;
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $this->company->id]);
        $this->manager = $manager;
    }

    /** @test */
    public function rejects_from_after_to(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tracking/sync-trips', [
                'from' => now()->toDateString(),
                'to' => now()->subDays(3)->toDateString(),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function rejects_future_to_date(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tracking/sync-trips', [
                'from' => now()->toDateString(),
                'to' => now()->addDays(2)->toDateString(),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function rejects_range_longer_than_90_days(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tracking/sync-trips', [
                'from' => now()->subDays(120)->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertStatus(422);
    }

    /** @test */
    public function accepts_valid_range_without_vehicles(): void
    {
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/tracking/sync-trips', [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertOk();
    }
}
