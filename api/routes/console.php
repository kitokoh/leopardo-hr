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
            // La table migrations doit vivre dans public (pas shared_tenants).
            DB::statement('SET search_path TO public');
            DB::purge();
            config(['database.connections.pgsql.search_path' => 'public']);
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
            DB::purge();
            config(['database.connections.pgsql.search_path' => 'shared_tenants,public']);
            DB::statement('SET search_path TO shared_tenants,public');
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
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DemoCompanySeeder',
                '--force' => true,
            ]);
        }

        $this->info('Leopardo migrate : OK');

        return 0;
    }
)->purpose('Run both public and tenant migrations (and optionally seeders) in one shot.');

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
