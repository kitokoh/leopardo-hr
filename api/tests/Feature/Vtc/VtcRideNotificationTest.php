<?php

declare(strict_types=1);

namespace Tests\Feature\Vtc;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Jobs\SendPushNotificationJob;
use App\Modules\Vtc\Domain\Enums\VtcDriverStatus;
use App\Modules\Vtc\Domain\Enums\VtcRideStatus;
use App\Modules\Vtc\Domain\Events\VtcRideAccepted;
use App\Modules\Vtc\Domain\Events\VtcRideCompleted;
use App\Modules\Vtc\Domain\Events\VtcRideExpired;
use App\Modules\Vtc\Domain\Models\VtcDriver;
use App\Modules\Vtc\Domain\Models\VtcRide;
use Illuminate\Support\Facades\Queue;
use Tests\RefreshTenantDatabase;
use Tests\Support\SwitchesTenantContext;
use Tests\TestCase;

/**
 * #8423 (M-20) — Notifications de cycle de vie : les événements
 * VtcRideAccepted / VtcRideExpired / VtcRideCompleted (orphelins avant ce
 * lot) déclenchent un push plateforme vers le passager — et le chauffeur
 * à la clôture.
 *
 *   - passager notifié dans SA locale (titre/corps traduits) ;
 *   - passager INVITÉ (marketplace, passenger_user_id null) : rien à
 *     notifier, aucune erreur ;
 *   - clôture : passager (montant final) ET chauffeur (récapitulatif) ;
 *   - dégradation gracieuse : course inconnue ou fiche absente → silence,
 *     jamais d'exception (une notification ne fait pas échouer la course).
 */
class VtcRideNotificationTest extends TestCase
{
    use RefreshTenantDatabase;
    use SwitchesTenantContext;

    protected function setUp(): void
    {
        parent::setUp();

        // Les pushes sont des jobs en file : on capture, jamais d'envoi.
        Queue::fake();
    }

    public function test_accepted_notifies_the_passenger_in_his_locale(): void
    {
        $company = $this->createCompany();
        $passenger = $this->createEmployee($company, preferredLanguage: 'en');
        [, $driver] = $this->createLinkedDriver($company);

        $ride = $this->createRide($company, [
            'passenger_user_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => VtcRideStatus::Accepted->value,
        ]);

        $this->withTenantContext($company, function () use ($company, $ride, $driver): void {
            event(new VtcRideAccepted((string) $company->id, $ride->id, $ride->reference, $driver->id));
        });

        Queue::assertPushed(
            SendPushNotificationJob::class,
            static fn (SendPushNotificationJob $job): bool => $job->employeeId === $passenger->id
                && $job->title === 'Driver on the way'
                && str_contains($job->body, $ride->reference)
                && ($job->metadata['type'] ?? null) === 'vtc.ride.accepted',
        );
    }

    public function test_accepted_skips_a_guest_ride_silently(): void
    {
        $company = $this->createCompany();
        [, $driver] = $this->createLinkedDriver($company);

        // Course invitée marketplace : aucun compte passager à notifier.
        $ride = $this->createRide($company, [
            'passenger_user_id' => null,
            'driver_id' => $driver->id,
            'status' => VtcRideStatus::Accepted->value,
        ]);

        $this->withTenantContext($company, function () use ($company, $ride, $driver): void {
            event(new VtcRideAccepted((string) $company->id, $ride->id, $ride->reference, $driver->id));
        });

        Queue::assertNothingPushed();
    }

    public function test_expired_notifies_the_passenger(): void
    {
        $company = $this->createCompany();
        $passenger = $this->createEmployee($company);

        $ride = $this->createRide($company, [
            'passenger_user_id' => $passenger->id,
            'status' => VtcRideStatus::Expired->value,
        ]);

        $this->withTenantContext($company, function () use ($company, $ride): void {
            event(new VtcRideExpired((string) $company->id, $ride->id, $ride->reference, 'dispatch_exhausted'));
        });

        Queue::assertPushed(
            SendPushNotificationJob::class,
            static fn (SendPushNotificationJob $job): bool => $job->employeeId === $passenger->id
                && $job->title === 'Aucun chauffeur disponible'
                && str_contains($job->body, $ride->reference),
        );
    }

    public function test_completed_notifies_passenger_and_driver_with_final_price(): void
    {
        $company = $this->createCompany();
        $passenger = $this->createEmployee($company);
        [$driverEmployee, $driver] = $this->createLinkedDriver($company);

        $ride = $this->createRide($company, [
            'passenger_user_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => VtcRideStatus::Completed->value,
            'final_price_minor' => 150_000,
            'currency' => 'XAF',
        ]);

        $this->withTenantContext($company, function () use ($company, $ride, $driver): void {
            event(new VtcRideCompleted(
                (string) $company->id,
                $ride->id,
                $ride->reference,
                $driver->id,
                150_000,
                'XAF',
            ));
        });

        // Passager : clôture + montant final formaté.
        Queue::assertPushed(
            SendPushNotificationJob::class,
            static fn (SendPushNotificationJob $job): bool => $job->employeeId === $passenger->id
                && $job->title === 'Course terminée'
                && str_contains($job->body, '1 500 XAF'),
        );

        // Chauffeur : récapitulatif de SA course.
        Queue::assertPushed(
            SendPushNotificationJob::class,
            static fn (SendPushNotificationJob $job): bool => $job->employeeId === $driverEmployee->id
                && str_contains($job->body, $ride->reference)
                && str_contains($job->body, '1 500 XAF'),
        );

        Queue::assertPushed(SendPushNotificationJob::class, 2);
    }

    public function test_unknown_ride_is_ignored_without_error(): void
    {
        $company = $this->createCompany();

        $this->withTenantContext($company, function () use ($company): void {
            event(new VtcRideAccepted((string) $company->id, 999_999, 'VTC-2026-000000', 1));
        });

        Queue::assertNothingPushed();
    }

    // ── Fixtures ────────────────────────────────────────────────────────

    private function createCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        return $company;
    }

    private function createEmployee(Company $company, string $preferredLanguage = 'fr'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
            'preferred_language' => $preferredLanguage,
        ]);

        return $employee;
    }

    /**
     * @return array{0: Employee, 1: VtcDriver}
     */
    private function createLinkedDriver(Company $company): array
    {
        $employee = $this->createEmployee($company);

        /** @var VtcDriver $driver */
        $driver = $this->withTenantContext($company, fn (): VtcDriver => VtcDriver::factory()->create([
            'user_id' => $employee->getAuthIdentifier(),
            'status' => VtcDriverStatus::Available->value,
            'name' => 'Chauffeur Test',
        ]));

        return [$employee, $driver];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRide(Company $company, array $attributes = []): VtcRide
    {
        return $this->withTenantContext($company, fn (): VtcRide => VtcRide::factory()->create(array_merge([
            'status' => VtcRideStatus::Dispatching->value,
            'requested_at' => now(),
        ], $attributes)));
    }
}
