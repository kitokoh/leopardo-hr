<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module HR — issue #5324 (workflow de départ) : les dates `last_work_day`
 * et `departed_at` de `employee_departures` sont rendues NULLABLES.
 *
 * Contexte : la migration initiale (2026_08_23_000007) les déclarait NOT
 * NULL alors que le modèle (`EmployeeDeparture`, docblock @property Carbon|null)
 * et le workflow réel autorisent un enregistrement de départ AVANT de
 * connaître le dernier jour travaillé (préavis en cours) et AVANT le départ
 * effectif (`departed_at` posé au moment de la révocations des accès).
 * Les tests Feature (`DepartureNoticeTest`) seedent sans `last_work_day` :
 * l'insert échouait en 23502 (not-null violation) — CI main rouge.
 *
 * Additive et idempotente (ALTER DROP NOT NULL, rejouable sans erreur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_departures', function (Blueprint $table): void {
            $table->date('last_work_day')->nullable()->change();
            $table->date('departed_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('employee_departures', function (Blueprint $table): void {
            $table->date('last_work_day')->nullable(false)->change();
            $table->date('departed_at')->nullable(false)->change();
        });
    }
};
