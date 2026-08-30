<?php

declare(strict_types=1);

namespace Tests\Feature\Fuel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\Notification;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Notifications & alertes — FUEL-019 (issue #5813).
 *
 * Couvre : incident → événement outbox sans PII ; consommation par
 * fuel:outbox-dispatch → notification app aux managers (template fuel_*,
 * catégorie fuel) ; alerte stock bas.
 */
class FuelNotificationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['features' => ['fuel_station' => true]]);

        return $company;
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);

        return $manager;
    }

    private function operator(Company $company): Employee
    {
        /** @var Employee $operator */
        $operator = Employee::factory()->create(['company_id' => $company->id]);

        return $operator;
    }

    public function test_incident_reported_dispatches_app_notification(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $operator = $this->operator($company);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/fuel-station/incidents', [
            'severity' => 'critical',
            'title' => 'Fuite cuve',
        ])->assertStatus(201);

        // Dispatch de l'outbox → notification app aux managers.
        $this->artisan('fuel:outbox-dispatch', ['--limit' => 10])->assertExitCode(0);

        $notifications = Notification::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $manager->id)
            ->where('type', 'fuel')
            ->get();

        $this->assertGreaterThanOrEqual(1, $notifications->count());

        // Pas de PII dans la notification : ni titre d'incident, ni description.
        foreach ($notifications as $notification) {
            $this->assertStringNotContainsString('Fuite cuve', (string) $notification->title);
            $this->assertStringNotContainsString('Fuite cuve', (string) $notification->body);
        }

        // L'événement est marqué sent.
        $event = DB::table('fuel_outbox_events')
            ->where('company_id', $company->id)
            ->where('event_type', 'fuel.incident.reported.v1')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('sent', $event->status);
    }

    public function test_low_stock_template_is_localizable(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        Sanctum::actingAs($manager);

        // Le template fuel_stock_low existe dans la config communication
        // et ses clés i18n sont présentes dans les 4 locales.
        $templates = config('communication.templates');
        $this->assertIsArray($templates);
        $this->assertArrayHasKey('fuel_stock_low', $templates);
        $this->assertArrayHasKey('fuel_incident_reported', $templates);

        foreach (['fr', 'en', 'tr', 'ar'] as $locale) {
            $title = trans('notifications.fuel_stock_low_title', ['station_id' => '1', 'product' => 'ESS', 'level' => '100'], $locale);
            $this->assertNotSame('notifications.fuel_stock_low_title', $title);
            $this->assertNotEmpty($title);
        }
    }
}
