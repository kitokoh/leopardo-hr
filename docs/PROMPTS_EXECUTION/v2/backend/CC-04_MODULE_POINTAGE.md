# CC-04 — Module Pointage (Attendance)
# Agent : Claude Code
# Durée : 5-6 heures
# Prérequis : CC-03 vert (Employees + Config OK, migrations tenant OK)

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test --filter="Config|Employee|Auth|Infrastructure"  # 0 failure

# Vérifier que la contrainte UNIQUE existe bien en DB
php artisan tinker
>>> DB::select("SELECT constraint_name FROM information_schema.table_constraints WHERE table_name = 'attendance_logs' AND constraint_type = 'UNIQUE'")
# Doit retourner [{"constraint_name":"unique_attendance_per_day"}]
```

---

## ENDPOINTS À IMPLÉMENTER

```
POST  /api/v1/attendance/check-in          → pointer l'arrivée
POST  /api/v1/attendance/check-out         → pointer le départ
GET   /api/v1/attendance/today             → état du jour de l'employé connecté
GET   /api/v1/attendance                   → liste (employé = ses propres, manager = équipe)
GET   /api/v1/attendance/{employee_id}     → historique d'un employé (manager only)
PUT   /api/v1/attendance/{id}              → correction manuelle (manager only)
POST  /api/v1/attendance/qrcode            → check-in via QR code
POST  /api/v1/biometric/webhook            → réception webhook ZKTeco
```

Payloads exacts : `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` — Section 4.

---

## TESTS À ÉCRIRE EN PREMIER

```php
// tests/Feature/Attendance/AttendanceTest.php

it('check-in creates a log with server timestamp — never client timestamp', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    // Simuler que le client envoie un faux timestamp (1 heure dans le futur)
    $fakeClientTimestamp = now()->addHour()->toIso8601String();

    $response = $this->withToken($token)
         ->postJson('/api/v1/attendance/check-in', [
             'gps_lat'          => 36.7372,
             'gps_lng'          => 3.0868,
             'fake_timestamp'   => $fakeClientTimestamp, // ignoré par le serveur
         ]);

    $response->assertStatus(201);

    // Vérifier que check_in est proche de now(), pas du fakeClientTimestamp
    $log = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();
    expect($log->check_in->timestamp)->toBeGreaterThan(now()->subMinute()->timestamp);
    expect($log->check_in->timestamp)->toBeLessThan(now()->addMinute()->timestamp);
    expect($log->check_in->timestamp)->not->toBe(Carbon::parse($fakeClientTimestamp)->timestamp);
});

it('second check-in returns 409 ALREADY_CHECKED_IN', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    $this->withToken($token)->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.7, 'gps_lng' => 3.0]);
    $this->withToken($token)
         ->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.7, 'gps_lng' => 3.0])
         ->assertStatus(409)
         ->assertJson(['error' => 'ALREADY_CHECKED_IN']);
});

it('check-out updates existing log — does NOT insert a new row', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    $this->withToken($token)->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.7, 'gps_lng' => 3.0]);

    $countBefore = AttendanceLog::where('employee_id', $employee->id)->count();

    $this->withToken($token)
         ->postJson('/api/v1/attendance/check-out', ['gps_lat' => 36.7, 'gps_lng' => 3.0])
         ->assertStatus(200);

    $countAfter = AttendanceLog::where('employee_id', $employee->id)->count();

    expect($countAfter)->toBe($countBefore); // Même nombre de lignes — UPDATE, pas INSERT
    $log = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();
    expect($log->check_out)->not->toBeNull();
});

it('check-out without check-in returns 422 MISSING_CHECK_IN', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    $this->withToken($token)
         ->postJson('/api/v1/attendance/check-out', ['gps_lat' => 36.7, 'gps_lng' => 3.0])
         ->assertStatus(422)
         ->assertJson(['error' => 'MISSING_CHECK_IN']);
});

