<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureApiManagerMiddleware;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiManagerMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::forgetGuards();

        parent::tearDown();
    }

    public function test_allows_any_manager_when_no_roles_specified(): void
    {
        foreach (['principal', 'rh', 'dept', 'comptable', 'superviseur'] as $role) {
            Auth::setUser($this->employee('manager', $role));

            $response = (new EnsureApiManagerMiddleware)->handle(
                Request::create('/api/v1/dashboard/summary'),
                fn (): Response => new Response('', 204),
            );

            self::assertSame(204, $response->getStatusCode(), "Manager with role {$role} should be allowed");
        }
    }

    public function test_rejects_regular_employee(): void
    {
        Auth::setUser($this->employee('employee', null));

        $response = (new EnsureApiManagerMiddleware)->handle(
            Request::create('/api/v1/dashboard/summary'),
            fn (): Response => new Response('', 204),
        );

        self::assertSame(403, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        self::assertSame('MANAGER_REQUIRED', $json['error']);
    }

    public function test_allows_specific_roles_only(): void
    {
        Auth::setUser($this->employee('manager', 'principal'));

        $response = (new EnsureApiManagerMiddleware)->handle(
            Request::create('/api/v1/billing/subscription'),
            fn (): Response => new Response('', 204),
            'principal', 'comptable',
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function test_rejects_wrong_manager_role(): void
    {
        Auth::setUser($this->employee('manager', 'superviseur'));

        $response = (new EnsureApiManagerMiddleware)->handle(
            Request::create('/api/v1/billing/subscription'),
            fn (): Response => new Response('', 204),
            'principal', 'comptable',
        );

        self::assertSame(403, $response->getStatusCode());

        $json = json_decode($response->getContent(), true);
        self::assertSame('INSUFFICIENT_ROLE', $json['error']);
    }

    public function test_rejects_unauthenticated_user(): void
    {
        $response = (new EnsureApiManagerMiddleware)->handle(
            Request::create('/api/v1/dashboard/summary'),
            fn (): Response => new Response('', 204),
        );

        self::assertSame(403, $response->getStatusCode());
    }

    private function employee(string $role, ?string $managerRole): Employee
    {
        $employee = new Employee;
        $employee->setRawAttributes([
            'id' => 1,
            'role' => $role,
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $employee;
    }
}
