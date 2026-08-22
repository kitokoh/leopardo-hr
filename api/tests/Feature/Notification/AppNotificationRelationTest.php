<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\AppNotification;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2436 — AppNotification.user() pointait vers public.users alors que
 * user_id stocke l'id d'un employé tenant (NotificationDispatcher appelé avec
 * un Employee id par NotifyTaxRateValidation). La relation doit résoudre
 * l'Employee.
 */
class AppNotificationRelationTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La table app_notifications est créée par la migration tenant
        // 2026_08_15_000002 (#2395/#1813) — le create inline historique est
        // retiré : RefreshTenantDatabase migre le schéma complet (issue #5201).
    }

    public function test_user_relation_resolves_to_tenant_employee(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();

        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $notification = AppNotification::query()->create([
            'user_id' => $employee->id,
            'type' => 'tax_rate_validation',
            'title' => 'Validation requise',
            'body' => null,
            'read' => false,
        ]);

        $this->assertNotNull($notification->user);
        $this->assertInstanceOf(Employee::class, $notification->user);
        $this->assertSame($employee->id, $notification->user->id);
        $this->assertSame($employee->company_id, $notification->user->company_id);
    }
}
