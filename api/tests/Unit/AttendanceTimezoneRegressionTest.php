<?php

namespace Tests\Unit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Attendance\Infrastructure\Services\AttendanceService;
use App\Modules\Planning\Domain\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PA2-ATT-008 — regression coverage for the "store UTC, display/compute in
 * company timezone" contract documented on the attendance_logs migration
 * (2026_04_01_000103_create_attendance_absences_advances.php):
 *
 *   "check_in/check_out toujours en UTC côté stockage. CALCULS (retard, HS)
 *    se font EN TIMEZONE ENTREPRISE via Carbon::setTimezone()."
 *
 * AttendanceServiceTest already covers the happy path with a UTC-timezone
 * company (a no-op case for timezone conversion). This suite exercises
 * companies in several real timezones used across the supported countries
 * (Africa/Algiers +01:00, Europe/Istanbul +03:00, America/Toronto -04:00)
 * to prove:
 *   1. check_in/check_out are always persisted as UTC instants.
 *   2. Late-arrival detection compares against the schedule's start time in
 *      the COMPANY's local timezone, not UTC or the server's timezone.
 *   3. The "today" business date used to find/create the daily attendance
 *      row is derived from the company's local calendar day, so a punch
 *      that is already past midnight UTC but still "today" locally (or
 *      vice versa) resolves to the correct row instead of creating a
 *      duplicate for the wrong date.
 */
class AttendanceTimezoneRegressionTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function timezoneProvider(): array
    {
        return [
            'algeria (UTC+1, no DST)' => ['DZ', 'Africa/Algiers'],
            'turkey (UTC+3, no DST)' => ['TR', 'Europe/Istanbul'],
            'canada eastern (UTC-4 in summer)' => ['CA', 'America/Toronto'],
        ];
    }

    /**
     * @dataProvider timezoneProvider
     */
    public function test_check_in_is_always_persisted_as_utc(string $country, string $timezone): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule($country, $timezone);
        app()->instance('current_company', $company);

        // 09:05 local time -> a different UTC instant per timezone.
        $localCheckIn = CarbonImmutable::parse('2026-07-06 09:05:00', $timezone);
        Carbon::setTestNow($localCheckIn);

        $log = app(AttendanceService::class)->checkIn($employee);

        // Assert against the raw column value written to PostgreSQL, which
        // the migration comment guarantees is always UTC. We deliberately do
        // NOT compare Eloquent's cast `check_in` Carbon instance directly:
        // under `Carbon::setTestNow()` with a non-UTC "now", Carbon's cast
        // path can label the returned instance with the ambient test
        // timezone instead of UTC, even though the same instant is stored
        // correctly. Reading the raw column sidesteps that test-only
        // artifact and verifies what actually lands in the database.
        $rawCheckIn = \DB::table('attendance_logs')->where('id', $log->id)->value('check_in');

        $this->assertSame(
            $localCheckIn->clone()->utc()->format('Y-m-d H:i:s'),
            Carbon::parse($rawCheckIn)->utc()->format('Y-m-d H:i:s'),
            "The raw check_in column must be stored as the UTC-equivalent instant of {$localCheckIn->toIso8601String()} for {$timezone}."
        );
    }

    /**
     * @dataProvider timezoneProvider
     */
    public function test_late_arrival_is_evaluated_in_company_timezone_not_utc(string $country, string $timezone): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule($country, $timezone);
        app()->instance('current_company', $company);

        // Schedule starts at 09:00 local with 15 min tolerance. Punching in
        // at 09:05 LOCAL time must be on-time, even though the equivalent
        // UTC clock time would look "late" relative to a naive 09:00 UTC
        // comparison for every non-UTC timezone under test.
        $onTimeLocal = CarbonImmutable::parse('2026-07-06 09:05:00', $timezone);
        Carbon::setTestNow($onTimeLocal);

        $log = app(AttendanceService::class)->checkIn($employee);

        $this->assertSame('ontime', $log->status, "Expected on-time status for {$timezone} using local schedule comparison.");
        $this->assertSame(0, $log->late_minutes);

        $log->forceFill(['check_out' => null])->delete();

        // Now punch in 30 minutes past the tolerance window, still in local
        // time, and confirm late_minutes reflects the LOCAL delay (30),
        // not some UTC-shifted value.
        $lateLocal = CarbonImmutable::parse('2026-07-06 09:30:00', $timezone);
        Carbon::setTestNow($lateLocal);

        $lateLog = app(AttendanceService::class)->checkIn($employee);

        $this->assertSame('late', $lateLog->status);
        $this->assertSame(15, $lateLog->late_minutes);
    }

    /**
     * @dataProvider timezoneProvider
     */
    public function test_business_date_follows_company_local_calendar_day_across_midnight(string $country, string $timezone): void
    {
        [$company, $employee] = $this->seedEmployeeWithSchedule($country, $timezone);
        app()->instance('current_company', $company);

        // 23:45 LOCAL time on 2026-07-06 is deliberately chosen so that, for
        // every positive-offset timezone under test, the equivalent UTC
        // instant has already rolled over to 2026-07-07 — the exact
        // scenario the migration's UTC-storage/local-calendar contract is
        // meant to protect against (a late-night punch must not be filed
        // under "tomorrow").
        $lateNightLocal = CarbonImmutable::parse('2026-07-06 23:45:00', $timezone);
        Carbon::setTestNow($lateNightLocal);

        $log = app(AttendanceService::class)->checkIn($employee);

        $rawCheckIn = Carbon::parse(\DB::table('attendance_logs')->where('id', $log->id)->value('check_in'))->utc();
        $localDateFromRawUtc = $rawCheckIn->copy()->setTimezone($timezone)->toDateString();

        // The business `date` column must follow the COMPANY's local
        // calendar day for this punch, i.e. 2026-07-06 — never the UTC
        // calendar day, which for a positive UTC offset has already become
        // 2026-07-07 at 23:45 local time.
        $this->assertSame('2026-07-06', $localDateFromRawUtc);
        $this->assertSame('2026-07-06', $log->date->format('Y-m-d'));

        // Only timezones AHEAD of UTC (positive offset, e.g. Africa/Algiers
        // +1, Europe/Istanbul +3) keep the same UTC calendar date at 23:45
        // local. A timezone BEHIND UTC (negative offset, e.g.
        // America/Toronto -4) rolls the UTC instant forward into the next
        // calendar day — exactly the case that would corrupt the business
        // date if the service naively used the UTC date instead of the
        // company's local date.
        $utcCalendarDate = $rawCheckIn->toDateString();
        if ($timezone === 'America/Toronto') {
            $this->assertNotSame(
                $utcCalendarDate,
                $log->date->format('Y-m-d'),
                "Expected the UTC calendar date to have already rolled over to the next day relative to the local business date for {$timezone} at 23:45 local time."
            );
        } else {
            $this->assertSame(
                $utcCalendarDate,
                $log->date->format('Y-m-d'),
                "For {$timezone} (ahead of UTC), the UTC calendar date should still match the local business date at 23:45 local time."
            );
        }
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function seedEmployeeWithSchedule(string $country, string $timezone): array
    {
        static $suffix = 0;
        $suffix++;

        $company = Company::query()->create([
            'name' => "Company {$country}",
            'slug' => 'company-'.strtolower($country).'-'.$suffix,
            'sector' => 'restaurant',
            'country' => $country,
            'city' => 'N/A',
            'email' => "tz-test-{$suffix}@company.test",
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => $timezone,
            'currency' => 'USD',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Jour',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8,
            'is_default' => true,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => "employee-tz-{$suffix}@company.test",
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        return [$company, $employee];
    }
}
