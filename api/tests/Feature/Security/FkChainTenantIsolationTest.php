<?php

namespace Tests\Feature\Security;

use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Company;
use App\Models\Employee;
use App\Models\ExpenseClaim;
use App\Models\ExpenseItem;
use App\Models\PayrollRun;
use App\Models\PaySlip;
use App\Models\PaySlipLine;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class FkChainTenantIsolationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        $this->ensureFkIsolationTables();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_webhook_delivery_is_isolated_through_endpoint_company(): void
    {
        [$companyA, $companyB] = $this->companies();

        $endpointA = WebhookEndpoint::query()->forceCreate([
            'company_id' => $companyA->id,
            'url' => 'https://a.example.test/hook',
            'events' => ['employee.created'],
            'secret' => 'secret-a',
        ]);
        $endpointB = WebhookEndpoint::query()->forceCreate([
            'company_id' => $companyB->id,
            'url' => 'https://b.example.test/hook',
            'events' => ['employee.deleted'],
            'secret' => 'secret-b',
        ]);

        WebhookDelivery::query()->forceCreate([
            'webhook_endpoint_id' => $endpointA->id,
            'event' => 'employee.created',
            'payload' => ['id' => 1],
        ]);
        WebhookDelivery::query()->forceCreate([
            'webhook_endpoint_id' => $endpointB->id,
            'event' => 'employee.deleted',
            'payload' => ['id' => 2],
        ]);

        app()->instance('current_company', $companyA);

        $events = WebhookDelivery::query()
            ->whereHas('endpoint', fn ($query) => $query->where('company_id', $companyA->id))
            ->orderBy('id')
            ->pluck('event')
            ->all();

        self::assertSame(['employee.created'], $events);
    }

    public function test_pay_slip_line_is_isolated_through_pay_slip_company(): void
    {
        [$companyA, $companyB] = $this->companies();
        $employeeA = $this->employee($companyA, 'payroll-a@example.test');
        $employeeB = $this->employee($companyB, 'payroll-b@example.test');

        $slipA = $this->paySlip($companyA, $employeeA);
        $slipB = $this->paySlip($companyB, $employeeB);

        PaySlipLine::query()->forceCreate([
            'pay_slip_id' => $slipA->id,
            'name' => 'Base A',
            'type' => 'earning',
            'amount' => 1000,
        ]);
        PaySlipLine::query()->forceCreate([
            'pay_slip_id' => $slipB->id,
            'name' => 'Base B',
            'type' => 'earning',
            'amount' => 2000,
        ]);

        app()->instance('current_company', $companyA);

        $lines = PaySlipLine::query()
            ->whereHas('paySlip', fn ($query) => $query->where('company_id', $companyA->id))
            ->pluck('name')
            ->all();

        self::assertSame(['Base A'], $lines);
    }

    public function test_approval_decision_is_isolated_through_request_company(): void
    {
        [$companyA, $companyB] = $this->companies();
        $approverA = $this->employee($companyA, 'approver-a@example.test');
        $approverB = $this->employee($companyB, 'approver-b@example.test');

        $requestA = $this->approvalRequest($companyA, $approverA);
        $requestB = $this->approvalRequest($companyB, $approverB);

        ApprovalDecision::query()->forceCreate([
            'approval_request_id' => $requestA->id,
            'level' => 1,
            'approver_id' => $approverA->id,
            'decision' => 'approved',
            'comment' => 'A',
        ]);
        ApprovalDecision::query()->forceCreate([
            'approval_request_id' => $requestB->id,
            'level' => 1,
            'approver_id' => $approverB->id,
            'decision' => 'approved',
            'comment' => 'B',
        ]);

        app()->instance('current_company', $companyA);

        $comments = ApprovalDecision::query()
            ->whereHas('request', fn ($query) => $query->where('company_id', $companyA->id))
            ->pluck('comment')
            ->all();

        self::assertSame(['A'], $comments);
    }

    public function test_expense_item_is_isolated_through_claim_company(): void
    {
        [$companyA, $companyB] = $this->companies();
        $employeeA = $this->employee($companyA, 'expense-a@example.test');
        $employeeB = $this->employee($companyB, 'expense-b@example.test');

        $claimA = $this->expenseClaim($companyA, $employeeA, 'Claim A');
        $claimB = $this->expenseClaim($companyB, $employeeB, 'Claim B');

        ExpenseItem::query()->forceCreate([
            'expense_claim_id' => $claimA->id,
            'category' => 'meals',
            'description' => 'Lunch A',
            'amount' => 120,
            'date' => '2026-05-13',
        ]);
        ExpenseItem::query()->forceCreate([
            'expense_claim_id' => $claimB->id,
            'category' => 'meals',
            'description' => 'Lunch B',
            'amount' => 220,
            'date' => '2026-05-13',
        ]);

        app()->instance('current_company', $companyA);

        $items = ExpenseItem::query()
            ->whereHas('claim', fn ($query) => $query->where('company_id', $companyA->id))
            ->pluck('description')
            ->all();

        self::assertSame(['Lunch A'], $items);
    }

    /** @return array{0: Company, 1: Company} */
    private function companies(): array
    {
        return [$this->company('Company A'), $this->company('Company B')];
    }

    private function company(string $name): Company
    {
        return Company::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => Str::slug($name).'@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }

    private function employee(Company $company, string $email): Employee
    {
        return Employee::query()->forceCreate([
            'company_id' => $company->id,
            'email' => $email,
            'password_hash' => 'secret',
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    private function paySlip(Company $company, Employee $employee): PaySlip
    {
        $run = PayrollRun::query()->forceCreate([
            'company_id' => $company->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'country_code' => 'DZ',
            'status' => 'draft',
        ]);

        return PaySlip::query()->forceCreate([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'draft',
        ]);
    }

    private function approvalRequest(Company $company, Employee $requester): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::query()->forceCreate([
            'company_id' => $company->id,
            'name' => 'Expense approval',
            'model_type' => ExpenseClaim::class,
            'levels' => [['role' => 'rh']],
            'active' => true,
        ]);

        return ApprovalRequest::query()->forceCreate([
            'company_id' => $company->id,
            'workflow_id' => $workflow->id,
            'approvable_type' => ExpenseClaim::class,
            'approvable_id' => 1,
            'requester_id' => $requester->id,
            'current_level' => 1,
            'status' => 'pending',
        ]);
    }

    private function expenseClaim(Company $company, Employee $employee, string $title): ExpenseClaim
    {
        return ExpenseClaim::query()->forceCreate([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'title' => $title,
            'description' => $title,
            'currency' => 'DZD',
            'status' => 'draft',
        ]);
    }

    private function ensureFkIsolationTables(): void
    {
        if (Schema::hasTable('webhook_endpoints') === false) {
            Schema::create('webhook_endpoints', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('url', 500);
                $table->json('events');
                $table->text('secret');
                $table->boolean('active')->default(true);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestampTz('last_triggered_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('webhook_deliveries') === false) {
            Schema::create('webhook_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('webhook_endpoint_id');
                $table->string('event', 100);
                $table->json('payload');
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->timestampTz('delivered_at')->useCurrent();
            });
        }

        if (Schema::hasTable('pay_slip_lines') === false) {
            Schema::create('pay_slip_lines', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('pay_slip_id');
                $table->unsignedBigInteger('salary_component_id')->nullable();
                $table->string('name', 150);
                $table->string('type', 30);
                $table->decimal('base_amount', 14, 2)->default(0);
                $table->decimal('rate', 8, 4)->default(1);
                $table->decimal('amount', 14, 2)->default(0);
                $table->unsignedSmallInteger('order')->default(0);
            });
        }

        if (Schema::hasTable('approval_workflows') === false) {
            Schema::create('approval_workflows', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('name', 150);
                $table->string('model_type', 100);
                $table->json('levels');
                $table->decimal('auto_approve_below', 12, 2)->nullable();
                $table->unsignedSmallInteger('escalation_hours')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('approval_requests') === false) {
            Schema::create('approval_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('workflow_id');
                $table->string('approvable_type', 100);
                $table->unsignedBigInteger('approvable_id');
                $table->unsignedInteger('requester_id');
                $table->unsignedSmallInteger('current_level')->default(1);
                $table->string('status', 30)->default('pending');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('approval_decisions') === false) {
            Schema::create('approval_decisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('approval_request_id');
                $table->unsignedSmallInteger('level');
                $table->unsignedInteger('approver_id');
                $table->string('decision', 30);
                $table->text('comment')->nullable();
                $table->timestampTz('decided_at')->nullable();
                $table->timestampTz('created_at')->useCurrent();
            });
        }
    }
}