it('GPS outside zone returns 422 GPS_OUTSIDE_ZONE', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    $site = Site::factory()->create([
        'gps_lat'           => 36.7372,
        'gps_lng'           => 3.0868,
        'gps_radius_meters' => 100,
    ]);
    $employee->update(['site_id' => $site->id]);

    // Envoyer des coordonnées à 5 km du site
    $this->withToken($token)
         ->postJson('/api/v1/attendance/check-in', [
             'gps_lat' => 36.8000,  // ~7 km du site
             'gps_lng' => 3.0868,
         ])
         ->assertStatus(422)
         ->assertJson(['error' => 'GPS_OUTSIDE_ZONE']);
});

it('GET /attendance/today returns null data when not checked in', function () {
    [$company, $employee, $token] = createEmployeeWithToken();

    $this->withToken($token)
         ->getJson('/api/v1/attendance/today')
         ->assertStatus(200)
         ->assertJson(['data' => null]);
});

it('check-in on time sets status ontime', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    $schedule = Schedule::factory()->create([
        'start_time'             => '08:00:00',
        'late_tolerance_minutes' => 10,
    ]);
    $employee->update(['schedule_id' => $schedule->id]);

    // Simuler un check-in à 08:05 (dans la tolérance)
    $this->travel(Carbon::today()->setTime(8, 5))->do(function () use ($token) {
        $this->withToken($token)
             ->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.7, 'gps_lng' => 3.0])
             ->assertStatus(201);
    });

    $log = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();
    expect($log->status)->toBe('ontime');
});

it('check-in after tolerance sets status late', function () {
    [$company, $employee, $token] = createEmployeeWithToken();
    $schedule = Schedule::factory()->create([
        'start_time'             => '08:00:00',
        'late_tolerance_minutes' => 10,
    ]);
    $employee->update(['schedule_id' => $schedule->id]);

    // Simuler un check-in à 08:25 (hors tolérance)
    $this->travel(Carbon::today()->setTime(8, 25))->do(function () use ($token) {
        $this->withToken($token)
             ->postJson('/api/v1/attendance/check-in', ['gps_lat' => 36.7, 'gps_lng' => 3.0])
             ->assertStatus(201);
    });

    $log = AttendanceLog::where('employee_id', $employee->id)->whereDate('date', today())->first();
    expect($log->status)->toBe('late');
});

it('ZKTeco biometric webhook creates attendance log', function () {
    [$company, $employee] = createEmployeeInCompany();
    $device = Device::factory()->create(['company_id' => $company->id]);

    $this->postJson('/api/v1/biometric/webhook', [
        'device_token'    => $device->api_token,
        'employee_badge'  => $employee->badge_number,
        'event_type'      => 'check_in',
        'recorded_at'     => now()->toIso8601String(),
    ])->assertStatus(200);

    $this->assertDatabaseHas('attendance_logs', [
        'employee_id' => $employee->id,
    ]);
});

it('biometric webhook with invalid device token returns 401', function () {
    $this->postJson('/api/v1/biometric/webhook', [
        'device_token' => 'fake_invalid_token',
        'employee_badge' => 'EMP001',
        'event_type' => 'check_in',
    ])->assertStatus(401);
});
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Tenant/AttendanceController.php
app/Http/Controllers/Tenant/BiometricController.php    ← webhook ZKTeco
app/Http/Requests/Tenant/CheckInRequest.php
app/Http/Requests/Tenant/CheckOutRequest.php
app/Http/Requests/Tenant/ManualAttendanceRequest.php
app/Http/Resources/AttendanceLogResource.php
app/Http/Resources/AttendanceTodayResource.php
app/Models/Tenant/AttendanceLog.php
app/Models/Tenant/Device.php
app/Services/AttendanceService.php
app/Services/GpsValidationService.php
app/Console/Commands/AttendanceCheckMissing.php       ← cron job J+1
```

---

## ATTENDANCESERVICE — MÉTHODES OBLIGATOIRES

```php
// app/Services/AttendanceService.php

class AttendanceService
{
    // Récupère le log du jour ou null — utilisé par le Controller pour décider check-in vs check-out
    public function getTodayLog(Employee $employee): ?AttendanceLog
    {
        return AttendanceLog::where('employee_id', $employee->id)
            ->whereDate('date', today())
            ->first();
    }

