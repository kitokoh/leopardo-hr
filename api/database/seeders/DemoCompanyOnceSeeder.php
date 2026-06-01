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

        $existingDemoSlugs = DB::table('companies')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->pluck('slug');

        $alreadyRan = DB::table('seed_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->exists();

        if ($alreadyRan && $existingDemoSlugs->count() === count(self::DEMO_SLUGS)) {
            $this->backfillDemoLeaveBalances();
            $this->command?->info('DemoCompanyOnceSeeder skipped (already executed).');

            return;
        }

        if ($alreadyRan) {
            DB::table('seed_locks')->where('lock_key', self::LOCK_KEY)->delete();
            $this->command?->warn('DemoCompanyOnceSeeder stale lock cleared (demo companies missing).');
        }

        if ($existingDemoSlugs->count() === count(self::DEMO_SLUGS)) {
            $this->backfillDemoLeaveBalances();
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
            $this->backfillDemoLeaveBalances();

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

    private function backfillDemoLeaveBalances(): void
    {
        if (
            ! $this->sharedTableExists('employees')
            || ! $this->sharedTableExists('absence_types')
            || ! $this->sharedTableExists('leave_balances')
        ) {
            return;
        }

        $companies = DB::table('companies')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->get(['id', 'slug']);

        if ($companies->isEmpty()) {
            return;
        }

        $year = (int) now()->format('Y');
        $created = 0;

        foreach ($companies as $company) {
            $annualTypeId = DB::table($this->sharedTable('absence_types'))
                ->where('company_id', $company->id)
                ->where(function ($query): void {
                    $query->where('code', 'like', 'CA_%')
                        ->orWhere('name', 'like', 'Conge annuel%');
                })
                ->orderBy('id')
                ->value('id');

            if (! $annualTypeId) {
                continue;
            }

            $employeeIds = DB::table($this->sharedTable('employees'))
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            foreach ($employeeIds as $index => $employeeId) {
                $exists = DB::table($this->sharedTable('leave_balances'))
                    ->where('company_id', $company->id)
                    ->where('employee_id', $employeeId)
                    ->where('absence_type_id', $annualTypeId)
                    ->where('year', $year)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table($this->sharedTable('leave_balances'))->insert([
                    'company_id' => $company->id,
                    'employee_id' => $employeeId,
                    'absence_type_id' => $annualTypeId,
                    'balance' => max(8, 18 - ($index % 5)),
                    'used' => $index % 3,
                    'pending' => 0,
                    'year' => $year,
                    'updated_at' => now(),
                ]);

                $created++;
            }
        }

        if ($created > 0) {
            $this->command?->info("DemoCompanyOnceSeeder backfilled {$created} demo leave balances.");
        }
    }

    private function sharedTableExists(string $table): bool
    {
        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.tables
                where table_schema = ?
                  and table_name = ?
            ) as exists',
            ['shared_tenants', $table]
        );

        return (bool) ($result->exists ?? false);
    }

    private function sharedTable(string $table): string
    {
        return 'shared_tenants.'.$table;
    }
}
