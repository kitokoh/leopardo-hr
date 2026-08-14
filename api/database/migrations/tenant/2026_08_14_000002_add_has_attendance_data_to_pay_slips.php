<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1816 (F-20) — trace la source du décompte des jours travaillés :
 * `pay_slips.has_attendance_data` (true = actual_days_worked issu des logs
 * de présence réels AttendanceLog ; false = fallback prorata contrat).
 *
 * Additive et idempotente (pattern 00015) : n'altère pas les colonnes
 * existantes et peut être rejouée sans effet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table('pay_slips', static function (Blueprint $table): void {
                $table->boolean('has_attendance_data')->default(false)->after('overtime_hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pay_slips', 'has_attendance_data')) {
            Schema::table('pay_slips', static function (Blueprint $table): void {
                $table->dropColumn('has_attendance_data');
            });
        }
    }
};