    public function checkIn(Employee $employee, array $gpsData): AttendanceLog
    {
        // Horodatage serveur TOUJOURS
        $checkInTime = now();

        // Récupérer le planning et convertir en timezone locale avant comparaison
        $schedule   = $employee->schedule;
        $timezone   = $employee->company->timezone ?? 'UTC';
        $localTime  = $checkInTime->setTimezone($timezone);
        $scheduleStart = Carbon::parse($schedule->start_time, $timezone);
        $lateThreshold = $scheduleStart->copy()->addMinutes($schedule->late_tolerance_minutes);

        $status = $localTime->lte($lateThreshold) ? 'ontime' : 'late';
        $minutesLate = max(0, $localTime->diffInMinutes($scheduleStart, false) * -1);

        return AttendanceLog::create([
            'employee_id'    => $employee->id,
            'date'           => today()->toDateString(),
            'check_in'       => $checkInTime,  // stocké UTC
            'status'         => $status,
            'minutes_late'   => $minutesLate,
            'gps_lat'        => $gpsData['gps_lat'] ?? null,
            'gps_lng'        => $gpsData['gps_lng'] ?? null,
            'source'         => $gpsData['source'] ?? 'mobile',
        ]);
    }

    public function checkOut(Employee $employee, AttendanceLog $log, array $gpsData): AttendanceLog
    {
        $checkOutTime  = now();   // Horodatage serveur
        $schedule      = $employee->schedule;
        $hoursWorked   = $this->calculateHoursWorked($log->check_in, $checkOutTime, $schedule);
        $overtimeHours = $this->calculateOvertime($hoursWorked, $schedule);

        $log->update([
            'check_out'      => $checkOutTime,
            'hours_worked'   => $hoursWorked,
            'overtime_hours' => $overtimeHours,
            'status'         => $this->resolveCheckoutStatus($log, $hoursWorked, $schedule),
        ]);

        return $log->fresh();
    }

    public function calculateHoursWorked(Carbon $checkIn, Carbon $checkOut, Schedule $schedule): float
    {
        $rawMinutes  = $checkIn->diffInMinutes($checkOut);
        $netMinutes  = max(0, $rawMinutes - ($schedule->break_minutes ?? 0));
        return round($netMinutes / 60, 2);
    }

    public function calculateOvertime(float $hoursWorked, Schedule $schedule): float
    {
        return max(0, round($hoursWorked - ($schedule->overtime_threshold_daily ?? 8.0), 2));
    }

    private function resolveCheckoutStatus(AttendanceLog $log, float $hoursWorked, Schedule $schedule): string
    {
        if ($log->status === 'late') return 'late';
        if ($hoursWorked < ($schedule->expected_hours ?? 8) - 0.25) return 'early_leave';
        return 'ontime';
    }
}
```

---

## GPSVALIDATIONSERVICE

```php
public function isWithinZone(float $lat, float $lng, Site $site): bool
{
    if (!$site->gps_lat || !$site->gps_lng || !$site->gps_radius_meters) {
        return true; // Pas de restriction GPS configurée → autorisé
    }

    $distance = $this->haversineDistance($lat, $lng, $site->gps_lat, $site->gps_lng);
    return $distance <= $site->gps_radius_meters;
}

private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000; // mètres
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadius * $c;
}
```

---

## COMMANDE CRON — AttendanceCheckMissing

```php
// app/Console/Commands/AttendanceCheckMissing.php
// Exécutée à H+1 après la fin du shift configuré
// Pour chaque employé sans check_out → status = 'incomplete' + alerte gestionnaire
// Planification dans Console/Kernel.php : $schedule->command('attendance:check-missing')->hourly()
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-05

```
[ ] php artisan test --filter=Attendance → 0 failure
[ ] Test "serveur timestamp" passe (check_in ≠ timestamp client)
[ ] Test "check-out = UPDATE" passe (count avant = count après)
[ ] Test GPS hors zone → 422 GPS_OUTSIDE_ZONE
[ ] Test ZKTeco webhook → log créé
[ ] Contrainte UNIQUE(employee_id, date) vérifiée en DB (pas de double ligne)
[ ] php artisan test (global) → 0 failure
```

---

## COMMIT

```
feat: add attendance module with GPS zone validation, ZKTeco webhook
feat: add AttendanceService with server-side timestamps and overtime calculation
feat: add attendance:check-missing cron command
test: add 9 attendance tests including server timestamp, GPS zone, UNIQUE constraint
```
