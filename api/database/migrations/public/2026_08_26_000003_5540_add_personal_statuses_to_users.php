<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5540 — Onboarding personnel multi-statuts.
 *
 * Ajoute `personal_statuses` (JSON) à la table `users` pour stocker
 * les statuts cumulables d'un utilisateur : student, employee, entrepreneur,
 * seeking_employment. Défaut : tableau vide.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (! Schema::hasColumn('users', 'personal_statuses')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->json('personal_statuses')->nullable()->after('status');
            });

            // Initialise les lignes existantes avec un tableau vide
            DB::table('users')
                ->whereNull('personal_statuses')
                ->update(['personal_statuses' => json_encode([])]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('SET search_path TO public');
        }

        if (Schema::hasColumn('users', 'personal_statuses')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('personal_statuses');
            });
        }
    }
};
