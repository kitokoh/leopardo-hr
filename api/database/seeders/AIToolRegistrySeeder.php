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
