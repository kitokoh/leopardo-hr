<?php

namespace Tests\Unit;

use Database\Seeders\HrModelSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PA2-COUNTRY-010: HrModelSeeder must keep covering every country already
 * supported by a dedicated CountryRulesInterface implementation/zone class
 * (DZ/MA/TN/FR/TR from earlier tickets, plus SN/CM/CI added here for
 * Senegal/CEMAC/CEDEAO), so admin-configurable HR model templates
 * (cotisations, IR brackets, leave rules, holidays, working hours) exist
 * out of the box for every country a demo/company can be provisioned in,
 * without breaking the existing demo seeding flow.
 */
class HrModelSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // HrModelSeeder::run() unconditionally issues `SET search_path TO
        // public` (Postgres-only syntax, matching every other seeder in this
        // app), so it cannot run as-is against the sqlite driver used by the
        // default PHPUnit test suite. Skip on non-pgsql drivers rather than
        // faking Postgres-only behaviour in sqlite.
        if (DB::getDriverName() !== 'pgsql') {
            self::markTestSkipped('HrModelSeeder requires the pgsql driver (uses `SET search_path`).');
        }

        DB::statement('SET search_path TO public');

        if (! Schema::hasTable('hr_model_templates')) {
            Schema::create('hr_model_templates', function (Blueprint $table): void {
                $table->increments('id');
                $table->char('country_code', 2)->unique();
                $table->string('name', 100);
                $table->json('cotisations')->default('{}');
                $table->json('ir_brackets')->default('[]');
                $table->json('leave_rules')->default('{}');
                $table->json('holiday_calendar')->default('[]');
                $table->json('working_hours')->default('{}');
            });
        }
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS public.hr_model_templates CASCADE');
        }

        parent::tearDown();
    }

    public function test_seeder_creates_a_row_for_every_supported_country_code(): void
    {
        $this->artisan('db:seed', ['--class' => HrModelSeeder::class])->run();

        $countryCodes = DB::table('hr_model_templates')->pluck('country_code')->sort()->values()->all();

        self::assertSame(['CI', 'CM', 'DZ', 'FR', 'MA', 'SN', 'TN', 'TR'], $countryCodes);
    }

    /**
     * SN/CM/CI are new additions (Senegal, CEMAC zone via Cameroon, CEDEAO/
     * UEMOA zone via Cote d'Ivoire): each must expose the same fully-populated
     * shape (non-empty cotisations/ir_brackets/leave_rules/holiday_calendar/
     * working_hours) that the original DZ/MA/TN/FR/TR rows already have, so
     * the Super Admin HR-model screen and payroll estimation never see a
     * half-empty template for these countries.
     */
    public function test_new_zone_representative_rows_expose_a_fully_populated_template(): void
    {
        $this->artisan('db:seed', ['--class' => HrModelSeeder::class])->run();

        foreach (['SN', 'CM', 'CI'] as $countryCode) {
            $row = DB::table('hr_model_templates')->where('country_code', $countryCode)->first();

            self::assertNotNull($row, "{$countryCode}: hr_model_templates row must exist");

            $cotisations = json_decode((string) $row->cotisations, true);
            $irBrackets = json_decode((string) $row->ir_brackets, true);
            $leaveRules = json_decode((string) $row->leave_rules, true);
            $holidayCalendar = json_decode((string) $row->holiday_calendar, true);
            $workingHours = json_decode((string) $row->working_hours, true);

            self::assertNotEmpty($cotisations['salariales'] ?? [], "{$countryCode}: cotisations.salariales must not be empty");
            self::assertNotEmpty($cotisations['patronales'] ?? [], "{$countryCode}: cotisations.patronales must not be empty");
            self::assertNotEmpty($irBrackets, "{$countryCode}: ir_brackets must not be empty");
            self::assertNotEmpty($leaveRules, "{$countryCode}: leave_rules must not be empty");
            self::assertNotEmpty($holidayCalendar, "{$countryCode}: holiday_calendar must not be empty");
            self::assertNotEmpty($workingHours, "{$countryCode}: working_hours must not be empty");
            self::assertArrayHasKey('legal_minimum_days', $leaveRules, "{$countryCode}: leave_rules must declare legal_minimum_days");
            self::assertGreaterThan(0.0, (float) ($workingHours['overtime_threshold_weekly'] ?? 0), "{$countryCode}: working_hours must declare a positive weekly overtime threshold");
        }
    }

    /**
     * Regression guard: the last ir_brackets slab for every country must be
     * open-ended (max === null), otherwise high earners in that country
     * would silently fall outside every tax bracket.
     */
    public function test_every_country_ir_brackets_end_with_an_unbounded_top_slab(): void
    {
        $this->artisan('db:seed', ['--class' => HrModelSeeder::class])->run();

        $rows = DB::table('hr_model_templates')->get();

        foreach ($rows as $row) {
            $irBrackets = json_decode((string) $row->ir_brackets, true);
            self::assertNotEmpty($irBrackets, "{$row->country_code}: ir_brackets must not be empty");

            $lastSlab = $irBrackets[array_key_last($irBrackets)];
            self::assertNull($lastSlab['max'], "{$row->country_code}: the last ir_brackets slab must be unbounded (max = null)");
        }
    }

    /**
     * Running the seeder twice (updateOrInsert on country_code) must stay
     * idempotent and must not duplicate rows or crash — this is exactly the
     * "sans casser demos" acceptance criteria, since DatabaseSeeder can be
     * re-run against an environment that already has demo data.
     */
    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->artisan('db:seed', ['--class' => HrModelSeeder::class])->run();
        $this->artisan('db:seed', ['--class' => HrModelSeeder::class])->run();

        self::assertSame(8, DB::table('hr_model_templates')->count());
    }
}
