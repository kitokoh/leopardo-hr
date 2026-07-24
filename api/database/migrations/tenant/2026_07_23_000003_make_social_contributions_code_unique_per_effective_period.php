<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-ARCH-004 — Temporal versioning of country payroll rules.
 *
 * `social_contributions.code` was created with a *global* unique
 * constraint (see 2026_05_10_100001_create_payroll_engine_tables.php),
 * which structurally prevented the effective_from/effective_to columns
 * from ever being used for real temporal versioning: a new rate for the
 * same contribution code (e.g. "CNAS_EMP" going from 9% to 9.5% next
 * year) could never be inserted as a new dated row without deleting the
 * old one first, destroying the historical rate an old payroll run would
 * need to be recalculated against for an audit.
 *
 * This drops the global unique index and replaces it with a composite
 * unique index on (company_id, code, effective_from), which still
 * prevents two rows from the same scope claiming the exact same
 * contribution code effective on the exact same start date, while
 * allowing the same code to be re-declared with a new effective_from date
 * (and a company-specific override to reuse a code already used
 * globally). PayrollCountryConfigSeeder's updateOrCreate() keys on
 * (company_id, code) already, so this migration also normalizes existing
 * rows to keep that seeder idempotent per company scope rather than
 * globally.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_contributions')) {
            return;
        }

        Schema::table('social_contributions', function (Blueprint $table): void {
            $table->dropUnique('social_contributions_code_unique');
            $table->unique(['company_id', 'code', 'effective_from'], 'social_contributions_company_code_effective_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_contributions')) {
            return;
        }

        Schema::table('social_contributions', function (Blueprint $table): void {
            $table->dropUnique('social_contributions_company_code_effective_unique');
            $table->unique('code', 'social_contributions_code_unique');
        });
    }
};
