<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'leopardo:migrate {--fresh : Drop all tables before migrating} {--seed : Run base seeders after migrating} {--demo : Also seed DemoCompanySeeder (local/dev only)}',
    function () {
        $fresh = (bool) $this->option('fresh');
        $seed = (bool) $this->option('seed');
        $demo = (bool) $this->option('demo');

        if ($fresh) {
            $this->warn('--fresh : suppression du schema public et shared_tenants');
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP SCHEMA IF EXISTS shared_tenants CASCADE');
                DB::statement('DROP SCHEMA public CASCADE');
                DB::statement('CREATE SCHEMA public');
                DB::statement('CREATE SCHEMA shared_tenants');
            } else {
                $this->call('migrate:fresh', ['--force' => true]);
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE SCHEMA IF NOT EXISTS public');
            DB::statement('CREATE SCHEMA IF NOT EXISTS shared_tenants');

            // La table migrations doit vivre dans public (pas shared_tenants).
            config(['database.connections.pgsql.search_path' => 'public']);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            DB::statement('SET search_path TO public');
        }

        $this->info('Migrations schema public...');
        $publicCode = $this->call('migrate', [
            '--path' => 'database/migrations/public',
            '--force' => true,
        ]);

        if ($publicCode !== 0) {
            $this->error('Echec des migrations public.');

            return $publicCode;
        }

        if (DB::getDriverName() === 'pgsql') {
            // Keep tenant migrations on the tenant schema only: some public
            // tables have tenant-like names and can make Schema::hasTable()
            // skip creating the real shared_tenants table.
            config(['database.connections.pgsql.search_path' => 'shared_tenants']);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            DB::statement('SET search_path TO shared_tenants');
        }

        $this->info('Migrations schema shared_tenants...');
        $tenantCode = $this->call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        if ($tenantCode !== 0) {
            $this->error('Echec des migrations tenant.');

            return $tenantCode;
        }

        if ($seed) {
            $this->info('Seeders de base...');
            if (DB::getDriverName() === 'pgsql') {
                config(['database.connections.pgsql.search_path' => 'shared_tenants,public']);
                DB::purge('pgsql');
                DB::reconnect('pgsql');
                DB::statement('SET search_path TO shared_tenants,public');
            }

            $seedCode = $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DatabaseSeeder',
                '--force' => true,
            ]);

            if ($seedCode !== 0) {
                return $seedCode;
            }
        }

        if ($demo) {
            $this->info('Seed des donnees de demo...');
            if (DB::getDriverName() === 'pgsql') {
                config(['database.connections.pgsql.search_path' => 'shared_tenants,public']);
                DB::purge('pgsql');
                DB::reconnect('pgsql');
                DB::statement('SET search_path TO shared_tenants,public');
            }

            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DemoCompanySeeder',
                '--force' => true,
            ]);
        }

        $this->info('Leopardo migrate : OK');

        return 0;
    }
)->purpose('Run both public and tenant migrations (and optionally seeders) in one shot.');

// ──────────────────────────────────────────────
// Scheduled Jobs
// ──────────────────────────────────────────────
use Illuminate\Support\Facades\Schedule;

Schedule::command('billing:check-trials')->daily()->at('08:00');
Schedule::command('billing:check-overdue')->daily()->at('09:00');
Schedule::command('app:send-drip-emails')->daily()->at('10:00');
Schedule::command('billing:generate-invoices')->monthlyOn(1, '02:00');
Schedule::command('leave:accrue')->monthlyOn(1, '03:00');
Schedule::command('leave:carry-forward --year='.(now()->year - 1))->yearlyOn(1, 1, '04:00');
Schedule::command('contracts:alert-expiring')->daily()->at('07:00');
Schedule::command('attendance:auto-close --threshold=12')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// SmartAttendance — fermeture automatique des sessions GPS orphelines
Schedule::command('smart-attendance:auto-close --hours=14')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
Schedule::command('queue:health-check')
    ->everyFiveMinutes()
    ->when(fn (): bool => config('queue.default') === 'redis')
    ->withoutOverlapping();

Schedule::command('growth:approve-commissions')
    ->daily()
    ->at('04:00');

// Module Marketing — publication des social_posts planifies devenus dus
Schedule::command('marketing:publish-scheduled-posts')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// PA2-COMM-011 — publish scheduled company announcements that are due
Schedule::command('announcements:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('growth:archive-clicks --days=90')
    ->weekly();

// RGPD / Loi 18-07 — rétention des audit logs (24 mois par défaut, voir
// docs/security/POLITIQUE_RETENTION_DOCUMENTS.md, issue #1474).
Schedule::command('audit:purge --older-than=24')
    ->weekly()
    ->onOneServer();

Artisan::command('super-admin:reset-password {email} {password}', function (string $email, string $password) {
    DB::statement('SET search_path TO public');

    $affected = DB::table('super_admins')
        ->where('email', $email)
        ->update([
            'password_hash' => Hash::make($password),
        ]);

    if ($affected === 0) {
        $this->error("Aucun super admin trouvé pour {$email}");

        return 1;
    }

    $this->info("Mot de passe super admin mis à jour pour {$email}");

    return 0;
})->purpose('Reset a super admin password safely');
