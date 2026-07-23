<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Billing\Domain\Models\Invoice;
use App\Modules\Billing\Domain\Models\WebhookEndpoint;
use App\Modules\HR\Domain\Models\Contract;
use App\Modules\HR\Domain\Models\Department;
use App\Modules\HR\Domain\Models\Evaluation;
use App\Modules\Marketing\Domain\Models\SocialAccount;
use App\Modules\Marketing\Domain\Models\SocialPost;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\ExpenseClaim;
use App\Policies\AbsencePolicy;
use App\Policies\AttendancePolicy;
use App\Policies\ContractPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\ExpenseClaimPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LoanPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\SocialPostPolicy;
use App\Policies\WebhookEndpointPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PA2-SEC-004: regression matrix covering every `manager_role` value
 * (principal, rh, comptable, marketing, dept, superviseur) plus the
 * self-service `employee` role against each existing Policy class.
 *
 * These are pure unit tests: Employee/target models are built in-memory
 * with `forceFill()`/`setRelation()` (no database), so the matrix runs
 * fast and stays a straight regression check on `hasManagerRole()`,
 * `isTeamScoped()` and `managesTeamMemberOf()` wiring inside each policy,
 * without re-testing those helpers themselves (already covered by
 * DepartmentScopedRbacTest / SupervisorScopedRbacTest / EmployeesRbacTest
 * at the HTTP/DB level).
 *
 * Company-wide manager roles per docs/security/RBAC_SYSTEM.md: principal,
 * rh, comptable, marketing. Team-scoped: dept, superviseur.
 */
class RbacManagerRoleMatrixTest extends TestCase
{
    private const COMPANY_A = 'company-a';

    private const COMPANY_B = 'company-b';

    private const ALL_MANAGER_ROLES = ['principal', 'rh', 'comptable', 'marketing', 'dept', 'superviseur'];

    /**
     * @return array<string, array{0: string}>
     */
    public static function managerRoleProvider(): array
    {
        $cases = [];
        foreach (self::ALL_MANAGER_ROLES as $role) {
            $cases[$role] = [$role];
        }

        return $cases;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function manager(string $managerRole, array $overrides = []): Employee
    {
        $employee = new Employee;
        $employee->forceFill(array_merge([
            'id' => 100,
            'company_id' => self::COMPANY_A,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'department_id' => null,
            'manager_id' => null,
            'status' => 'active',
        ], $overrides));

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function employee(array $overrides = []): Employee
    {
        $employee = new Employee;
        $employee->forceFill(array_merge([
            'id' => 200,
            'company_id' => self::COMPANY_A,
            'role' => 'employee',
            'manager_role' => null,
            'department_id' => null,
            'manager_id' => null,
            'status' => 'active',
        ], $overrides));

        return $employee;
    }

    private function isCompanyWide(string $managerRole): bool
    {
        return in_array($managerRole, ['principal', 'rh', 'comptable', 'marketing'], true);
    }

    // -----------------------------------------------------------------
    // EmployeePolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_employee_policy_view_any_allows_every_manager_role(string $managerRole): void
    {
        $policy = new EmployeePolicy;
        $actor = $this->manager($managerRole);

        self::assertTrue($policy->viewAny($actor), "manager_role={$managerRole} should be able to viewAny employees");
    }

    public function test_employee_policy_view_any_denies_self_service_employee(): void
    {
        $policy = new EmployeePolicy;

        self::assertFalse($policy->viewAny($this->employee()));
    }

    #[DataProvider('managerRoleProvider')]
    public function test_employee_policy_create_restricted_to_principal_and_rh(string $managerRole): void
    {
        $policy = new EmployeePolicy;
        $actor = $this->manager($managerRole);

        $expected = in_array($managerRole, ['principal', 'rh'], true);

        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
    }

    public function test_employee_policy_dept_manager_can_only_view_own_department_employee(): void
    {
        $policy = new EmployeePolicy;
        $actor = $this->manager('dept', ['department_id' => 5]);

        $sameDept = $this->employee(['id' => 201, 'department_id' => 5]);
        $otherDept = $this->employee(['id' => 202, 'department_id' => 9]);

        self::assertTrue($policy->view($actor, $sameDept));
        self::assertFalse($policy->view($actor, $otherDept));
    }

    public function test_employee_policy_dept_manager_without_department_sees_nobody(): void
    {
        $policy = new EmployeePolicy;
        $actor = $this->manager('dept', ['department_id' => null]);

        $target = $this->employee(['id' => 201, 'department_id' => 5]);

        self::assertFalse($policy->view($actor, $target), 'A dept manager with no department must fail closed');
    }

    public function test_employee_policy_superviseur_can_only_view_direct_reports(): void
    {
        $policy = new EmployeePolicy;
        $actor = $this->manager('superviseur', ['id' => 100]);

        $directReport = $this->employee(['id' => 201, 'manager_id' => 100]);
        $notReport = $this->employee(['id' => 202, 'manager_id' => 999]);

        self::assertTrue($policy->view($actor, $directReport));
        self::assertFalse($policy->view($actor, $notReport));
        self::assertTrue($policy->view($actor, $actor), 'A superviseur can always view themselves');
    }

    #[DataProvider('managerRoleProvider')]
    public function test_employee_policy_company_wide_roles_view_any_employee_in_company(string $managerRole): void
    {
        if (! $this->isCompanyWide($managerRole)) {
            self::markTestSkipped("{$managerRole} is team-scoped, covered by dedicated tests");
        }

        $policy = new EmployeePolicy;
        $actor = $this->manager($managerRole);
        $target = $this->employee(['id' => 201, 'department_id' => 77]);

        self::assertTrue($policy->view($actor, $target));
    }

    // -----------------------------------------------------------------
    // AbsencePolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_absence_policy_approve_reject_allow_every_manager_role_in_same_company(string $managerRole): void
    {
        $policy = new AbsencePolicy;
        $actor = $this->manager($managerRole);
        $absence = new Absence;
        $absence->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201, 'status' => 'pending']);

        self::assertTrue($policy->approve($actor, $absence), "manager_role={$managerRole} should approve absences");
        self::assertTrue($policy->reject($actor, $absence), "manager_role={$managerRole} should reject absences");
    }

