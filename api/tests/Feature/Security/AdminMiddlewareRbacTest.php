<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\AdminMiddleware;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminMiddlewareRbacTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::forgetGuards();

        parent::tearDown();
    }

    public function test_admin_middleware_allows_only_principal_manager_subrole(): void
    {
        Auth::setUser($this->employee('manager', 'principal'));

        $response = (new AdminMiddleware)->handle(
            Request::create('/admin-only'),
            fn (): Response => new Response('', 204),
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function test_admin_middleware_rejects_department_and_supervisor_managers(): void
    {
        foreach (['dept', 'superviseur'] as $managerRole) {
            Auth::setUser($this->employee('manager', $managerRole));

            $response = (new AdminMiddleware)->handle(
                Request::create('/admin-only'),
                fn (): Response => new Response('', 204),
            );

            self::assertSame(403, $response->getStatusCode());
        }
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
