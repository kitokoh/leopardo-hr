<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plusieurs tables (absence_types, expense_items) ne definissaient que
 * created_at (timestampTz('created_at')->useCurrent()) dans leurs migrations
 * d'origine, sans updated_at. Les modeles Eloquent correspondants
 * (App\Modules\Absence\Domain\Models\AbsenceType,
 * App\Modules\Expense\Domain\Models\ExpenseItem) utilisent les timestamps
 * par defaut (created_at + updated_at), ce qui fait echouer tout
 * INSERT/UPDATE via Eloquent (colonne "updated_at" inexistante).
 */
return new class extends Migration
{
    private const TABLES = ['absence_types', 'expense_items'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'updated_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    // PostgreSQL n'a pas d'equivalent natif au "ON UPDATE CURRENT_TIMESTAMP" de
                    // MySQL dans le schema builder Laravel ; Eloquent gere deja updated_at
                    // automatiquement a chaque save(), donc un defaut useCurrent() (valeur
                    // initiale) suffit ici.
                    $table->timestampTz('updated_at')->nullable()->useCurrent();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'updated_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('updated_at');
                });
            }
        }
    }
};
