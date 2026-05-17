<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CotisationSimulationController extends Controller
{
    private const COUNTRY_RATES = [
        'DZ' => [
            'employee' => ['cnas' => 0.09],
            'employer' => ['cnas' => 0.26],
            'irg_brackets' => [
                ['min' => 0, 'max' => 20000, 'rate' => 0],
                ['min' => 20001, 'max' => 40000, 'rate' => 0.20],
                ['min' => 40001, 'max' => 80000, 'rate' => 0.30],
                ['min' => 80001, 'max' => 160000, 'rate' => 0.35],
                ['min' => 160001, 'max' => PHP_INT_MAX, 'rate' => 0.40],
            ],
        ],
        'MA' => [
            'employee' => ['cnss' => 0.0448, 'amo' => 0.0226],
            'employer' => ['cnss' => 0.0898, 'amo' => 0.0340],
            'ir_brackets' => [
                ['min' => 0, 'max' => 30000, 'rate' => 0],
                ['min' => 30001, 'max' => 50000, 'rate' => 0.10],
                ['min' => 50001, 'max' => 60000, 'rate' => 0.20],
                ['min' => 60001, 'max' => 80000, 'rate' => 0.30],
                ['min' => 80001, 'max' => 180000, 'rate' => 0.34],
                ['min' => 180001, 'max' => PHP_INT_MAX, 'rate' => 0.38],
            ],
        ],
        'FR' => [
            'employee' => ['securite_sociale' => 0.0705, 'csg_crds' => 0.097],
            'employer' => ['securite_sociale' => 0.1530, 'assurance_chomage' => 0.0405],
            'pas_brackets' => [
                ['min' => 0, 'max' => 10777, 'rate' => 0],
                ['min' => 10778, 'max' => 27478, 'rate' => 0.11],
                ['min' => 27479, 'max' => 78570, 'rate' => 0.30],
                ['min' => 78571, 'max' => 168994, 'rate' => 0.41],
                ['min' => 168995, 'max' => PHP_INT_MAX, 'rate' => 0.45],
            ],
        ],
        'TN' => [
            'employee' => ['cnss' => 0.0918],
            'employer' => ['cnss' => 0.1657],
            'irpp_brackets' => [
                ['min' => 0, 'max' => 5000, 'rate' => 0],
                ['min' => 5001, 'max' => 20000, 'rate' => 0.26],
                ['min' => 20001, 'max' => 30000, 'rate' => 0.28],
                ['min' => 30001, 'max' => 50000, 'rate' => 0.32],
                ['min' => 50001, 'max' => PHP_INT_MAX, 'rate' => 0.35],
            ],
        ],
        'TR' => [
            'employee' => ['sgk' => 0.14],
            'employer' => ['sgk' => 0.2050],
            'gelir_brackets' => [
                ['min' => 0, 'max' => 110000, 'rate' => 0.15],
                ['min' => 110001, 'max' => 230000, 'rate' => 0.20],
                ['min' => 230001, 'max' => 870000, 'rate' => 0.27],
                ['min' => 870001, 'max' => 3000000, 'rate' => 0.35],
                ['min' => 3000001, 'max' => PHP_INT_MAX, 'rate' => 0.40],
            ],
        ],
        'SN' => [
            'employee' => ['ipres' => 0.056],
            'employer' => ['ipres' => 0.084, 'css' => 0.07],
            'ir_brackets' => [
                ['min' => 0, 'max' => 630000, 'rate' => 0],
                ['min' => 630001, 'max' => 1500000, 'rate' => 0.20],
                ['min' => 1500001, 'max' => 4000000, 'rate' => 0.30],
                ['min' => 4000001, 'max' => 8000000, 'rate' => 0.35],
                ['min' => 8000001, 'max' => PHP_INT_MAX, 'rate' => 0.37],
            ],
        ],
    ];

    public function simulate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'country_code' => 'required|string|in:DZ,MA,FR,TN,TR,SN',
        ]);

        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];
        $rates = self::COUNTRY_RATES[$countryCode] ?? self::COUNTRY_RATES['DZ'];

        $employeeContributions = [];
        $totalEmployeeRate = 0.0;
        foreach ($rates['employee'] as $name => $rate) {
            $amount = round($gross * $rate, 2);
            $employeeContributions[] = [
                'name' => $name,
                'rate' => $rate,
                'amount' => $amount,
            ];
            $totalEmployeeRate += $rate;
        }

        $employerContributions = [];
        $totalEmployerRate = 0.0;
        foreach ($rates['employer'] as $name => $rate) {
            $amount = round($gross * $rate, 2);
            $employerContributions[] = [
                'name' => $name,
                'rate' => $rate,
                'amount' => $amount,
            ];
            $totalEmployerRate += $rate;
        }

        $totalEmployeeDeduction = round($gross * $totalEmployeeRate, 2);
        $totalEmployerCost = round($gross * $totalEmployerRate, 2);
        $netBeforeTax = round($gross - $totalEmployeeDeduction, 2);

        return response()->json([
            'data' => [
                'country_code' => $countryCode,
                'gross_salary' => $gross,
                'employee_contributions' => $employeeContributions,
                'employer_contributions' => $employerContributions,
                'total_employee_deduction' => $totalEmployeeDeduction,
                'total_employer_cost' => $totalEmployerCost,
                'net_before_tax' => $netBeforeTax,
                'total_cost_employer' => round($gross + $totalEmployerCost, 2),
            ],
        ]);
    }
}
