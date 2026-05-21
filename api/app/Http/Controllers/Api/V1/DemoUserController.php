<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DemoUserController extends Controller
{
    public function index(): JsonResponse
    {
        if (app()->environment('production') && ! filter_var(config('app.demo_mode_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json(['message' => 'Demo mode is not available.'], 403);
        }

        return response()->json([
            'data' => [
                'super_admin' => [
                    'label' => 'Super Administrateur',
                    'email' => config('demo.super_admin_email', 'admin@leopardo-rh.com'),
                    'password' => 'password123',
                    'role' => 'super_admin',
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
                    ['email' => 'ahmed.benali@techcorp-algerie.dz', 'name' => 'Ahmed Benali', 'role' => 'manager', 'manager_role' => 'principal', 'password' => 'password123'],
                    ['email' => 'fatima.meziane@techcorp-algerie.dz', 'name' => 'Fatima Meziane', 'role' => 'manager', 'manager_role' => 'rh', 'password' => 'password123'],
                    ['email' => 'karim.aouad@techcorp-algerie.dz', 'name' => 'Karim Aouad', 'role' => 'employee', 'manager_role' => null, 'password' => 'password123'],
                ],
            ],
            [
                'name' => 'PharmaPlus Casablanca',
                'slug' => 'pharmaplus-casablanca',
                'country' => 'MA',
                'plan' => 'Business',
                'users' => [
                    ['email' => 'amina.tahiri@pharmaplus.ma', 'name' => 'Amina Tahiri', 'role' => 'manager', 'manager_role' => 'principal', 'password' => 'password123'],
                    ['email' => 'sara.mansouri@pharmaplus.ma', 'name' => 'Sara Mansouri', 'role' => 'manager', 'manager_role' => 'rh', 'password' => 'password123'],
                    ['email' => 'youssef.bennani@pharmaplus.ma', 'name' => 'Youssef Bennani', 'role' => 'employee', 'manager_role' => null, 'password' => 'password123'],
                ],
            ],
            [
                'name' => 'DigitalFlow Tunis',
                'slug' => 'digitalflow-tunis',
                'country' => 'TN',
                'plan' => 'Business',
                'users' => [
                    ['email' => 'sofiane.mrad@digitalflow.tn', 'name' => 'Sofiane Mrad', 'role' => 'manager', 'manager_role' => 'principal', 'password' => 'password123'],
                    ['email' => 'olfa.trabelsi@digitalflow.tn', 'name' => 'Olfa Trabelsi', 'role' => 'manager', 'manager_role' => 'rh', 'password' => 'password123'],
                    ['email' => 'aziz.khelifi@digitalflow.tn', 'name' => 'Aziz Khelifi', 'role' => 'employee', 'manager_role' => null, 'password' => 'password123'],
                ],
            ],
        ];
    }
}
