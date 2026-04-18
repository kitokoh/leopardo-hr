<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCompanyOnceSeeder extends Seeder
{
    private const LOCK_KEY = 'demo_company_seed_v1';

    public function run(): void
    {
        $isProduction = app()->environment('production');
        $allowOnce = filter_var(env('DEMO_SEED_ONCE', false), FILTER_VALIDATE_BOOLEAN);

        if ($isProduction && ! $allowOnce) {
            $this->command?->info('DemoCompanyOnceSeeder skipped (production without DEMO_SEED_ONCE=true).');
            return;
        }

        DB::statement("SET search_path TO public");

        $alreadyRan = DB::table('seed_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->exists();

        if ($alreadyRan) {
            $this->command?->info('DemoCompanyOnceSeeder skipped (already executed).');
            return;
        }

        DB::table('seed_locks')->insert([
            'lock_key' => self::LOCK_KEY,
            'ran_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->call(DemoCompanySeeder::class);

            DB::statement("SET search_path TO public");
            DB::table('seed_locks')
                ->where('lock_key', self::LOCK_KEY)
                ->update([
                    'ran_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $throwable) {
            DB::statement("SET search_path TO public");
            DB::table('seed_locks')
                ->where('lock_key', self::LOCK_KEY)
                ->delete();

            throw $throwable;
        }
    }
}
