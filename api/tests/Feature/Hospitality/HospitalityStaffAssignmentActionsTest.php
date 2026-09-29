<?php

declare(strict_types=1);

namespace Tests\Feature\Hospitality;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\HospitalityManager\Application\Actions\AssignHospitalityStaffAction;
use App\Modules\HospitalityManager\Application\Actions\RemoveHospitalityStaffAssignmentAction;
use App\Modules\HospitalityManager\Application\Actions\UpdateHospitalityStaffAssignmentAction;
use App\Modules\HospitalityManager\Domain\Models\HospitalityProperty;
use App\Modules\HospitalityManager\Domain\Models\HospitalityPropertyStaff;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Actions des affectations staff ↔ établissement (BOS-024d, #8215) :
 * contrôle tenant (422), unicité (409), ré-affectation = restauration,
 * appartenance à l'établissement de la route (404), retrait = soft delete.
 */
class HospitalityStaffAssignmentActionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $otherCompany;

    private Employee $admin;

    private HospitalityProperty $property;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'FR', 'currency' => 'EUR']);
        $company->setFeature('hospitality', true);
        $company->save();
        $this->company = $company;

        /** @var Company $otherCompany */
        $otherCompany = Company::factory()->create(['country' => 'CM', 'currency' => 'XAF']);
        $otherCompany->setFeature('hospitality', true);
        $otherCompany->save();
        $this->otherCompany = $otherCompany;

        /** @var Employee $admin */
        $admin = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);
        $this->admin = $admin;

        /** @var HospitalityProperty $property */
        $property = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Hôtel Test',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);
        $this->property = $property;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'employee',
        ]);

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Employee $employee): array
    {
        return [
            'employee_id' => $employee->getKey(),
            'role' => 'reception',
        ];
    }

    public function test_assign_creates_the_assignment_with_employee_loaded(): void
    {
        $employee = $this->employee($this->company);

        $assignment = app(AssignHospitalityStaffAction::class)->execute(
            $this->property,
            $this->admin,
            $this->payload($employee),
        );

        $this->assertSame($employee->getKey(), $assignment->employee_id);
        $this->assertSame('reception', $assignment->role);
        $this->assertTrue($assignment->relationLoaded('employee'));
    }

    public function test_assign_rejects_an_employee_from_another_tenant(): void
    {
        $foreign = $this->employee($this->otherCompany);

        try {
            app(AssignHospitalityStaffAction::class)->execute($this->property, $this->admin, $this->payload($foreign));
            $this->fail('Un employé hors tenant doit être refusé.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertStringContainsString('EMPLOYEE_OUTSIDE_TENANT', (string) $exception->getMessage());
        }
    }

    public function test_assign_rejects_an_active_duplicate(): void
    {
        $employee = $this->employee($this->company);
        $action = app(AssignHospitalityStaffAction::class);
        $action->execute($this->property, $this->admin, $this->payload($employee));

        try {
            $action->execute($this->property, $this->admin, $this->payload($employee));
            $this->fail('Un doublon actif doit être refusé.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertStringContainsString('EMPLOYEE_ALREADY_ASSIGNED', (string) $exception->getMessage());
        }
    }

    public function test_reassign_after_removal_restores_the_soft_deleted_row(): void
    {
        $employee = $this->employee($this->company);
        $action = app(AssignHospitalityStaffAction::class);
        $first = $action->execute($this->property, $this->admin, $this->payload($employee));

        app(RemoveHospitalityStaffAssignmentAction::class)->execute($this->property, $first);
        $this->assertTrue($first->refresh()->trashed());

        $restored = $action->execute($this->property, $this->admin, $this->payload($employee));

        $this->assertSame($first->getKey(), $restored->getKey());
        $this->assertFalse($restored->trashed());
        $this->assertSame(
            1,
            HospitalityPropertyStaff::query()->withoutGlobalScopes()->withTrashed()->count(),
        );
    }

    public function test_update_changes_the_role(): void
    {
        $employee = $this->employee($this->company);
        $assignment = app(AssignHospitalityStaffAction::class)->execute($this->property, $this->admin, $this->payload($employee));

        $updated = app(UpdateHospitalityStaffAssignmentAction::class)->execute($this->property, $assignment, [
            'role' => 'housekeeping',
        ]);

        $this->assertSame('housekeeping', $updated->role);
        $this->assertTrue($updated->relationLoaded('employee'));
    }

    public function test_update_on_an_assignment_of_another_property_is_not_found(): void
    {
        $employee = $this->employee($this->company);
        $assignment = app(AssignHospitalityStaffAction::class)->execute($this->property, $this->admin, $this->payload($employee));

        /** @var HospitalityProperty $otherProperty */
        $otherProperty = HospitalityProperty::query()->withoutGlobalScopes()->create([
            'company_id' => $this->company->id,
            'name' => 'Hôtel Annexe',
            'code' => 'HTL-'.fake()->unique()->numerify('####'),
            'type' => 'hotel',
            'country' => 'FR',
            'currency' => 'EUR',
        ]);

        try {
            app(UpdateHospitalityStaffAssignmentAction::class)->execute($otherProperty, $assignment, ['role' => 'x']);
            $this->fail('Une affectation hors établissement doit répondre 404.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }
}
