<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $runsSchema = resolveTableSchema('payroll_runs');
        if ($runsSchema !== null && ! schemaHasColumn('payroll_runs', 'rules_version')) {
            Schema::table("{$runsSchema}.payroll_runs", function (Blueprint $table): void {
                $table->string('rules_version', 32)->nullable()->after('country_code');
                $table->date('rules_period')->nullable()->after('rules_version');
                $table->string('rules_identifier', 150)->nullable()->after('rules_period');
                $table->index(['country_code', 'rules_period']);
            });
        }

        $slipsSchema = resolveTableSchema('pay_slips');
        if ($slipsSchema !== null && ! schemaHasColumn('pay_slips', 'rules_version')) {
            Schema::table("{$slipsSchema}.pay_slips", function (Blueprint $table): void {
                $table->string('rules_version', 32)->nullable()->after('period_end');
                $table->date('rules_period')->nullable()->after('rules_version');
                $table->string('rules_identifier', 150)->nullable()->after('rules_period');
                $table->index(['company_id', 'rules_period']);
            });
        }
    }

    public function down(): void
    {
        $runsSchema = resolveTableSchema('payroll_runs');
        if ($runsSchema !== null && schemaHasColumn('payroll_runs', 'rules_version')) {
            Schema::table("{$runsSchema}.payroll_runs", function (Blueprint $table): void {
                $table->dropIndex(['country_code', 'rules_period']);
                $table->dropColumn(['rules_version', 'rules_period', 'rules_identifier']);
            });
        }

        $slipsSchema = resolveTableSchema('pay_slips');
        if ($slipsSchema !== null && schemaHasColumn('pay_slips', 'rules_version')) {
            Schema::table("{$slipsSchema}.pay_slips", function (Blueprint $table): void {
                $table->dropIndex(['company_id', 'rules_period']);
                $table->dropColumn(['rules_version', 'rules_period', 'rules_identifier']);
            });
        }
    }
};
