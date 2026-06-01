<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoCompanyOnceSeeder extends Seeder
{
    private const LOCK_KEY = 'demo_company_seed_v2';

    private const DEMO_SLUGS = [
        'techcorp-algerie',
        'pharmaplus-casablanca',
        'digitalflow-tunis',
    ];

    private const DEMO_SUPER_ADMIN_PASSWORD = 'password123';

    public function run(): void
    {
        $isProduction = app()->environment('production');
        $disabled = filter_var(env('DISABLE_DEMO_SEEDING', false), FILTER_VALIDATE_BOOLEAN);

        if ($isProduction && $disabled) {
            $this->command?->info('DemoCompanyOnceSeeder skipped (DISABLE_DEMO_SEEDING=true).');

            return;
        }

        DB::statement('SET search_path TO public');

        $this->syncDemoSuperAdmin();

        $existingDemoSlugs = DB::table('companies')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->pluck('slug');

        $alreadyRan = DB::table('seed_locks')
            ->where('lock_key', self::LOCK_KEY)
            ->exists();

        if ($alreadyRan && $existingDemoSlugs->count() === count(self::DEMO_SLUGS)) {
            $this->backfillDemoLeaveBalances();
            $this->backfillDemoLaunchReadinessSignals();
            $this->command?->info('DemoCompanyOnceSeeder skipped (already executed).');

            return;
        }

        if ($alreadyRan) {
            DB::table('seed_locks')->where('lock_key', self::LOCK_KEY)->delete();
            $this->command?->warn('DemoCompanyOnceSeeder stale lock cleared (demo companies missing).');
        }

        if ($existingDemoSlugs->count() === count(self::DEMO_SLUGS)) {
            $this->backfillDemoLeaveBalances();
            $this->backfillDemoLaunchReadinessSignals();
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
            $this->backfillDemoLaunchReadinessSignals();

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

    private function backfillDemoLaunchReadinessSignals(): void
    {
        $companies = DB::table('companies')
            ->whereIn('slug', self::DEMO_SLUGS)
            ->get(['id', 'slug', 'metadata']);

        if ($companies->isEmpty() || ! $this->sharedTableExists('employees')) {
            return;
        }

        $updatedPayroll = 0;
        $createdKiosks = 0;
        $createdEvents = 0;
        $updatedGeofences = 0;

        foreach ($companies as $company) {
            $metadata = is_string($company->metadata ?? null)
                ? json_decode((string) $company->metadata, true)
                : [];
            $metadata = is_array($metadata) ? $metadata : [];

            if (! isset($metadata['attendance_geofence'])) {
                $metadata['attendance_geofence'] = [
                    'lat' => 36.7538,
                    'lng' => 3.0588,
                    'radius_meters' => 180,
                    'label' => 'Site demo '.$company->slug,
                ];

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['metadata' => json_encode($metadata)]);
                $updatedGeofences++;
            }

            if ($this->sharedColumnExists('employees', 'salary_base')) {
                $updatedPayroll += DB::table($this->sharedTable('employees'))
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->where(function ($query): void {
                        $query->whereNull('salary_base')
                            ->orWhere('salary_base', '<=', 0);
                    })
                    ->update([
                        'salary_base' => 75000,
                        'salary_type' => 'fixed',
                    ]);
            }

            if ($this->sharedTableExists('attendance_kiosks')) {
                $hasKiosk = DB::table($this->sharedTable('attendance_kiosks'))
                    ->where('company_id', $company->id)
                    ->where('status', 'active')
                    ->exists();

                if (! $hasKiosk) {
                    $deviceCode = strtoupper(substr(str_replace('-', '', (string) $company->slug), 0, 18)).'-KIOSK-01';
                    $row = [
                        'company_id' => $company->id,
                        'name' => 'Kiosque demo '.$company->slug,
                        'location_label' => 'Accueil demo',
                        'device_code' => $deviceCode,
                        'status' => 'active',
                        'biometric_mode' => 'fingerprint',
                        'trusted_device_label' => 'Tablette reception demo',
                        'last_seen_at' => now()->subMinutes(3),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($this->sharedColumnExists('attendance_kiosks', 'sync_token_hash')) {
                        $row['sync_token_hash'] = Hash::make($deviceCode.'-demo-sync');
                    }

                    DB::table($this->sharedTable('attendance_kiosks'))->insert($row);
                    $createdKiosks++;
                }
            }

            if ($this->sharedTableExists('client_events')) {
                $hasRecentEvent = DB::table($this->sharedTable('client_events'))
                    ->where('company_id', $company->id)
                    ->where('occurred_at', '>=', now()->subDays(7))
                    ->exists();

                if (! $hasRecentEvent) {
                    $employeeId = DB::table($this->sharedTable('employees'))
                        ->where('company_id', $company->id)
                        ->where('status', 'active')
                        ->orderBy('id')
                        ->value('id');

                    $row = [
                        'company_id' => $company->id,
                        'employee_id' => $employeeId,
                        'event_name' => 'launch_readiness_backfilled',
                        'surface' => 'mobile',
                        'session_id' => 'demo-'.$company->slug.'-launch-readiness',
                        'duration_ms' => 1200,
                        'properties' => json_encode(['demo' => true, 'source' => 'DemoCompanyOnceSeeder']),
                        'user_agent' => 'LeopardoDemoSeeder/launch-readiness',
                        'occurred_at' => now()->subMinutes(10),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if ($this->sharedColumnExists('client_events', 'ip_address')) {
                        $row['ip_address'] = '127.0.0.1';
                    }

                    DB::table($this->sharedTable('client_events'))->insert($row);
                    $createdEvents++;
                }
            }
        }

        $total = $updatedPayroll + $createdKiosks + $createdEvents + $updatedGeofences;

        if ($total > 0) {
            $this->command?->info("DemoCompanyOnceSeeder backfilled launch readiness signals: payroll={$updatedPayroll}, kiosks={$createdKiosks}, events={$createdEvents}, geofences={$updatedGeofences}.");
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

    private function syncDemoSuperAdmin(): void
    {
        if (! config('app.demo_mode_enabled', true)) {
            return;
        }

        if (! $this->publicTableExists('super_admins')) {
            return;
        }

        $email = config('demo.super_admin_email', env('SUPER_ADMIN_EMAIL', 'admin@leopardo-rh.com'));
        $superAdmin = DB::table('super_admins')->where('email', $email)->first();

        if (! $superAdmin) {
            return;
        }

        $updates = [];

        if (! Hash::check(self::DEMO_SUPER_ADMIN_PASSWORD, (string) $superAdmin->password_hash)) {
            $updates['password_hash'] = Hash::make(self::DEMO_SUPER_ADMIN_PASSWORD);
        }

        if ($this->publicColumnExists('super_admins', 'two_fa_secret') && $superAdmin->two_fa_secret !== null) {
            $updates['two_fa_secret'] = null;
        }

        if ($updates === []) {
            return;
        }

        DB::table('super_admins')->where('email', $email)->update($updates);
        $this->command?->info("Demo super-admin credentials synced for {$email}.");
    }

    private function publicTableExists(string $table): bool
    {
        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.tables
                where table_schema = ?
                  and table_name = ?
            ) as exists',
            ['public', $table]
        );

        return (bool) ($result->exists ?? false);
    }

    private function publicColumnExists(string $table, string $column): bool
    {
        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.columns
                where table_schema = ?
                  and table_name = ?
                  and column_name = ?
            ) as exists',
            ['public', $table, $column]
        );

        return (bool) ($result->exists ?? false);
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

    private function sharedColumnExists(string $table, string $column): bool
    {
        $result = DB::selectOne(
            'select exists (
                select 1
                from information_schema.columns
                where table_schema = ?
                  and table_name = ?
                  and column_name = ?
            ) as exists',
            ['shared_tenants', $table, $column]
        );

        return (bool) ($result->exists ?? false);
    }

    private function sharedTable(string $table): string
    {
        return 'shared_tenants.'.$table;
    }
}
