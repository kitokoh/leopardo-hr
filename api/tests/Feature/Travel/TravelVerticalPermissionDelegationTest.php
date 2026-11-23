<?php

declare(strict_types=1);

namespace Tests\Feature\Travel;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\Domain\Models\EmployeeModuleGrant;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Application\Actions\ActivateTravelAgencyAction;
use App\Modules\TravelAgency\Application\Actions\GenerateTripSeatsAction;
use App\Modules\TravelAgency\Domain\Enums\BookingStatus;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelTrip;
use App\Modules\TravelAgency\Domain\Models\TravelTripPrice;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #EVAL — délégation des permissions VERTICALES depuis l'interface RH.
 *
 * Constat de recette : `travel.agent` n'était attribuable à personne. Le
 * registre `ModuleKey` est fermé et HORIZONTAL, donc `PUT /module-grants`
 * refusait la clé en 422 ; le grant n'existait que posé automatiquement pour
 * le `principal` à l'activation. Un responsable d'agence ne pouvait donc pas
 * nommer un vendeur au guichet.
 *
 * Contrat vérifié ici :
 *  - `GET/PUT /employees/{id}/vertical-permissions` réservés au principal ;
 *  - le catalogue proposé ne contient QUE les permissions des verticales
 *    ACTIVES (fail-closed : verticale inactive ⇒ aucune clé) ;
 *  - une clé hors catalogue (ou horizontale) est refusée en 422 ;
 *  - le grant posé ouvre réellement la vente au guichet (bout en bout) ;
 *  - les deux surfaces (`module-grants` / `vertical-permissions`) ne se
 *    révoquent pas mutuellement.
 */
class TravelVerticalPermissionDelegationTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(bool $activate = true): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);

        if ($activate) {
            $company->setFeature('travelagency', true);
            $company->save();
        }

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    private function plainEmployee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'manager_role' => null,
        ]);

        return $employee;
    }

    /**
     * Recharge l'employé depuis la base pour que Sanctum reçoive une instance
     * fraîche (les grants posés par la requête précédente sont visibles).
     *
     * `fresh()` est typé `Employee|null` : on échoue explicitement plutôt que
     * de laisser PHPStan refuser l'argument de `Sanctum::actingAs()`.
     */
    private function reload(Employee $employee): Employee
    {
        $fresh = $employee->fresh();
        self::assertNotNull($fresh, 'Employé introuvable après rechargement.');

        return $fresh;
    }

    /**
     * @return array{trip: TravelTrip, class: TravelClass}
     */
    private function publishedTripWithPrice(Company $company): array
    {
        return app(TenantManager::class)->withinTenant($company, function (): array {
            $trip = TravelTrip::factory()->create(['status' => 'published', 'total_seats' => 40]);
            app(GenerateTripSeatsAction::class)->execute($trip);

            $class = TravelClass::factory()->create();
            TravelTripPrice::factory()->create([
                'trip_id' => $trip->id,
                'class_id' => $class->id,
                'adult_price_minor' => 15000,
            ]);

            return ['trip' => $trip->refresh(), 'class' => $class];
        });
    }

    // ── Catalogue exposé ────────────────────────────────────────────────

    public function test_catalogue_lists_only_active_vertical_permissions(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $employee = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->getJson("/api/v1/employees/{$employee->id}/vertical-permissions")
            ->assertOk()
            ->assertJsonPath('data.permission_keys', [])
            ->assertJsonPath('data.available', [
                'travel.agent' => 'Vente au guichet : réservations, encaissement et émission de billets',
                'travel.checkin' => 'Embarquement : check-in et contrôle des billets',
                'travel.manage' => "Administration de l'agence (réseau, trajets, tarifs, publication)",
                'travel.reports' => "Rapports d'exploitation internes de l'agence",
            ]);
    }

    public function test_catalogue_is_empty_when_the_vertical_is_inactive(): void
    {
        $company = $this->company(activate: false);
        $principal = $this->principal($company);
        $employee = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->getJson("/api/v1/employees/{$employee->id}/vertical-permissions")
            ->assertOk()
            ->assertJsonPath('data.available', []);
    }

    // ── Autorisation ────────────────────────────────────────────────────

    public function test_non_principal_cannot_read_or_write_vertical_permissions(): void
    {
        $company = $this->company();
        $this->principal($company);
        $employee = $this->plainEmployee($company);

        /** @var Employee $rh */
        $rh = Employee::factory()->managerRh()->create(['company_id' => $company->id]);

        Sanctum::actingAs($rh);
        $this->getJson("/api/v1/employees/{$employee->id}/vertical-permissions")->assertStatus(403);
        $this->putJson("/api/v1/employees/{$employee->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertStatus(403);

        Sanctum::actingAs($employee);
        $this->getJson("/api/v1/employees/{$employee->id}/vertical-permissions")->assertStatus(403);
    }

    // ── Validation fail-closed ──────────────────────────────────────────

    public function test_unknown_permission_key_is_rejected(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $employee = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->putJson("/api/v1/employees/{$employee->id}/vertical-permissions", [
            'permission_keys' => ['travel.warp_drive'],
        ])->assertStatus(422)->assertJsonValidationErrors('permission_keys.0');
    }

    public function test_horizontal_module_key_is_rejected_on_the_vertical_surface(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $employee = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        // `marketing` est un module HORIZONTAL : il n'a rien à faire ici.
        $this->putJson("/api/v1/employees/{$employee->id}/vertical-permissions", [
            'permission_keys' => ['marketing'],
        ])->assertStatus(422);
    }

    public function test_permission_of_an_inactive_vertical_is_rejected(): void
    {
        $company = $this->company(activate: false);
        $principal = $this->principal($company);
        $employee = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->putJson("/api/v1/employees/{$employee->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertStatus(422);
    }

    // ── Bout en bout : le grant ouvre réellement la vente ───────────────

    public function test_delegated_travel_agent_grant_opens_the_counter_sale(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $seller = $this->plainEmployee($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        // 1. Le vendeur ne peut pas vendre avant délégation.
        Sanctum::actingAs($seller);
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'eval-deleg-000',
            'passengers' => [
                ['full_name' => 'Avant Délégation', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(403);

        // 2. Le principal délègue `travel.agent` depuis l'interface.
        Sanctum::actingAs($principal);
        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertOk()->assertJsonPath('data.permission_keys', ['travel.agent']);

        $this->assertDatabaseHas('employee_module_grants', [
            'company_id' => $company->id,
            'employee_id' => $seller->id,
            'module_key' => 'travel.agent',
            'granted_by_employee_id' => $principal->id,
        ]);

        // 3. Le vendeur vend désormais au guichet.
        Sanctum::actingAs($this->reload($seller));
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'eval-deleg-001',
            'passengers' => [
                ['full_name' => 'Après Délégation', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(201)->assertJsonPath('data.status', BookingStatus::PENDING->value);
    }

    public function test_revocation_is_effective_in_one_gesture(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $seller = $this->plainEmployee($company);

        ['trip' => $trip, 'class' => $class] = $this->publishedTripWithPrice($company);

        Sanctum::actingAs($principal);
        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent', 'travel.checkin'],
        ])->assertOk();

        // PUT = remplacement : `travel.checkin` disparaît.
        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertOk()->assertJsonPath('data.permission_keys', ['travel.agent']);

        self::assertSame(0, EmployeeModuleGrant::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $seller->id)
            ->where('module_key', 'travel.checkin')
            ->count());

        // Révocation totale → la vente repasse en 403.
        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => [],
        ])->assertOk()->assertJsonPath('data.permission_keys', []);

        Sanctum::actingAs($this->reload($seller));
        $this->postJson('/api/v1/travel/bookings', [
            'trip_id' => $trip->id,
            'booking_source' => 'office',
            'idempotency_key' => 'eval-deleg-002',
            'passengers' => [
                ['full_name' => 'Révoqué', 'age_category' => 'adult', 'class_id' => $class->id],
            ],
        ])->assertStatus(403);
    }

    // ── Indépendance des deux surfaces ──────────────────────────────────

    public function test_module_grants_put_does_not_revoke_vertical_permissions(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $seller = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertOk();

        // Enregistrer les modules HORIZONTAUX ne doit pas toucher au grant métier.
        $this->putJson("/api/v1/employees/{$seller->id}/module-grants", [
            'module_keys' => ['marketing'],
        ])->assertOk();

        $this->assertDatabaseHas('employee_module_grants', [
            'company_id' => $company->id,
            'employee_id' => $seller->id,
            'module_key' => 'travel.agent',
        ]);
    }

    public function test_vertical_put_does_not_revoke_horizontal_modules(): void
    {
        $company = $this->company();
        $principal = $this->principal($company);
        $seller = $this->plainEmployee($company);

        Sanctum::actingAs($principal);

        $this->putJson("/api/v1/employees/{$seller->id}/module-grants", [
            'module_keys' => ['marketing', 'accounting'],
        ])->assertOk();

        $this->putJson("/api/v1/employees/{$seller->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertOk();

        $this->assertDatabaseHas('employee_module_grants', [
            'company_id' => $company->id,
            'employee_id' => $seller->id,
            'module_key' => 'marketing',
        ]);
        $this->assertDatabaseHas('employee_module_grants', [
            'company_id' => $company->id,
            'employee_id' => $seller->id,
            'module_key' => 'accounting',
        ]);
    }

    // ── Isolation multi-tenant ──────────────────────────────────────────

    public function test_cannot_delegate_on_an_employee_of_another_company(): void
    {
        $companyA = $this->company();
        $principalA = $this->principal($companyA);

        $companyB = $this->company();
        $employeeB = $this->plainEmployee($companyB);

        Sanctum::actingAs($principalA);

        $this->putJson("/api/v1/employees/{$employeeB->id}/vertical-permissions", [
            'permission_keys' => ['travel.agent'],
        ])->assertStatus(403);

        self::assertSame(0, EmployeeModuleGrant::query()
            ->where('employee_id', $employeeB->id)
            ->count());
    }

    // ── Activation : le principal reçoit bien les 4 permissions ─────────

    public function test_activation_grants_are_visible_on_the_delegation_screen(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $principal = $this->principal($company);

        app(TenantManager::class)->withinTenant($company, function () use ($company): void {
            app(ActivateTravelAgencyAction::class)->execute($company);
        });

        Sanctum::actingAs($this->reload($principal));

        $this->getJson("/api/v1/employees/{$principal->id}/vertical-permissions")
            ->assertOk()
            ->assertJsonPath('data.permission_keys', [
                'travel.agent',
                'travel.checkin',
                'travel.manage',
                'travel.reports',
            ]);
    }
}
