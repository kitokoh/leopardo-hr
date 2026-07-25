<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-MOB-006 — Salary advance supporting document.
 *
 * The absence workflow already stores a "proof" file
 * (`absences.proof_path`, see 2026_04_01_000103_create_attendance_absences_advances.php),
 * but salary advances never gained an equivalent column. The ticket's
 * acceptance criteria explicitly requires the manager to see "qui quoi
 * combien pourquoi et pieces" (who/what/how much/why and attachments) for
 * both absences and advances, so this closes that gap for advances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->string('proof_path', 255)->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->dropColumn('proof_path');
        });
    }
};
