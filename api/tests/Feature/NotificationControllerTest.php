<?php

namespace Tests\Feature;

use App\Models\CommunicationEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_user_lists_only_own_notifications_with_unread_count(): void
    {
        [$company, $employee, $otherEmployee] = $this->notificationFixture();

        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'absence',
            'title' => 'Absence approved',
            'body' => 'Your absence was approved.',
            'is_read' => false,
            'created_at' => now(),
        ]);
        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'payroll',
            'title' => 'Payslip ready',
            'body' => 'Your payslip is ready.',
            'is_read' => true,
            'read_at' => now(),
            'created_at' => now()->subMinute(),
        ]);
        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'type' => 'foreign',
            'title' => 'Other employee',
            'body' => 'Should stay isolated.',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1);

        $this->getJson('/api/v1/notifications?unread_only=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Absence approved');
    }

    public function test_unread_endpoint_returns_only_unread_notifications(): void
    {
        [$company, $employee] = $this->notificationFixture();

        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'absence',
            'title' => 'Unread',
            'body' => 'Unread body',
            'is_read' => false,
            'created_at' => now(),
        ]);
        Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'absence',
            'title' => 'Read',
            'body' => 'Read body',
            'is_read' => true,
            'read_at' => now(),
            'created_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/notifications/unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Unread');
    }

    public function test_mark_read_and_mark_all_read_only_touch_authenticated_employee(): void
    {
        [$company, $employee, $otherEmployee] = $this->notificationFixture();

        $first = Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'absence',
            'title' => 'First',
            'body' => 'First body',
            'created_at' => now(),
        ]);
        $second = Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'payroll',
            'title' => 'Second',
            'body' => 'Second body',
            'created_at' => now(),
        ]);
        $other = Notification::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'type' => 'foreign',
            'title' => 'Other',
            'body' => 'Other body',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->patchJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertTrue($first->fresh()->is_read);
        $this->assertFalse($second->fresh()->is_read);
        $this->assertDatabaseHas('communication_events', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'notification_id' => $first->id,
            'event_name' => 'notification_read',
        ]);

        $this->postJson('/api/v1/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.');

        $this->assertTrue($second->fresh()->is_read);
        $this->assertFalse($other->fresh()->is_read);
        $this->assertSame(2, CommunicationEvent::query()->where('employee_id', $employee->id)->count());
    }

    public function test_mobile_notification_mutation_aliases_and_delete_are_scoped(): void
    {
        [$company, $employee, $otherEmployee] = $this->notificationFixture();

        $owned = Notification::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'type' => 'task',
            'title' => 'Task assigned',
            'body' => 'A new task was assigned.',
            'created_at' => now(),
        ]);
        $other = Notification::create([
            'company_id' => $company->id,
            'employee_id' => $otherEmployee->id,
            'type' => 'task',
            'title' => 'Other task',
            'body' => 'Must stay private.',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $this->putJson("/api/v1/notifications/{$owned->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->putJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.');

        $this->deleteJson("/api/v1/notifications/{$other->id}")
            ->assertNotFound();

        $this->deleteJson("/api/v1/notifications/{$owned->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Notification deleted.');

        $this->assertDatabaseMissing('notifications', ['id' => $owned->id]);
        $this->assertDatabaseHas('notifications', ['id' => $other->id]);
    }

    /**
     * @return array{0: Company, 1: Employee, 2: Employee}
     */
    private function notificationFixture(): array
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $otherEmployee = Employee::factory()->create(['company_id' => $company->id]);

        return [$company, $employee, $otherEmployee];
    }
}
