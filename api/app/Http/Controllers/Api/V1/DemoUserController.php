<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DemoUserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'super_admin' => [
                    'label' => 'Super Administrateur',
                    'email' => config('demo.super_admin_email', 'admin@leopardo-rh.com'),
                    'password' => 'password123',
                    'role' => 'super_admin',
                    'surface' => 'admin-platform',
                    'primary_path' => '/platform',
                    'use_cases' => [
                        'Administrer les tenants',
                        'Suivre les plans et demandes clients',
                        'Controler la sante globale de la plateforme',
                    ],
                ],
                'companies' => $this->demoCompanies(),
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function demoCompanies(): array
    {
        return [
            [
                'name' => 'TechCorp Algerie SARL',
                'slug' => 'techcorp-algerie',
                'country' => 'DZ',
                'plan' => 'Starter',
                'users' => [
                    $this->persona('Ahmed Benali', 'ahmed.benali@techcorp-algerie.dz', 'manager', 'principal', 'web-manager', '/dashboard', [
                        'Dashboard dirigeant',
                        'Readiness lancement',
                        'Paie et exports',
                    ]),
                    $this->persona('Fatima Meziane', 'fatima.meziane@techcorp-algerie.dz', 'manager', 'rh', 'web-manager', '/dashboard', [
                        'Employes et absences',
                        'Analytics communication',
                        'Onboarding RH',
                    ]),
                    $this->persona('Samir Boukhalfa', 'samir.boukhalfa@techcorp-algerie.dz', 'manager', 'dept', 'web-manager', '/dashboard', [
                        'Equipe departement',
                        'Absences equipe',
                        'Projets et taches',
                    ]),
                    $this->persona('Lina Haddad', 'lina.haddad@techcorp-algerie.dz', 'manager', 'comptable', 'web-manager', '/dashboard', [
                        'Paie',
                        'Exports bancaires',
                        'Suivi financier RH',
                    ]),
                    $this->persona('Nassim Cheriet', 'nassim.cheriet@techcorp-algerie.dz', 'manager', 'superviseur', 'kiosk-supervisor', '/biometrics', [
                        'Pointage terrain',
                        'Kiosk',
                        'Demandes biometrie',
                    ]),
                    $this->persona('Karim Aouad', 'karim.aouad@techcorp-algerie.dz', 'employee', null, 'mobile-employee', '/me', [
                        'Self-service employe',
                        'Pointage mobile',
                        'Notifications et absences',
                    ]),
                ],
            ],
            [
                'name' => 'PharmaPlus Casablanca',
                'slug' => 'pharmaplus-casablanca',
                'country' => 'MA',
                'plan' => 'Business',
                'users' => [
                    $this->persona('Amina Tahiri', 'amina.tahiri@pharmaplus.ma', 'manager', 'principal', 'web-manager', '/dashboard', [
                        'Dashboard dirigeant',
                        'Readiness lancement',
                    ]),
                    $this->persona('Sara Mansouri', 'sara.mansouri@pharmaplus.ma', 'manager', 'rh', 'web-manager', '/dashboard', [
                        'Employes et absences',
                        'Communication interne',
                    ]),
                    $this->persona('Rachid Benjelloun', 'rachid.benjelloun@pharmaplus.ma', 'manager', 'comptable', 'web-manager', '/dashboard', [
                        'Paie',
                        'Exports',
                    ]),
                    $this->persona('Youssef Bennani', 'youssef.bennani@pharmaplus.ma', 'employee', null, 'mobile-employee', '/me', [
                        'Self-service employe',
                        'Notifications',
                    ]),
                ],
            ],
            [
                'name' => 'DigitalFlow Tunis',
                'slug' => 'digitalflow-tunis',
                'country' => 'TN',
                'plan' => 'Business',
                'users' => [
                    $this->persona('Sofiane Mrad', 'sofiane.mrad@digitalflow.tn', 'manager', 'principal', 'web-manager', '/dashboard', [
                        'Dashboard dirigeant',
                        'Readiness lancement',
                    ]),
                    $this->persona('Olfa Trabelsi', 'olfa.trabelsi@digitalflow.tn', 'manager', 'rh', 'web-manager', '/dashboard', [
                        'Employes et absences',
                        'Communication interne',
                    ]),
                    $this->persona('Marwen Chakroun', 'marwen.chakroun@digitalflow.tn', 'manager', 'superviseur', 'kiosk-supervisor', '/biometrics', [
                        'Pointage terrain',
                        'Demandes biometrie',
                    ]),
                    $this->persona('Aziz Khelifi', 'aziz.khelifi@digitalflow.tn', 'employee', null, 'mobile-employee', '/me', [
                        'Self-service employe',
                        'Pointage mobile',
                    ]),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function persona(
        string $name,
        string $email,
        string $role,
        ?string $managerRole,
        string $surface,
        string $primaryPath,
        array $useCases,
    ): array {
        return [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'manager_role' => $managerRole,
            'password' => 'password123',
            'surface' => $surface,
            'primary_path' => $primaryPath,
            'use_cases' => $useCases,
        ];
    }
}
