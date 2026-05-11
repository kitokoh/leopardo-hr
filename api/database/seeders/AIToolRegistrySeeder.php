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
                'required_role' => 'employee',
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
                'required_role' => 'employee',
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
                'required_role' => 'employee',
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
                'required_role' => 'employee',
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