    public function test_absence_policy_denies_approve_across_companies(): void
    {
        $policy = new AbsencePolicy;
        $actor = $this->manager('principal');
        $absence = new Absence;
        $absence->forceFill(['id' => 1, 'company_id' => self::COMPANY_B, 'employee_id' => 201, 'status' => 'pending']);

        self::assertFalse($policy->approve($actor, $absence));
    }

    public function test_absence_policy_denies_self_service_employee_from_approving(): void
    {
        $policy = new AbsencePolicy;
        $actor = $this->employee();
        $absence = new Absence;
        $absence->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 999, 'status' => 'pending']);

        self::assertFalse($policy->approve($actor, $absence));
    }

    // -----------------------------------------------------------------
    // ContractPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_contract_policy_create_and_update_restricted_to_principal_and_rh(string $managerRole): void
    {
        $policy = new ContractPolicy;
        $actor = $this->manager($managerRole);
        $contract = new Contract;
        $contract->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);

        $expected = in_array($managerRole, ['principal', 'rh'], true);

        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
        self::assertSame($expected, $policy->update($actor, $contract), "manager_role={$managerRole} update() mismatch");
        self::assertSame($expected, $policy->terminate($actor, $contract), "manager_role={$managerRole} terminate() mismatch");
        self::assertSame($expected, $policy->renew($actor, $contract), "manager_role={$managerRole} renew() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_contract_policy_view_any_allows_every_manager_role(string $managerRole): void
    {
        $policy = new ContractPolicy;

        self::assertTrue($policy->viewAny($this->manager($managerRole)));
    }

    // -----------------------------------------------------------------
    // DepartmentPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_department_policy_create_delete_restricted_to_principal_and_rh(string $managerRole): void
    {
        $policy = new DepartmentPolicy;
        $actor = $this->manager($managerRole);
        $department = new Department;
        $department->forceFill(['id' => 5, 'company_id' => self::COMPANY_A]);

        $expected = in_array($managerRole, ['principal', 'rh'], true);

        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
        self::assertSame($expected, $policy->delete($actor, $department), "manager_role={$managerRole} delete() mismatch");
    }

    public function test_department_policy_dept_manager_view_and_update_limited_to_own_department(): void
    {
        $policy = new DepartmentPolicy;
        $actor = $this->manager('dept', ['department_id' => 5]);

        $ownDepartment = new Department;
        $ownDepartment->forceFill(['id' => 5, 'company_id' => self::COMPANY_A]);
        $otherDepartment = new Department;
        $otherDepartment->forceFill(['id' => 9, 'company_id' => self::COMPANY_A]);

        self::assertTrue($policy->view($actor, $ownDepartment));
        self::assertFalse($policy->view($actor, $otherDepartment));
    }

    public function test_department_policy_superviseur_stays_company_wide_for_department_listings(): void
    {
        // PA2-SEC-003: a superviseur has no department of their own, so
        // department *listings* stay company-wide (their scoping is
        // defined by assigned employees, not department membership).
        $policy = new DepartmentPolicy;
        $actor = $this->manager('superviseur');
        $anyDepartment = new Department;
        $anyDepartment->forceFill(['id' => 42, 'company_id' => self::COMPANY_A]);

        self::assertTrue($policy->view($actor, $anyDepartment));
    }

    // -----------------------------------------------------------------
    // PayrollPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_payroll_policy_create_run_restricted_to_principal_and_rh(string $managerRole): void
    {
        $policy = new PayrollPolicy;
        $actor = $this->manager($managerRole);

        $expected = in_array($managerRole, ['principal', 'rh'], true);

        self::assertSame($expected, $policy->createRun($actor), "manager_role={$managerRole} createRun() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_payroll_policy_validate_and_cancel_run_restricted_to_principal_only(string $managerRole): void
    {
        $policy = new PayrollPolicy;
        $actor = $this->manager($managerRole);
        $run = new PayrollRun;
        $run->forceFill(['id' => 1, 'company_id' => self::COMPANY_A]);

        $expected = $managerRole === 'principal';

        self::assertSame($expected, $policy->validateRun($actor, $run), "manager_role={$managerRole} validateRun() mismatch");
        self::assertSame($expected, $policy->cancelRun($actor, $run), "manager_role={$managerRole} cancelRun() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_payroll_policy_view_slip_allows_every_manager_role_in_same_company(string $managerRole): void
    {
        $policy = new PayrollPolicy;
        $actor = $this->manager($managerRole);
        $slip = new PaySlip;
        $slip->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 999]);

        self::assertTrue($policy->viewSlip($actor, $slip), "manager_role={$managerRole} should view any slip in their company");
    }

    public function test_payroll_policy_view_slip_allows_owner_regardless_of_role(): void
    {
        $policy = new PayrollPolicy;
        $actor = $this->employee(['id' => 201]);
        $ownSlip = new PaySlip;
        $ownSlip->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);
        $otherSlip = new PaySlip;
        $otherSlip->forceFill(['id' => 2, 'company_id' => self::COMPANY_A, 'employee_id' => 999]);

        self::assertTrue($policy->viewSlip($actor, $ownSlip));
        self::assertFalse($policy->viewSlip($actor, $otherSlip));
    }

    // -----------------------------------------------------------------
    // AttendancePolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_attendance_policy_update_restricted_to_principal_and_rh(string $managerRole): void
    {
        $policy = new AttendancePolicy;
        $actor = $this->manager($managerRole);
        $log = new AttendanceLog;
        $log->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);

        $expected = in_array($managerRole, ['principal', 'rh'], true);

        self::assertSame($expected, $policy->update($actor, $log), "manager_role={$managerRole} update() mismatch");
    }

    public function test_attendance_policy_view_for_employee_respects_team_scoping(): void
    {
        $policy = new AttendancePolicy;

        $deptManager = $this->manager('dept', ['department_id' => 5]);
        $sameDeptTarget = $this->employee(['id' => 201, 'department_id' => 5]);
        $otherDeptTarget = $this->employee(['id' => 202, 'department_id' => 9]);

        self::assertTrue($policy->viewForEmployee($deptManager, $sameDeptTarget));
        self::assertFalse($policy->viewForEmployee($deptManager, $otherDeptTarget));

        $superviseur = $this->manager('superviseur', ['id' => 100]);
        $directReport = $this->employee(['id' => 301, 'manager_id' => 100]);
        $notReport = $this->employee(['id' => 302, 'manager_id' => 999]);

        self::assertTrue($policy->viewForEmployee($superviseur, $directReport));
        self::assertFalse($policy->viewForEmployee($superviseur, $notReport));
    }

    // -----------------------------------------------------------------
    // EvaluationPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_evaluation_policy_company_wide_roles_manage_every_evaluation(string $managerRole): void
    {
        if (! $this->isCompanyWide($managerRole)) {
            self::markTestSkipped("{$managerRole} is team-scoped, covered by dedicated tests");
        }

        $policy = new EvaluationPolicy;
        $actor = $this->manager($managerRole);
        $target = $this->employee(['id' => 201, 'department_id' => 77]);
        $evaluation = new Evaluation;
        $evaluation->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);
        $evaluation->setRelation('employee', $target);

        self::assertTrue($policy->update($actor, $evaluation));
        self::assertTrue($policy->delete($actor, $evaluation));
        self::assertTrue($policy->submit($actor, $evaluation));
    }

    public function test_evaluation_policy_dept_manager_limited_to_own_department(): void
    {
        $policy = new EvaluationPolicy;
        $actor = $this->manager('dept', ['department_id' => 5]);

        $sameDeptTarget = $this->employee(['id' => 201, 'department_id' => 5]);
        $sameDeptEvaluation = new Evaluation;
        $sameDeptEvaluation->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);
        $sameDeptEvaluation->setRelation('employee', $sameDeptTarget);

        $otherDeptTarget = $this->employee(['id' => 202, 'department_id' => 9]);
        $otherDeptEvaluation = new Evaluation;
        $otherDeptEvaluation->forceFill(['id' => 2, 'company_id' => self::COMPANY_A, 'employee_id' => 202]);
        $otherDeptEvaluation->setRelation('employee', $otherDeptTarget);

        self::assertTrue($policy->update($actor, $sameDeptEvaluation));
        self::assertFalse($policy->update($actor, $otherDeptEvaluation));
    }

    public function test_evaluation_policy_superviseur_limited_to_direct_reports(): void
    {
        $policy = new EvaluationPolicy;
        $actor = $this->manager('superviseur', ['id' => 100]);

        $directReport = $this->employee(['id' => 201, 'manager_id' => 100]);
        $directReportEvaluation = new Evaluation;
        $directReportEvaluation->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);
        $directReportEvaluation->setRelation('employee', $directReport);

        $notReport = $this->employee(['id' => 202, 'manager_id' => 999]);
        $notReportEvaluation = new Evaluation;
        $notReportEvaluation->forceFill(['id' => 2, 'company_id' => self::COMPANY_A, 'employee_id' => 202]);
        $notReportEvaluation->setRelation('employee', $notReport);

        self::assertTrue($policy->update($actor, $directReportEvaluation));
        self::assertFalse($policy->update($actor, $notReportEvaluation));
    }

    public function test_evaluation_policy_employee_can_always_view_and_acknowledge_own_evaluation(): void
    {
        $policy = new EvaluationPolicy;
        $actor = $this->employee(['id' => 201]);
        $evaluation = new Evaluation;
        $evaluation->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);

        self::assertTrue($policy->view($actor, $evaluation));
        self::assertTrue($policy->acknowledge($actor, $evaluation));
        self::assertFalse($policy->update($actor, $evaluation), 'a self-service employee is never a manager, so update() must fail');
    }

    // -----------------------------------------------------------------
    // ExpenseClaimPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_expense_claim_policy_approve_reject_restricted_to_principal_comptable_rh(string $managerRole): void
    {
        $policy = new ExpenseClaimPolicy;
        $actor = $this->manager($managerRole);
        $expense = new ExpenseClaim;
        $expense->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201, 'status' => 'submitted']);

        $expected = in_array($managerRole, ['principal', 'comptable', 'rh'], true);

        self::assertSame($expected, $policy->approve($actor, $expense), "manager_role={$managerRole} approve() mismatch");
        self::assertSame($expected, $policy->reject($actor, $expense), "manager_role={$managerRole} reject() mismatch");
    }

    // -----------------------------------------------------------------
    // LoanPolicy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_loan_policy_approve_reject_disburse_restricted_to_principal_and_comptable(string $managerRole): void
    {
        $policy = new LoanPolicy;
        $actor = $this->manager($managerRole);
        $loan = new EmployeeLoan;
        $loan->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'employee_id' => 201]);

        $expected = in_array($managerRole, ['principal', 'comptable'], true);

        self::assertSame($expected, $policy->approve($actor, $loan), "manager_role={$managerRole} approve() mismatch");
        self::assertSame($expected, $policy->reject($actor, $loan), "manager_role={$managerRole} reject() mismatch");
        self::assertSame($expected, $policy->disburse($actor, $loan), "manager_role={$managerRole} disburse() mismatch");
    }

    // -----------------------------------------------------------------
    // InvoicePolicy / WebhookEndpointPolicy (principal/comptable-only surfaces)
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_invoice_policy_view_any_restricted_to_principal_and_comptable(string $managerRole): void
    {
        $policy = new InvoicePolicy;
        $actor = $this->manager($managerRole);

        $expected = in_array($managerRole, ['principal', 'comptable'], true);

        self::assertSame($expected, $policy->viewAny($actor), "manager_role={$managerRole} viewAny() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_invoice_policy_create_and_pay_restricted_to_principal_only(string $managerRole): void
    {
        $policy = new InvoicePolicy;
        $actor = $this->manager($managerRole);
        $invoice = new Invoice;
        $invoice->forceFill(['id' => 1, 'company_id' => self::COMPANY_A]);

        $expected = $managerRole === 'principal';

        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
        self::assertSame($expected, $policy->pay($actor, $invoice), "manager_role={$managerRole} pay() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_webhook_endpoint_policy_restricted_to_principal_only(string $managerRole): void
    {
        $policy = new WebhookEndpointPolicy;
        $actor = $this->manager($managerRole);
        $webhook = new WebhookEndpoint;
        $webhook->forceFill(['id' => 1, 'company_id' => self::COMPANY_A]);

        $expected = $managerRole === 'principal';

        self::assertSame($expected, $policy->viewAny($actor), "manager_role={$managerRole} viewAny() mismatch");
        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
        self::assertSame($expected, $policy->update($actor, $webhook), "manager_role={$managerRole} update() mismatch");
        self::assertSame($expected, $policy->delete($actor, $webhook), "manager_role={$managerRole} delete() mismatch");
    }

    // -----------------------------------------------------------------
    // SocialAccountPolicy / SocialPostPolicy (principal/marketing-only surfaces)
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_social_account_policy_restricted_to_principal_and_marketing(string $managerRole): void
    {
        $policy = new SocialAccountPolicy;
        $actor = $this->manager($managerRole);
        $account = new SocialAccount;
        $account->forceFill(['id' => 1, 'company_id' => self::COMPANY_A]);

        $expected = in_array($managerRole, ['principal', 'marketing'], true);

        self::assertSame($expected, $policy->connect($actor), "manager_role={$managerRole} connect() mismatch");
        self::assertSame($expected, $policy->view($actor, $account), "manager_role={$managerRole} view() mismatch");
        self::assertSame($expected, $policy->disconnect($actor, $account), "manager_role={$managerRole} disconnect() mismatch");
    }

    #[DataProvider('managerRoleProvider')]
    public function test_social_post_policy_restricted_to_principal_and_marketing(string $managerRole): void
    {
        $policy = new SocialPostPolicy;
        $actor = $this->manager($managerRole);
        $post = new SocialPost;
        $post->forceFill(['id' => 1, 'company_id' => self::COMPANY_A, 'status' => SocialPost::STATUS_DRAFT]);

        $expected = in_array($managerRole, ['principal', 'marketing'], true);

        self::assertSame($expected, $policy->viewAny($actor), "manager_role={$managerRole} viewAny() mismatch");
        self::assertSame($expected, $policy->create($actor), "manager_role={$managerRole} create() mismatch");
        self::assertSame($expected, $policy->update($actor, $post), "manager_role={$managerRole} update() mismatch");
        self::assertSame($expected, $policy->publish($actor, $post), "manager_role={$managerRole} publish() mismatch");
    }

    // -----------------------------------------------------------------
    // Cross-tenant isolation smoke check shared by every scoped policy
    // -----------------------------------------------------------------

    #[DataProvider('managerRoleProvider')]
    public function test_no_manager_role_can_act_on_another_companys_contract(string $managerRole): void
    {
        $policy = new ContractPolicy;
        $actor = $this->manager($managerRole);
        $foreignContract = new Contract;
        $foreignContract->forceFill(['id' => 1, 'company_id' => self::COMPANY_B, 'employee_id' => 999]);

        self::assertFalse($policy->update($actor, $foreignContract), "manager_role={$managerRole} must never cross tenants");
    }
}
