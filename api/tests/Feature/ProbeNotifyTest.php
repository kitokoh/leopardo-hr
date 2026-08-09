<?php
namespace Tests\Feature;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class ProbeNotifyTest extends TestCase
{
    use CreatesMvpSchema;

    public function test_probe_notify(): void
    {
        $company = Company::query()->create([
            'id' => \Illuminate\Support\Str::uuid(), 'name' => 'Probe Co', 'slug' => 'probe-co',
            'sector' => 'test', 'country' => 'DZ', 'city' => 'Alger',
            'email' => 'probe@test.com', 'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared', 'status' => 'active',
            'plan_id' => 1, 'subscription_start' => '2026-01-01', 'subscription_end' => '2027-01-01',
            'timezone' => 'UTC', 'currency' => 'DZD', 'language' => 'fr',
        ]);
        $employee = Employee::query()->create([
            'company_id' => $company->id, 'first_name' => 'Probe', 'last_name' => 'User',
            'email' => 'probe.emp@test.com', 'password_hash' => Hash::make('password123'),
            'role' => 'employee', 'status' => 'active',
        ]);
        $service = app(CommunicationService::class);
        try {
            $res = $service->notifyEmployee($employee, 'salary_advance_manager_approved', [
                'salary_advance_id' => 42, 'payment_reference' => 'VIR-1',
            ]);
            fwrite(STDERR, "RESULT: ".json_encode($res)."\n");
        } catch (\Throwable $e) {
            fwrite(STDERR, "THREW: ".get_class($e).": ".$e->getMessage()."\n");
        }
        $rows = Notification::query()->where('employee_id', $employee->id)->get(['id','type','title','data']);
        fwrite(STDERR, "NOTIFS: ".$rows->count()." ".$rows->toJson()."\n");
        $this->assertTrue(true);
    }
}
