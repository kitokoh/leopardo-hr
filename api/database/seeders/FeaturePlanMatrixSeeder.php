<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeaturePlanMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            ['feature_key' => 'employees', 'free' => [true, 5], 'pilot' => [true, 30], 'operations' => [true, 250], 'enterprise' => [true, null]],
            ['feature_key' => 'attendance', 'free' => [true, null], 'pilot' => [true, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'anomalies', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'geofence', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'monthly_report', 'free' => [false, null], 'pilot' => [true, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'absences', 'free' => [false, null], 'pilot' => [true, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'payroll', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'contracts', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'recruitment', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'training', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'tracking', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'ai_chat', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'ai_voice', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'webhooks', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'api_public', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'multi_site', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'custom_branding', 'free' => [false, null], 'pilot' => [false, null], 'operations' => [false, null], 'enterprise' => [true, null]],
        ];

        foreach ($matrix as $row) {
            $featureKey = $row['feature_key'];
            foreach (['free', 'pilot', 'operations', 'enterprise'] as $plan) {
                [$enabled, $limit] = $row[$plan];
                DB::table('feature_plan_matrix')->updateOrInsert(
                    ['feature_key' => $featureKey, 'plan' => $plan],
                    [
                        'enabled' => $enabled,
                        'limit_value' => $limit,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
