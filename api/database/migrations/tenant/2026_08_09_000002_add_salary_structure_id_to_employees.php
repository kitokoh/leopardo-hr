<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programme FOCUS — F-05/F-20 (issue #1587) : lier chaque employé à sa
 * structure salariale.
 *
 * Avant : calculateRun() payait TOUS les employés sur la première structure
 * active de l'entreprise (salary_structure_id n'existait que sur
 * salary_components). Désormais employees.salary_structure_id permet une
 * affectation par employé, avec repli sur la structure par défaut si non
 * affecté (comportement historique préservé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'salary_structure_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('salary_structure_id')->nullable()->after('site_id');
                $table->foreign('salary_structure_id')
                    ->references('id')
                    ->on('salary_structures')
                    ->nullOnDelete();
                $table->index(['company_id', 'salary_structure_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'salary_structure_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropForeign(['salary_structure_id']);
                $table->dropIndex(['company_id', 'salary_structure_id']);
                $table->dropColumn('salary_structure_id');
            });
        }
    }
};
