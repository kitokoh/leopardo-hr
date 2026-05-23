<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCompanyOnceSeeder extends Seeder
{
    private const LOCK_KEY = 'demo_company_seed_v2';

    private const DEMO_SLUGS = [
        'techcorp-algerie',
        'pharmaplus-casablanca',
        'digitalflow-tunis',
    ];

    public function run(): void
    {
        $isProduction = app()->environment('production');
        $disabled = filter_var(env('DISABLE_DEMO_SEEDING', false), FILTER_VALIDATE_BOOLEAN);

        if ($isProduction && $disabled) {
            $this->command?->info('DemoCompanyOnceSeeder skipped (DISABLE_DEMO_SEEDING=true).');

            return;
        }

        DB::statement('SET search_path TO public');

        $alreadyRan = DB::table('seed_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->exists();

        if ($alreadyRan) {
            $this->command?->info('DemoCompanyOnceSeeder skipped (already executed).');

            return;
        }

        $existingDemoSlugs = DB::table('companies')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->pluck('slug');

        if ($existingDemoSlugs->count() === count(self::DEMO_SLUGS)) {
            DB::table('seed_locks')->updateOrInsert(
                ['lock_key' => self::LOCK_KEY],
                [
                    'ran_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command?->warn('DemoCompanyOnceSeeder skipped (demo companies already exist).');

            return;
        }

        DB::table('seed_locks')->insert([
            'lock_key' => self::LOCK_KEY,
            'ran_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app()->instance('leopardo.demo_seed_once', true);
            $this->call(DemoCompanySeeder::class);

            DB::statement('SET search_path TO public');
            DB::table('seed_locks')
                ->where('lock_key', self::LOCK_KEY)
                ->update([
                    'ran_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $throwable) {
            DB::statement('SET search_path TO public');
            DB::table('seed_locks')
                ->where('lock_key', self::LOCK_KEY)
                ->delete();

            throw $throwable;
        }
    }
}
