<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-PAY-002 — Salary advance currency snapshot.
 *
 * `salary_advances` had no `currency` column of its own, so
 * `SalaryAdvanceResource` had to fall back to the *current* company
 * currency (or hardcoded 'DZD') at read time. That means an advance
 * receipt silently changes currency if the tenant's company currency is
 * ever edited after the advance was created/paid — which is wrong for a
 * financial document that must stay historically accurate.
 *
 * This adds a `currency` column snapshotted once at creation time (see
 * SalaryAdvanceService::create()) and backfills existing rows from their
 * owning company, so payment receipts remain accurate even if the
 * tenant's currency setting changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->char('currency', 3)->nullable()->after('amount');
        });

        // Backfill existing rows from their owning company's current
        // currency so historical advances are not left with a NULL value.
        //
        // `companies` lives in the `public` schema while this migration runs
        // against a tenant schema (search_path=<tenant>,public in
        // production, but CI runs tenant migrations with
        // search_path=shared_tenants only, no `public`). Schema-qualify the
        // join target so it resolves correctly regardless of search_path.
        DB::table('salary_advances')
            ->join('public.companies', 'public.companies.id', '=', 'salary_advances.company_id')
            ->update(['salary_advances.currency' => DB::raw('public.companies.currency')]);
    }

    public function down(): void
    {
        Schema::table('salary_advances', function (Blueprint $table): void {
            $table->dropColumn('currency');
        });
    }
};
