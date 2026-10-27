<?php

declare(strict_types=1);

namespace Tests\Feature\Planning;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\Site;
use App\Modules\Planning\Domain\Models\Schedule;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #8450 — Rotations multi-sites : le socle `schedules` porte l'axe
 * établissement, donc n'importe quelle verticale multi-sites peut planifier
 * et suivre ses rotations par site (et non plus seulement Restaurant /
 * FuelStation).
 */
class ScheduleSiteScopeTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    private function makeSchedule(Company $company, string $name, ?int $siteId = null): Schedule
    {
        return Schedule::query()->create([
            'company_id' => $company->id,
            'site_id' => $siteId,
            'name' => $name,
            'start_time' => '08:00',
            'end_time' => '16:00',
            'break_minutes' => 30,
            'work_days' => [1, 2, 3, 4, 5],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => 8,
            'overtime_threshold_weekly' => 40,
            'is_default' => false,
        ]);
    }

    public function test_manager_can_create_a_rotation_for_a_site(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $site = Site::query()->create(['company_id' => $company->id, 'name' => 'Station A']);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/schedules', [
            'name' => 'Matin station A',
            'site_id' => $site->id,
            'start_time' => '06:00',
            'end_time' => '14:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.site_id', $site->id)
            ->assertJsonPath('data.name', 'Matin station A');
    }

    public function test_rotation_without_site_stays_tenant_wide(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/schedules', [
            'name' => 'Rotation historique',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.site_id', null);
    }

    public function test_manager_cannot_attach_a_rotation_to_another_tenants_site(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $foreignSite = Site::query()->create(['company_id' => $otherCompany->id, 'name' => 'Site étranger']);

        Sanctum::actingAs($manager);

        $this->postJson('/api/v1/schedules', [
            'name' => 'Fuite inter-tenant',
            'site_id' => $foreignSite->id,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('schedules', ['name' => 'Fuite inter-tenant']);
    }

    public function test_index_filters_by_site_and_keeps_tenant_wide_rotations(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $siteA = Site::query()->create(['company_id' => $company->id, 'name' => 'Site A']);
        $siteB = Site::query()->create(['company_id' => $company->id, 'name' => 'Site B']);

        $this->makeSchedule($company, 'Rotation site A', $siteA->id);
        $this->makeSchedule($company, 'Rotation site B', $siteB->id);
        $this->makeSchedule($company, 'Rotation commune', null);

        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/v1/schedules?site_id={$siteA->id}")->assertOk();

        $names = array_column($response->json('data'), 'name');
        sort($names);

        $this->assertSame(['Rotation commune', 'Rotation site A'], $names);
    }

    public function test_manager_can_move_a_rotation_to_another_site(): void
    {
        $company = Company::factory()->create();
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $siteA = Site::query()->create(['company_id' => $company->id, 'name' => 'Site A']);
        $siteB = Site::query()->create(['company_id' => $company->id, 'name' => 'Site B']);
        $schedule = $this->makeSchedule($company, 'Rotation mobile', $siteA->id);

        Sanctum::actingAs($manager);

        $this->patchJson("/api/v1/schedules/{$schedule->id}", ['site_id' => $siteB->id])
            ->assertOk()
            ->assertJsonPath('data.site_id', $siteB->id);

        // Retour à « tout le tenant » : `site_id: null` doit être accepté.
        $this->patchJson("/api/v1/schedules/{$schedule->id}", ['site_id' => null])
            ->assertOk()
            ->assertJsonPath('data.site_id', null);
    }
}
