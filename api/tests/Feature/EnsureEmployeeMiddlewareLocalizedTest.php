<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Http\Middleware\Web\EnsureEmployeeMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * #4878 (audit 2026-08-17) — EnsureEmployeeMiddleware : les messages 403
 * passent par le catalogue errors.* (plus de littéraux FR codés en dur).
 */
class EnsureEmployeeMiddlewareLocalizedTest extends TestCase
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

    private function makeCompany(string $slug, string $status, string $language = 'en'): Company
    {
        /** @var Company $company */
        $company = Company::query()->create([
            'name' => 'Company '.$slug,
            'slug' => $slug,
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@'.$slug.'.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => $status,
            'plan_id' => 1,
            'subscription_start' => '2026-01-01',
            'subscription_end' => '2027-01-01',
            'language' => $language,
            'currency' => 'DZD',
            'timezone' => 'UTC',
        ]);

        return $company;
    }

    private function makeEmployee(Company $company, string $status): Employee
    {
        $employee = new Employee([
            'company_id' => $company->id,
            'email' => fake()->unique()->safeEmail(),
            'first_name' => 'Test',
            'last_name' => 'Employee',
        ]);
        $employee->forceFill([
            'password_hash' => Hash::make('password123'),
            'status' => $status,
        ])->save();

        return $employee;
    }

    public function test_inactive_employee_message_is_localized(): void
    {
        App::setLocale('en');
        $company = $this->makeCompany('inactive-emp', 'active');
        $employee = $this->makeEmployee($company, 'inactive');

        $request = Request::create('/me', 'GET');
        $request->setUserResolver(fn () => $employee);

        try {
            (new EnsureEmployeeMiddleware)->handle($request, fn () => response('ok'));
            $this->fail('Expected HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(__('errors.EMPLOYEE_INACTIVE', [], 'en'), $e->getMessage());
        }
    }

    public function test_suspended_company_message_is_localized(): void
    {
        App::setLocale('en');
        $company = $this->makeCompany('susp-co', 'suspended');
        $employee = $this->makeEmployee($company, 'active');
        // Appel direct du middleware : la relation company est pré-chargée
        // (pas de contexte tenant HTTP pour un lazy-load fiable).
        $employee->setRelation('company', $company);

        $request = Request::create('/me', 'GET');
        $request->setUserResolver(fn () => $employee);

        try {
            (new EnsureEmployeeMiddleware)->handle($request, fn () => response('ok'));
            $this->fail('Expected HttpException 403.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertSame(__('errors.COMPANY_SUSPENDED_EXPIRED', [], 'en'), $e->getMessage());
        }
    }
}
