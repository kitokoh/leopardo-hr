<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'key' => 'employee_management',
                'title' => 'Gestion des EmployÃ©s',
                'description' => 'Module complet de gestion des employÃ©s : crÃ©ation, modification, consultation et suppression des profils employÃ©s.',
                'endpoint' => '/api/v1/employees',
                'http_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
                'parameters' => [
                    'list' => [
                        'page' => ['type' => 'integer', 'required' => false, 'default' => 1],
                        'per_page' => ['type' => 'integer', 'required' => false, 'default' => 15],
                        'search' => ['type' => 'string', 'required' => false],
                        'department_id' => ['type' => 'integer', 'required' => false],
                        'status' => ['type' => 'enum', 'required' => false, 'values' => ['active', 'suspended', 'archived']],
                    ],
                    'create' => [
                        'first_name' => ['type' => 'string', 'required' => true, 'max_length' => 100],
                        'last_name' => ['type' => 'string', 'required' => true, 'max_length' => 100],
                        'email' => ['type' => 'email', 'required' => true, 'max_length' => 150],
                        'phone' => ['type' => 'string', 'required' => false, 'max_length' => 30],
                        'department_id' => ['type' => 'integer', 'required' => false],
                        'position_id' => ['type' => 'integer', 'required' => false],
                        'manager_id' => ['type' => 'integer', 'required' => false],
                    ],
                ],
                'response_schema' => [
                    'employee' => [
                        'id' => 'integer',
                        'first_name' => 'string',
                        'last_name' => 'string',
                        'email' => 'string',
                        'phone' => 'string',
                        'status' => 'string',
                        'department' => 'object',
                        'position' => 'object',
                        'created_at' => 'datetime',
                        'updated_at' => 'datetime',
                    ],
                ],
                'permissions' => ['employees.view', 'employees.create', 'employees.update', 'employees.delete'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => null,
                'api_version' => '1.2.0',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'list',
                    'form_schema' => [
                        'fields' => [
                            ['name' => 'first_name', 'type' => 'text', 'label' => 'PrÃ©nom', 'required' => true],
                            ['name' => 'last_name', 'type' => 'text', 'label' => 'Nom', 'required' => true],
                            ['name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                            ['name' => 'phone', 'type' => 'tel', 'label' => 'TÃ©lÃ©phone', 'required' => false],
                        ],
                    ],
                    'list_schema' => [
                        'columns' => [
                            ['field' => 'first_name', 'label' => 'PrÃ©nom', 'sortable' => true],
                            ['field' => 'last_name', 'label' => 'Nom', 'sortable' => true],
                            ['field' => 'email', 'label' => 'Email', 'sortable' => true],
                            ['field' => 'status', 'label' => 'Statut', 'sortable' => true],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'attendance_management',
                'title' => 'Gestion des PrÃ©sences',
                'description' => 'Suivi et gestion des prÃ©sences, pointages et heures de travail des employÃ©s.',
                'endpoint' => '/api/v1/attendance',
                'http_methods' => ['GET', 'POST'],
                'parameters' => [
                    'list' => [
                        'employee_id' => ['type' => 'integer', 'required' => false],
                        'date_from' => ['type' => 'date', 'required' => false],
                        'date_to' => ['type' => 'date', 'required' => false],
                    ],
                    'checkin' => [
                        'employee_id' => ['type' => 'integer', 'required' => true],
                        'location' => ['type' => 'object', 'required' => false],
                    ],
                ],
                'response_schema' => [
                    'attendance' => [
                        'id' => 'integer',
                        'employee_id' => 'integer',
                        'check_in' => 'datetime',
                        'check_out' => 'datetime',
                        'hours_worked' => 'decimal',
                        'status' => 'string',
                    ],
                ],
                'permissions' => ['attendance.view', 'attendance.checkin'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => null,
                'api_version' => '1.2.0',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'dashboard',
                    'dashboard_widgets' => [
                        ['type' => 'checkin_button', 'priority' => 1],
                        ['type' => 'today_hours', 'priority' => 2],
                        ['type' => 'week_summary', 'priority' => 3],
                    ],
                ],
            ],
            [
                'key' => 'absence_requests',
                'title' => 'Demandes d\'Absence',
                'description' => 'Gestion des demandes de congÃ©s, absences et validation par les managers.',
                'endpoint' => '/api/v1/absences',
                'http_methods' => ['GET', 'POST', 'PUT'],
                'parameters' => [
                    'list' => [
                        'status' => ['type' => 'enum', 'required' => false, 'values' => ['pending', 'approved', 'rejected']],
                        'employee_id' => ['type' => 'integer', 'required' => false],
                    ],
                    'create' => [
                        'absence_type_id' => ['type' => 'integer', 'required' => true],
                        'start_date' => ['type' => 'date', 'required' => true],
                        'end_date' => ['type' => 'date', 'required' => true],
                        'reason' => ['type' => 'text', 'required' => false],
                    ],
                ],
                'response_schema' => [
                    'absence' => [
                        'id' => 'integer',
                        'employee_id' => 'integer',
                        'absence_type' => 'object',
                        'start_date' => 'date',
                        'end_date' => 'date',
                        'status' => 'string',
                        'reason' => 'string',
                    ],
                ],
                'permissions' => ['absences.view', 'absences.create', 'absences.approve'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => null,
                'api_version' => '1.2.0',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'form',
                    'form_schema' => [
                        'fields' => [
                            ['name' => 'absence_type_id', 'type' => 'select', 'label' => 'Type d\'absence', 'required' => true],
                            ['name' => 'start_date', 'type' => 'date', 'label' => 'Date de dÃ©but', 'required' => true],
                            ['name' => 'end_date', 'type' => 'date', 'label' => 'Date de fin', 'required' => true],
                            ['name' => 'reason', 'type' => 'textarea', 'label' => 'Motif', 'required' => false],
                        ],
                    ],
                ],
            ],
            [
                'key' => 'payroll_consultation',
                'title' => 'Consultation des Paies',
                'description' => 'Consultation des bulletins de paie et historique des rÃ©munÃ©rations.',
                'endpoint' => '/api/v1/payrolls',
                'http_methods' => ['GET'],
                'parameters' => [
                    'list' => [
                        'employee_id' => ['type' => 'integer', 'required' => false],
                        'year' => ['type' => 'integer', 'required' => false],
                        'month' => ['type' => 'integer', 'required' => false],
                    ],
                ],
                'response_schema' => [
                    'payroll' => [
                        'id' => 'integer',
                        'employee_id' => 'integer',
                        'period_start' => 'date',
                        'period_end' => 'date',
                        'gross_salary' => 'decimal',
                        'net_salary' => 'decimal',
                        'deductions' => 'array',
                        'bonuses' => 'array',
                    ],
                ],
                'permissions' => ['payrolls.view'],
                'mobile_version_min' => '1.1.0',
                'mobile_version_max' => null,
                'api_version' => '1.2.0',
                'status' => 'active',
                'metadata' => [
                    'ui_type' => 'detail',
                    'sensitive_data' => true,
                    'requires_additional_auth' => true,
                ],
            ],
            [
                'key' => 'legacy_reports',
                'title' => 'Rapports Anciens',
                'description' => 'Module de rapports de l\'ancienne version, maintenu pour compatibilitÃ©.',
                'endpoint' => '/api/v1/legacy/reports',
                'http_methods' => ['GET'],
                'parameters' => [
                    'list' => [
                        'type' => ['type' => 'enum', 'required' => true, 'values' => ['attendance', 'payroll']],
                    ],
                ],
                'response_schema' => [
                    'report' => [
                        'data' => 'array',
                        'generated_at' => 'datetime',
                    ],
                ],
                'permissions' => ['reports.view'],
                'mobile_version_min' => '1.0.0',
                'mobile_version_max' => '1.5.0',
                'api_version' => '1.0.0',
                'status' => 'deprecated',
                'metadata' => [
                    'ui_type' => 'generic',
                    'deprecation_notice' => 'Ce module sera supprimÃ© dans la version 2.0.0',
                ],
            ],
        ];

        foreach ($features as $featureData) {
            Feature::updateOrCreate(
                ['key' => $featureData['key']],
                $featureData
            );
        }

        $this->command->info('Features seeded successfully!');
    }
}
