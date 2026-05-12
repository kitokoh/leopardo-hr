<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_user_can_list_notifications(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/notifications');
        $response->assertOk();
    }

    public function test_user_can_list_unread_notifications(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/notifications/unread');
        $response->assertOk();
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/v1/notifications/mark-all-read');
        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_list_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    public function test_user_can_mark_single_notification_read(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $notificationId = Str::uuid()->toString();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\Employee',
            'notifiable_id' => $employee->id,
            'data' => json_encode(['message' => 'Test']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->patchJson("/api/v1/notifications/{$notificationId}/read");
        $response->assertOk();
    }

    public function test_notifications_are_paginated(): void
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/notifications?page=1');
        $response->assertOk();
    }
}
