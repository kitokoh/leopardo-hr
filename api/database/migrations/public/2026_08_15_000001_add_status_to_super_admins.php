<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA wave 2026-08-14 — T004 (#2229) : gestion des utilisateurs plateforme.
 * `super_admins` n'a aucun statut : les actions activate/deactivate/suspend
 * de l'API /platform/users ont besoin d'une colonne `status`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('super_admins') && ! Schema::hasColumn('super_admins', 'status')) {
            Schema::table('super_admins', function (Blueprint $table): void {
                $table->string('status', 20)->default('active')->after('email');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE super_admins ALTER COLUMN status SET DEFAULT \'active\'');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('super_admins') && Schema::hasColumn('super_admins', 'status')) {
            Schema::table('super_admins', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
    }
};
