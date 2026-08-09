<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plusieurs tables (absence_types, expense_items) ne definissaient que
 * created_at (timestampTz('created_at')->useCurrent()) dans leurs migrations
 * d'origine, sans updated_at. Les modeles Eloquent correspondants
 * (App\Modules\Planning\Domain\Models\AbsenceType,
 * App\Modules\Planning\Domain\Models\ExpenseItem) utilisent les timestamps
 * par defaut (created_at + updated_at), ce qui fait echouer tout
 * INSERT/UPDATE via Eloquent (colonne "updated_at" inexistante).
 *
 * expense_claims : App\Modules\Expense\Interfaces\Api\V1\Controllers\ExpenseClaimController::reject()
 * ecrit dans la colonne rejection_reason, absente de la migration d'origine
 * 2026_05_10_000007_create_loans_and_expenses_tables.php -> QueryException
 * 'column rejection_reason does not exist' au premier appel de
 * PUT /expense-claims/{id}/reject.
 */
return new class extends Migration
{
    private const TIMESTAMP_TABLES = ['absence_types', 'expense_items'];

    public function up(): void
    {
        foreach (self::TIMESTAMP_TABLES as $tableName) {
            $schema = resolveTableSchema($tableName);
            if ($schema !== null && ! schemaHasColumn($tableName, 'updated_at')) {
                Schema::table("{$schema}.{$tableName}", function (Blueprint $table): void {
                    // PostgreSQL n'a pas d'equivalent natif au "ON UPDATE CURRENT_TIMESTAMP" de
                    // MySQL dans le schema builder Laravel ; Eloquent gere deja updated_at
                    // automatiquement a chaque save(), donc un defaut useCurrent() (valeur
                    // initiale) suffit ici.
                    $table->timestampTz('updated_at')->nullable()->useCurrent();
                });
            }
        }

        $schema = resolveTableSchema('expense_claims');
        if ($schema !== null && ! schemaHasColumn('expense_claims', 'rejection_reason')) {
            Schema::table("{$schema}.expense_claims", function (Blueprint $table): void {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TIMESTAMP_TABLES as $tableName) {
            $schema = resolveTableSchema($tableName);
            if ($schema !== null && schemaHasColumn($tableName, 'updated_at')) {
                Schema::table("{$schema}.{$tableName}", function (Blueprint $table): void {
                    $table->dropColumn('updated_at');
                });
            }
        }

        $schema = resolveTableSchema('expense_claims');
        if ($schema !== null && schemaHasColumn('expense_claims', 'rejection_reason')) {
            Schema::table("{$schema}.expense_claims", function (Blueprint $table): void {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
