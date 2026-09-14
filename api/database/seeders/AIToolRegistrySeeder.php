<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AIToolRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $tools = [
            [
                'name' => 'get_employees',
                'description' => 'List employees with optional filters (status, department_id, limit).',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'archived']],
                        'department_id' => ['type' => 'integer'],
                        'limit' => ['type' => 'integer', 'default' => 20],
                    ],
                ]),
                'required_permissions' => '["employees.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'get_employee_details',
                'description' => 'Get detailed information about a specific employee by ID.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer', 'description' => 'The employee ID'],
                    ],
                    'required' => ['employee_id'],
                ]),
                'required_permissions' => '["employees.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'get_departments',
                'description' => 'List all departments in the company.',
                'parameters' => json_encode(['type' => 'object', 'properties' => new \stdClass]),
                'required_permissions' => '["departments.view"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'get_headcount',
                'description' => 'Get headcount statistics: total, active, and inactive employees.',
                'parameters' => json_encode(['type' => 'object', 'properties' => new \stdClass]),
                'required_permissions' => '["reports.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'search_employees',
                'description' => 'Search employees by name, email, or job title.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search term'],
                    ],
                    'required' => ['query'],
                ]),
                'required_permissions' => '["employees.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'get_attendance_today',
                'description' => 'Get today\'s attendance records.',
                'parameters' => json_encode(['type' => 'object', 'properties' => new \stdClass]),
                'required_permissions' => '["attendance.view"]',
                'required_role' => 'manager',
                'module' => 'attendance',
            ],
            [
                'name' => 'get_attendance_anomalies',
                'description' => 'Get attendance anomalies (late arrivals, missing check-outs, etc.).',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'period' => ['type' => 'string', 'enum' => ['today', 'week', 'month']],
                    ],
                ]),
                'required_permissions' => '["attendance.view"]',
                'required_role' => 'manager',
                'module' => 'attendance',
            ],
            [
                'name' => 'get_monthly_report',
                'description' => 'Get monthly attendance report.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'month' => ['type' => 'integer'],
                        'year' => ['type' => 'integer'],
                    ],
                ]),
                'required_permissions' => '["attendance.view"]',
                'required_role' => 'manager',
                'module' => 'attendance',
            ],
            [
                'name' => 'get_absences',
                'description' => 'List absence requests with optional status filter.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']],
                    ],
                ]),
                'required_permissions' => '["absences.view"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'create_absence',
                'description' => 'Create a new absence/leave request.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'start_date' => ['type' => 'string', 'format' => 'date'],
                        'end_date' => ['type' => 'string', 'format' => 'date'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['type', 'start_date', 'end_date'],
                ]),
                'required_permissions' => '["absences.create"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'approve_absence',
                'description' => 'Approve a pending absence request.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => ['type' => 'integer'],
                    ],
                    'required' => ['absence_id'],
                ]),
                'required_permissions' => '["absences.approve"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            // B3a (#6856) — outil écriture BC-06 LEAVE déclaré au contrat A3
            // (#6850). `parameters` aligné sur l'inputSchema du catalogue
            // Absence (AbsenceDecisionToolCatalog) ; exécution après
            // confirmation (flux A4) via WriteActionRunner → Actions
            // canoniques Planning (ApproveAbsence/RejectAbsence).
            [
                'name' => 'absence_decision',
                'description' => 'Approve or reject a pending absence request (manager, confirmation required before execution).',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => ['type' => 'integer', 'description' => 'The absence request ID'],
                        'decision' => ['type' => 'string', 'enum' => ['approve', 'reject'], 'description' => "Decision: 'approve' or 'reject'"],
                        'reason' => ['type' => 'string', 'description' => 'Rejection reason — required when decision=reject'],
                    ],
                    'required' => ['absence_id', 'decision'],
                ]),
                'required_permissions' => '["absences.approve"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            // B3b (#6857) — outil écriture BC-05 WORKFORCE déclaré au contrat
            // A3 (#6850). `parameters` aligné sur l'inputSchema du catalogue
            // Planning (ShiftAssignToolCatalog) ; exécution après confirmation
            // (flux A4) via WriteActionRunner, parité
            // ScheduleController::assignEmployees.
            [
                'name' => 'shift_assign',
                'description' => 'Assign a shift (schedule) to an employee of the tenant (manager, confirmation required before execution).',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'schedule_id' => ['type' => 'integer', 'description' => 'The schedule (shift) ID to assign'],
                        'employee_id' => ['type' => 'integer', 'description' => 'The employee ID to assign'],
                    ],
                    'required' => ['schedule_id', 'employee_id'],
                ]),
                'required_permissions' => '["schedules.assign"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            // B3c (#6858) — outil écriture BC-13 COMMS (annonce tenant, parité
            // AnnouncementController, exécution après confirmation) ; permissions
            // alignées sur `config ai.write_tools.notify_team`.
            [
                'name' => 'notify_team',
                'description' => 'Send a short message to a team (whole company for principal/RH, or one department) — confirmation required before send, rate-limited.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Message title (max 200)'],
                        'message' => ['type' => 'string', 'description' => 'Message body (max 5000)'],
                        'audience_type' => ['type' => 'string', 'enum' => ['company', 'department'], 'description' => "Target audience: 'company' (principal/RH only) or 'department' (default)"],
                        'department_id' => ['type' => 'integer', 'description' => 'Target department ID — required when audience_type=department'],
                    ],
                    'required' => ['title', 'message'],
                ]),
                'required_permissions' => '["schedules.assign"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'get_daily_summary',
                'description' => 'Get daily summary for an employee (attendance, tasks, estimations).',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer'],
                        'date' => ['type' => 'string', 'format' => 'date'],
                    ],
                ]),
                'required_permissions' => '["estimations.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'get_notifications',
                'description' => 'Get unread notifications for the current user.',
                'parameters' => json_encode(['type' => 'object', 'properties' => new \stdClass]),
                'required_permissions' => '["notifications.view"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'get_leave_balances',
                'description' => 'Get leave balances for the current user or a specific employee.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer'],
                    ],
                ]),
                'required_permissions' => '["leave.view"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'get_payroll_summary',
                'description' => 'Get payroll summary for a period.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'month' => ['type' => 'integer'],
                        'year' => ['type' => 'integer'],
                    ],
                ]),
                'required_permissions' => '["payroll.view"]',
                'required_role' => 'manager',
                'module' => 'payroll',
            ],
            // B2 (#6855) — outil lecture BC-07 PAYROLL déclaré au contrat A3
            // (#6850) : `parameters` aligné sur l'inputSchema de la définition
            // du catalogue Payroll (PayrollReadToolCatalog) ; handler dans
            // IntentEngine.
            [
                'name' => 'payroll_current_status',
                'description' => 'Aggregated payroll status of the tenant: last closed run (validated/paid) and current run (draft/calculating/processing/calculated/error) with progress (generated/validated payslips vs run headcount). No amounts, no personal data.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [],
                ]),
                'required_permissions' => '["payroll.view"]',
                'required_role' => 'manager',
                'module' => 'payroll',
            ],
            // B1 (#6854) — outils lecture BC-04 HR déclarés au contrat A3 (#6850).
            // `parameters` aligné sur l'inputSchema des AIToolDefinition du
            // catalogue HR (HrReadToolCatalog) ; handlers dans IntentEngine.
            [
                'name' => 'team_overview',
                'description' => 'Aggregated headcount view (company or manager scope): total, status, contract type and department breakdown. No personal data.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'department_id' => ['type' => 'integer', 'description' => 'Optional department filter'],
                    ],
                ]),
                'required_permissions' => '["employees.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'team_absences_recent',
                'description' => 'Recent absences in the manager scope over a period (default: last 30 days), with statuses and aggregates. Non-personal output.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'cancelled']],
                        'from' => ['type' => 'string', 'format' => 'date'],
                        'to' => ['type' => 'string', 'format' => 'date'],
                    ],
                ]),
                'required_permissions' => '["absences.view"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            [
                'name' => 'employee_leave_balance',
                'description' => 'Leave balance of an employee for a year (default: current year): balance, used and pending per absence type. Employees can only read their own balance.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer', 'description' => 'Target employee (default: caller). Managers only.'],
                        'year' => ['type' => 'integer', 'description' => 'Balance year (default: current year)'],
                    ],
                ]),
                'required_permissions' => '["leave.view"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            // A7 (#7377) — création d'un employé depuis l'assistant. Le rôle
            // créé est toujours `employee` (pas d'élévation de privilège) et
            // une invitation est envoyée : le tool le dit explicitement au LLM
            // pour que la confirmation annonce l'effet externe.
            [
                'name' => 'create_employee',
                'description' => 'Create an employee record (always role=employee) and send an invitation email so they set their own password. Requires a principal or RH manager. Cannot set a password, department or manager role.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string', 'description' => 'Given name'],
                        'last_name' => ['type' => 'string', 'description' => 'Family name'],
                        'email' => ['type' => 'string', 'description' => 'Professional email (invitation recipient), unique in the company'],
                        'phone' => ['type' => 'string', 'description' => 'Optional phone number'],
                        'job_title' => ['type' => 'string', 'description' => 'Optional job title'],
                        'contract_type' => ['type' => 'string', 'description' => 'Optional contract type, e.g. CDI, CDD, Stage'],
                        'hire_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Optional hire date (YYYY-MM-DD), defaults to today'],
                        'salary_type' => ['type' => 'string', 'enum' => ['fixed', 'hourly', 'daily'], 'description' => 'Optional salary type'],
                        'salary_base' => ['type' => 'number', 'description' => 'Optional base salary amount'],
                    ],
                    'required' => ['first_name', 'last_name', 'email'],
                ]),
                'required_permissions' => '["employees.create"]',
                'required_role' => 'manager',
                'module' => 'rh',
            ],
            // A8 (#7378) — pointage assisté. Un employé ne pointe que pour
            // lui-même ; un manager peut pointer pour son équipe.
            [
                'name' => 'check_in_employee',
                'description' => 'Record a check-in (clock-in) for an employee. Employees can only clock in for themselves; managers can clock in a team member. Subject to the company geofence/GPS rules.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer', 'description' => 'Target employee (managers only; defaults to the caller)'],
                        'gps_lat' => ['type' => 'number', 'description' => 'Optional GPS latitude'],
                        'gps_lng' => ['type' => 'number', 'description' => 'Optional GPS longitude'],
                        'note' => ['type' => 'string', 'description' => 'Optional punch note'],
                    ],
                ]),
                'required_permissions' => '["attendance.punch"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
            [
                'name' => 'check_out_employee',
                'description' => 'Record a check-out (clock-out) for an employee. Employees can only clock out for themselves; managers can clock out a team member. Subject to the company geofence/GPS rules.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer', 'description' => 'Target employee (managers only; defaults to the caller)'],
                        'gps_lat' => ['type' => 'number', 'description' => 'Optional GPS latitude'],
                        'gps_lng' => ['type' => 'number', 'description' => 'Optional GPS longitude'],
                        'note' => ['type' => 'string', 'description' => 'Optional punch note'],
                    ],
                ]),
                'required_permissions' => '["attendance.punch"]',
                'required_role' => 'employee',
                'module' => 'rh',
            ],
        ];

        foreach ($tools as $tool) {
            DB::table('ai_tool_registry')->updateOrInsert(
                ['name' => $tool['name']],
                array_merge($tool, [
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
