<?php

namespace Tests\Feature\Attendance;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Attendance\Domain\Models\AttendanceLog;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\Schedule;
use App\Modules\Planning\Domain\Models\Task;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * PA2-ATT-012: punctuality + task-completion indicators must be
 * readable/non-opaque (every number feeding the score is exposed, not
 * just the final number).
 */
class AttendanceRegularityTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_employee_can_view_their_own_regularity_score(): void
    {
        $company = Company::factory()->create();
        $schedule = Schedule::factory()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
        ]);

        app()->instance('current_company', $company);

        // Monday 2026-05-04 through Friday 2026-05-08: 5 expected working
        // days (rest_days = Sat/Sun on the factory-default schedule).
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-04',
            'check_in' => Carbon::parse('2026-05-04 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-04 17:00:00', 'UTC'),
        ]);
        AttendanceLog::factory()->late()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-05',
            'check_in' => Carbon::parse('2026-05-05 08:25:00', 'UTC'),
            'check_out' => Carbon::parse('2026-05-05 17:00:00', 'UTC'),
            'late_minutes' => 25,
        ]);
        AttendanceLog::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-05-06',
            'check_in' => Carbon::parse('2026-05-06 08:00:00', 'UTC'),
            'check_out' => null,
        ]);
        // 2026-05-07 and 2026-05-08: no log at all (absent, not excused).

        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/attendance/regularity?date_from=2026-05-04&date_to=2026-05-08');

        $response->assertOk();
        $response->assertJsonPath('data.employee_id', $employee->id);
        $response->assertJsonPath('data.breakdown.punctuality.expected_days', 5);
        $response->assertJsonPath('data.breakdown.punctuality.worked_days', 3);
        $response->assertJsonPath('data.breakdown.punctuality.absent_days', 2);
        $response->assertJsonPath('data.breakdown.punctuality.late_arrivals_count', 1);
        $response->assertJsonPath('data.breakdown.punctuality.late_minutes_total', 25);
        $response->assertJsonPath('data.breakdown.punctuality.missing_check_outs', 1);
        // No tasks assigned in the period: task_completion score must be
        // null (excluded), not a punishing 0.
        $response->assertJsonPath('data.breakdown.task_completion.score', null);
        $response->assertJsonPath('data.breakdown.task_completion.assigned_tasks', 0);
        $this->assertIsFloat($response->json('data.score'));
        $this->assertNotEmpty($response->json('data.score_label'));
    }

    public function test_approved_absences_are_excluded_from_expected_days_and_do_not_lower_the_score(): void
    {
        $company = Company::factory()->create();
        $schedule = Schedule::factory()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
        ]);

        app()->instance('current_company', $company);

        $absenceType = AbsenceType::factory()->create(['company_id' => $company->id]);

        Absence::factory()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'start_date' => '2026-05-07',
            'end_date' => '2026-05-08',
            'status' => 'approved',
        ]);

        foreach (['2026-05-04', '2026-05-05', '2026-05-06'] as $date) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'check_in' => Carbon::parse($date.' 08:00:00', 'UTC'),
                'check_out' => Carbon::parse($date.' 17:00:00', 'UTC'),
            ]);
        }
        // 2026-05-07 and 2026-05-08 are covered by the approved absence.

        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/attendance/regularity?date_from=2026-05-04&date_to=2026-05-08');

        $response->assertOk();
        // Only the 3 non-absence working days are expected, all worked
        // perfectly, so punctuality must be a full 100.
        $response->assertJsonPath('data.breakdown.punctuality.expected_days', 3);
        $response->assertJsonPath('data.breakdown.punctuality.worked_days', 3);
        $response->assertJsonPath('data.breakdown.punctuality.absent_days', 0);
        $this->assertSame(100.0, (float) $response->json('data.score'));
    }

    public function test_task_completion_ratio_is_included_and_weighted_with_punctuality(): void
    {
        $company = Company::factory()->create();
        $schedule = Schedule::factory()->create(['company_id' => $company->id]);
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
        ]);
        $creator = Employee::factory()->create(['company_id' => $company->id]);

        app()->instance('current_company', $company);

        foreach (['2026-05-04', '2026-05-05', '2026-05-06', '2026-05-07', '2026-05-08'] as $date) {
            AttendanceLog::factory()->create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'date' => $date,
                'check_in' => Carbon::parse($date.' 08:00:00', 'UTC'),
                'check_out' => Carbon::parse($date.' 17:00:00', 'UTC'),
            ]);
        }

        Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Task 1',
            'description' => 'Done on time',
            'created_by' => $creator->id,
            'assigned_to' => [$employee->id],
            'due_date' => '2026-05-06 12:00:00',
            'status' => 'done',
        ]);
        Task::query()->create([
            'company_id' => $company->id,
            'title' => 'Task 2',
            'description' => 'Never finished',
            'created_by' => $creator->id,
            'assigned_to' => [$employee->id],
            'due_date' => '2026-05-06 12:00:00',
            'status' => 'todo',
        ]);

        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/attendance/regularity?date_from=2026-05-04&date_to=2026-05-08');

        $response->assertOk();
        // Perfect punctuality (5/5 worked, no late arrivals) => 100.
        $this->assertSame(100.0, (float) $response->json('data.breakdown.punctuality.score'));
        // 1 of 2 tasks completed => 50.
        $this->assertSame(50.0, (float) $response->json('data.breakdown.task_completion.score'));
        $response->assertJsonPath('data.breakdown.task_completion.assigned_tasks', 2);
        $response->assertJsonPath('data.breakdown.task_completion.completed_tasks', 1);
        // Combined: 100 * 0.7 + 50 * 0.3 = 85.
        $this->assertSame(85.0, (float) $response->json('data.score'));
    }

    public function test_manager_can_view_regularity_for_a_team_member_but_not_another_companys_employee(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $manager = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        $employeeInA = Employee::factory()->create(['company_id' => $companyA->id]);
        $employeeInB = Employee::factory()->create(['company_id' => $companyB->id]);

        Sanctum::actingAs($manager);

        $this->getJson("/api/v1/attendance/regularity?employee_id={$employeeInA->id}")
            ->assertOk();

        $this->getJson("/api/v1/attendance/regularity?employee_id={$employeeInB->id}")
            ->assertStatus(422);
    }

    public function test_employee_cannot_view_another_employees_regularity_score(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $coworker = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $this->getJson("/api/v1/attendance/regularity?employee_id={$coworker->id}")
            ->assertStatus(403);
    }
}
