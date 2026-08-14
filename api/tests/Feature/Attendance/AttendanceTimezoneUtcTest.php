<?php

declare(strict_types=1);

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #1770 — Les timestamps de pointage doivent être interprétés en UTC
 * quelle que soit l'heure locale du serveur/base : la connexion pgsql force
 * `set time zone 'UTC'` (config/database.php → 'timezone' => 'UTC').
 */
class AttendanceTimezoneUtcTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_pgsql_config_forces_utc_session_timezone(): void
    {
        // Le connector Laravel exécute `set time zone 'UTC'` sur chaque
        // connexion quand `timezone` est présent dans la config pgsql.
        $this->assertSame('UTC', config('database.connections.pgsql.timezone'));

        // Nouvelle connexion (session fraîche) → le fuseau doit être UTC,
        // même si le serveur tourne en heure locale (scénario self-host).
        DB::purge('pgsql');

        /** @var object{TimeZone: string}|null $row */
        $row = DB::selectOne('SHOW TIME ZONE');
        $this->assertNotNull($row);
        $this->assertSame('UTC', $row->TimeZone);
    }

    public function test_attendance_check_in_round_trips_utc(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'Africa/Algiers']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        // Réalisme : le serveur de test est forcé en UTC ; on s'assure que le
        // pointage écrit par l'app (now('UTC')) se relit à l'identique.
        $utcNow = now('UTC');

        /** @var AttendanceLog $log */
        $log = AttendanceLog::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => $utcNow->toDateString(),
            'session_number' => 1,
            'check_in' => $utcNow,
            'method' => 'mobile',
            'status' => 'ontime',
        ]);

        /** @var AttendanceLog|null $read */
        $read = AttendanceLog::find($log->id);
        $this->assertNotNull($read);
        $this->assertNotNull($read->check_in);
        $this->assertSame($utcNow->timestamp, $read->check_in->timestamp);
        // Instantané conservé en UTC (offset 0), quel que soit le fuseau local.
        $this->assertSame(0, $read->check_in->getOffset());
    }
}
