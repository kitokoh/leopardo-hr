<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeaturePlanMatrixSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            ['feature_key' => 'employees', 'trial' => [true, 10], 'starter' => [true, 30], 'business' => [true, 100], 'enterprise' => [true, null]],
            ['feature_key' => 'attendance', 'trial' => [true, null], 'starter' => [true, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'anomalies', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'geofence', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'monthly_report', 'trial' => [false, null], 'starter' => [true, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'absences', 'trial' => [false, null], 'starter' => [true, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'payroll', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'contracts', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'recruitment', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'training', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'tracking', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'ai_chat', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'ai_voice', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'webhooks', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'api_public', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
            ['feature_key' => 'multi_site', 'trial' => [false, null], 'starter' => [false, null], 'business' => [true, null], 'enterprise' => [true, null]],
            ['feature_key' => 'custom_branding', 'trial' => [false, null], 'starter' => [false, null], 'business' => [false, null], 'enterprise' => [true, null]],
        ];

        foreach ($matrix as $row) {
            $featureKey = $row['feature_key'];
            foreach (['trial', 'starter', 'business', 'enterprise'] as $plan) {
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
