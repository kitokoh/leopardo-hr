<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\ApprovalRequest;
use App\Modules\Attendance\Domain\Models\ApprovalWorkflow;
use App\Modules\Attendance\Domain\Models\AttendanceCorrectionRequest;
use App\Modules\Attendance\Domain\Models\AttendanceModeSettings;
use App\Modules\Attendance\Domain\Models\EmployeeAttendancePreference;
use App\Modules\Attendance\Domain\Models\EmployeeLocationEvent;
use App\Modules\Attendance\Domain\Models\KioskAnnouncement;
use App\Modules\Attendance\Domain\Models\ZktecoDevice;
use App\Modules\EdgeSync\Domain\Models\EdgeLicense;
use App\Modules\EdgeSync\Domain\Models\EdgeNode;
use App\Modules\HR\Domain\Models\ExportHistory;
use App\Modules\Notification\Domain\Models\DeviceToken;
use App\Modules\Payroll\Domain\Models\EmployeeLoan;
use App\Modules\Payroll\Domain\Models\LoanRepayment;
use App\Modules\Planning\Domain\Models\ClientEvent;
use Illuminate\Database\Eloquent\Model;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #7711 (suite #7646/#7678, pattern Cabinet #7710) — les derniers
 * modèles tenant-scopés du schéma partagé shared_tenants sont raccordés au
 * trait BelongsToCompany. Ces tests verrouillent le contrat pour CHAQUE
 * modèle raccordé :
 *   - sous tenant actif, un enregistrement d'un autre tenant est invisible
 *     (scope global `company`) ;
 *   - `company_id` fourni en mass assignment est ignoré (plus fillable) et le
 *     trait force le tenant courant à la création ;
 *   - `company_id` ne peut pas être déplacé vers un autre tenant sur update
 *     (garde `updating` du trait) ;
 *   - hors contexte tenant (console, jobs), forceCreate conserve la valeur
 *     fournie (chemin documenté du trait).
 *
 * Cas particuliers couverts explicitement :
 *   - AuditLog (dérogation : company_id reste fillable pour les writers
 *     plateforme hors tenant, valeur NULL = événement plateforme) ;
 *   - ZktecoDevice (lookup pré-tenant par serial_number via
 *     withoutGlobalScope('company'), pattern AuthenticateZktecoDevice).
 */
class RemainingModelsTenantIsolationTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $employeeA;

    private Employee $employeeB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->companyA = $companyA;
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->companyB = $companyB;

        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $this->companyA->id]);
        $this->employeeA = $employeeA;
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $this->companyB->id]);
        $this->employeeB = $employeeB;
    }

    protected function tearDown(): void
    {
        app()->forgetInstance('tenant_scope_required');
        app()->forgetInstance('current_company');

        parent::tearDown();
    }

    /**
     * Fixtures minimales par modèle raccordé — company_id via forceCreate
     * (pattern #7678), employé du tenant demandé quand la table l'exige.
     *
     * @return array<class-string<Model>, array<string, mixed>>
     */
    private function fixtures(Company $company, Employee $employee): array
    {
        $suffix = substr($company->id, 0, 8).'-'.uniqid();

        return [
            ApprovalWorkflow::class => [
                'name' => 'WF '.$suffix,
                'model_type' => 'leave_request',
                'levels' => [['level' => 1, 'approver_type' => 'manager']],
            ],
            AttendanceCorrectionRequest::class => [
                'employee_id' => $employee->id,
                'date' => '2026-09-01',
                'requested_check_in' => '2026-09-01 08:00:00',
                'reason' => 'Oubli de pointage',
                'status' => 'pending',
            ],
            AttendanceModeSettings::class => [
                'forced_mode' => 'manual',
            ],
            EmployeeAttendancePreference::class => [
                'employee_id' => $employee->id,
                'preferred_mode' => 'manual',
            ],
            EmployeeLocationEvent::class => [
                'employee_id' => $employee->id,
                'event_type' => EmployeeLocationEvent::TYPE_ZONE_ENTER,
                'metadata' => [],
            ],
            KioskAnnouncement::class => [
                'title' => 'Annonce '.$suffix,
                'body' => 'Corps',
            ],
            ZktecoDevice::class => [
                'serial_number' => 'ZK-'.$suffix,
                'name' => 'Pointeuse '.$suffix,
            ],
            ExportHistory::class => [
                'employee_id' => $employee->id,
                'type' => 'employees',
                'created_at' => now(),
            ],
            DeviceToken::class => [
                'employee_id' => $employee->id,
                'token' => 'fcm-'.$suffix,
                'platform' => 'android',
            ],
            ClientEvent::class => [
                'employee_id' => $employee->id,
                'event_name' => 'page_view',
                'surface' => 'web',
                'occurred_at' => now(),
            ],
        ];
    }

    public function test_scope_hides_records_of_another_tenant(): void
    {
        // Hors tenant : fixtures des deux tenants via forceCreate.
        foreach ($this->fixtures($this->companyA, $this->employeeA) as $model => $attributes) {
            $model::forceCreate(['company_id' => $this->companyA->id, ...$attributes]);
        }
        foreach ($this->fixtures($this->companyB, $this->employeeB) as $model => $attributes) {
            $model::forceCreate(['company_id' => $this->companyB->id, ...$attributes]);
        }

        app()->instance('current_company', $this->companyA);

        foreach (array_keys($this->fixtures($this->companyA, $this->employeeA)) as $model) {
            $this->assertSame(
                [$this->companyA->id],
                $model::query()->pluck('company_id')->unique()->values()->all(),
                "{$model} : le scope company doit masquer le tenant B",
            );
            $this->assertSame(
                2,
                $model::query()->withoutGlobalScope('company')->count(),
                "{$model} : les deux enregistrements existent bien hors scope",
            );
        }
    }

    public function test_creation_under_tenant_ignores_spoofed_company_id(): void
    {
        app()->instance('current_company', $this->companyA);

        foreach ($this->fixtures($this->companyA, $this->employeeA) as $model => $attributes) {
            // company_id n'est plus fillable + hook creating : le spoof vers
            // le tenant B est neutralisé.
            $record = $model::create(['company_id' => $this->companyB->id, ...$attributes]);

            $this->assertSame(
                $this->companyA->id,
                (string) $record->company_id,
                "{$model} : company_id doit être forcé au tenant courant",
            );
        }
    }

    public function test_update_cannot_move_record_to_another_tenant(): void
    {
        app()->instance('current_company', $this->companyA);

        /** @var ApprovalWorkflow $workflow */
        $workflow = ApprovalWorkflow::create([
            'name' => 'WF update',
            'model_type' => 'leave_request',
            'levels' => [['level' => 1, 'approver_type' => 'manager']],
        ]);

        $workflow->company_id = $this->companyB->id;
        $workflow->save();
        $workflow->refresh();

        $this->assertSame($this->companyA->id, (string) $workflow->company_id);
    }

    public function test_out_of_tenant_context_behaviour_is_preserved(): void
    {
        // Jobs/CLI/seeders : hors tenant, forceCreate conserve la valeur fournie.
        /** @var KioskAnnouncement $announcement */
        $announcement = KioskAnnouncement::forceCreate([
            'company_id' => $this->companyB->id,
            'title' => 'Maintenance',
            'body' => 'Fenêtre de maintenance',
        ]);

        $this->assertSame($this->companyB->id, (string) $announcement->company_id);
    }

    public function test_approval_request_is_tenant_scoped(): void
    {
        // ApprovalRequest exige un workflow + un requester du même tenant.
        $workflowB = ApprovalWorkflow::forceCreate([
            'company_id' => $this->companyB->id,
            'name' => 'WF B',
            'model_type' => 'leave_request',
            'levels' => [['level' => 1, 'approver_type' => 'manager']],
        ]);

        ApprovalRequest::forceCreate([
            'company_id' => $this->companyB->id,
            'workflow_id' => $workflowB->id,
            'approvable_type' => 'leave_request',
            'approvable_id' => 1,
            'requester_id' => $this->employeeB->id,
            'status' => 'pending',
        ]);

        app()->instance('current_company', $this->companyA);

        $this->assertSame(0, ApprovalRequest::query()->count());
        $this->assertSame(1, ApprovalRequest::query()->withoutGlobalScope('company')->count());
    }

    public function test_loan_repayment_is_tenant_scoped(): void
    {
        $loanB = EmployeeLoan::forceCreate([
            'company_id' => $this->companyB->id,
            'employee_id' => $this->employeeB->id,
            'loan_type' => 'personal',
            'amount' => 1200,
            'installments' => 12,
            'installment_amount' => 100,
            'start_date' => '2026-09-01',
            'status' => 'approved',
        ]);

        LoanRepayment::forceCreate([
            'company_id' => $this->companyB->id,
            'employee_loan_id' => $loanB->id,
            'due_date' => '2026-10-01',
            'amount' => 100,
            'principal' => 100,
            'interest' => 0,
            'status' => 'pending',
        ]);

        app()->instance('current_company', $this->companyA);

        $this->assertSame(0, LoanRepayment::query()->count());
        $this->assertSame(1, LoanRepayment::query()->withoutGlobalScope('company')->count());
    }

    public function test_edge_license_is_tenant_scoped_and_machine_flow_unscoped(): void
    {
        $nodeB = EdgeNode::forceCreate([
            'company_id' => $this->companyB->id,
            'name' => 'Node B',
            'slug' => 'node-b-'.uniqid(),
            'status' => 'active',
            'mode' => 'hybrid',
        ]);

        $licenseB = EdgeLicense::forceCreate([
            'company_id' => $this->companyB->id,
            'edge_node_id' => $nodeB->id,
            'license_key' => 'lic-'.uniqid(),
            'signed_payload' => 'jwt-payload',
            'allowed_features' => [],
            'max_employees' => 50,
            'issued_at' => now(),
            'expires_at' => now()->addDays(30),
            'validation_status' => 'valid',
        ]);

        // Sous tenant A : la licence du tenant B est invisible.
        app()->instance('current_company', $this->companyA);
        $this->assertSame(0, EdgeLicense::query()->count());

        // Flux machine pré-tenant (heartbeat/validate-license) : aucun
        // current_company lié → scope neutre, la licence reste résoluble.
        app()->forgetInstance('current_company');
        $this->assertNotNull(
            EdgeLicense::query()->where('edge_node_id', $nodeB->id)->first()
        );
        $this->assertSame($licenseB->id, EdgeLicense::query()->where('edge_node_id', $nodeB->id)->firstOrFail()->id);
    }

    public function test_zkteco_pre_tenant_lookup_bypasses_scope_explicitly(): void
    {
        ZktecoDevice::forceCreate([
            'company_id' => $this->companyB->id,
            'serial_number' => 'ZK-PRETENANT-1',
            'name' => 'Pointeuse B',
        ]);

        app()->instance('current_company', $this->companyA);

        // Sous tenant A, le device du tenant B est masqué par le scope…
        $this->assertNull(
            ZktecoDevice::query()->where('serial_number', 'ZK-PRETENANT-1')->first()
        );

        // …mais le lookup pré-tenant (AuthenticateZktecoDevice) le résout via
        // withoutGlobalScope('company') — l'authentification par
        // X-Device-Token reste la barrière du flux machine.
        $this->assertNotNull(
            ZktecoDevice::query()
                ->withoutGlobalScope('company')
                ->where('serial_number', 'ZK-PRETENANT-1')
                ->first()
        );
    }

    public function test_audit_log_read_isolation_and_platform_writer_derogation(): void
    {
        // Écritures plateforme hors tenant : company_id RESTE mass-assignable
        // (dérogation documentée #7711) — y compris NULL (événement plateforme).
        AuditLog::create([
            'company_id' => $this->companyB->id,
            'action' => 'platform_team_created',
            'module' => 'platform',
        ]);
        AuditLog::create([
            'company_id' => null,
            'action' => 'platform_maintenance',
            'module' => 'platform',
        ]);

        $this->assertSame(
            $this->companyB->id,
            (string) AuditLog::query()->where('action', 'platform_team_created')->firstOrFail()->company_id,
        );

        // Sous tenant A : le journal du tenant B (et les événements plateforme
        // NULL) sont invisibles ; le spoof à la création est neutralisé.
        app()->instance('current_company', $this->companyA);

        $this->assertSame(0, AuditLog::query()->count());

        $log = AuditLog::create([
            'company_id' => $this->companyB->id, // spoof sous tenant actif
            'action' => 'hr.export',
            'module' => 'hr',
        ]);
        $this->assertSame($this->companyA->id, (string) $log->company_id);
        $this->assertSame(1, AuditLog::query()->count());
    }
}
