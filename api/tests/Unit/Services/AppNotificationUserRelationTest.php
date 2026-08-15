<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Notification\Domain\Models\AppNotification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AppNotificationUserRelationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La table `app_notifications` est créée par la migration
        // 2026_08_15_000001 (issue #2398) — si l'environnement de test ne
        // l'a pas (base partielle), on crée un schéma minimal local.
        if (! Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function ($table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 80);
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->jsonb('data')->nullable();
                $table->boolean('read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->timestampsTz();
            });
        }
    }

    public function test_user_relation_resolves_tenant_employee(): void
    {
        $employee = Employee::query()->create([
            'company_id' => '00000000-0000-0000-0000-000000000001',
            'matricule' => 'REL-2436-1',
            'first_name' => 'Notif',
            'last_name' => 'Relation',
            'email' => 'relation@test.test',
            'password_hash' => bcrypt('password'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $notification = AppNotification::query()->create([
            'user_id' => $employee->id,
            'type' => 'tax_rate_validated',
            'title' => 'Barème validé',
            'read' => false,
        ]);

        // #2436 : user_id porte un id d'EMPLOYÉ tenant — la relation doit
        // résoudre l'employé, pas public.users (ids incohérents).
        $this->assertInstanceOf(Employee::class, $notification->user);
        $this->assertSame($employee->id, $notification->user->id);
        $this->assertSame($employee->email, $notification->user->email);
    }
}
