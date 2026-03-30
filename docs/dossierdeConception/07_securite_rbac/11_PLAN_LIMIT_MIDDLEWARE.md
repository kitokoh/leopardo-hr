# PLAN LIMIT MIDDLEWARE — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## DESCRIPTION

Le `PlanLimitMiddleware` vérifie qu'un tenant ne dépasse pas les limites de son plan avant toute création de ressource.
Sans ce middleware, un client Starter peut créer 200 employés malgré la limite de 20.

---

## IMPLÉMENTATION LARAVEL

```php
// app/Http/Middleware/PlanLimitMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanLimitMiddleware
{
    /**
     * Vérifie les limites du plan avant création.
     * Route param: "resource" détermine quoi compter.
     *
     * Usage dans routes/api.php :
     * Route::post('/employees', ...)->middleware('plan.limit:employees');
     */
    public function handle(Request $request, Closure $next, string $resource = 'employees'): mixed
    {
        $company = $request->user()->company; // via TenantMiddleware
        $plan    = $company->plan;

        $limit = match($resource) {
            'employees' => $plan->max_employees,   // NULL = illimité
            default     => null,
        };

        if ($limit !== null) {
            $currentCount = DB::table('employees')
                ->where('status', '!=', 'archived')
                ->count();

            if ($currentCount >= $limit) {
                return response()->json([
                    'error'   => 'PLAN_EMPLOYEE_LIMIT_REACHED',
                    'message' => "Votre plan {$plan->name} est limité à {$limit} employés actifs.",
                    'data'    => [
                        'current_count' => $currentCount,
                        'plan_limit'    => $limit,
                        'plan'          => $plan->name,
                        'upgrade_url'   => config('app.url') . '/settings/billing',
                    ],
                ], 403);
            }
        }

        return $next($request);
    }
}
```

---

## ENREGISTREMENT

```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant'     => \App\Http\Middleware\TenantMiddleware::class,
        'plan.limit' => \App\Http\Middleware\PlanLimitMiddleware::class,
        'sub.check'  => \App\Http\Middleware\CheckSubscription::class,
    ]);
})
```

---

## UTILISATION DANS LES ROUTES

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'tenant', 'sub.check'])->group(function () {

    // Employees — limité par plan
    Route::post('/employees', [EmployeeController::class, 'store'])
         ->middleware('plan.limit:employees');
    Route::post('/employees/import', [EmployeeController::class, 'import'])
         ->middleware('plan.limit:employees');

    // Autres CRUD sans limite de plan
    Route::apiResource('/departments', DepartmentController::class);
    Route::apiResource('/schedules', ScheduleController::class);
});
```

---

## TEST OBLIGATOIRE

```php
// tests/Feature/PlanLimitTest.php

it('blocks employee creation when starter plan limit reached', function () {
    $company = Company::factory()->withPlan('starter')->create(); // max 20
    $token   = Employee::factory()->manager()->for($company)->createWithToken();

    // Créer 20 employés (limite)
    Employee::factory()->count(20)->for($company)->create(['status' => 'active']);

    $response = $this->withToken($token)
        ->postJson('/api/v1/employees', Employee::factory()->make()->toArray());

    $response->assertStatus(403)
             ->assertJson(['error' => 'PLAN_EMPLOYEE_LIMIT_REACHED']);
});

it('allows employee creation on business plan (200 limit)', function () {
    $company = Company::factory()->withPlan('business')->create();
    $token   = Employee::factory()->manager()->for($company)->createWithToken();
    Employee::factory()->count(50)->for($company)->create();

    $response = $this->withToken($token)
        ->postJson('/api/v1/employees', Employee::factory()->make()->toArray());

    $response->assertStatus(201);
});

it('never limits enterprise plan (null = unlimited)', function () {
    $company = Company::factory()->withPlan('enterprise')->create();
    $token   = Employee::factory()->manager()->for($company)->createWithToken();
    Employee::factory()->count(500)->for($company)->create();

    $response = $this->withToken($token)
        ->postJson('/api/v1/employees', Employee::factory()->make()->toArray());

    $response->assertStatus(201);
});
```

---

## RÈGLES MÉTIER

- Les employés `status = 'archived'` ne comptent PAS dans la limite
- La limite s'applique à la création (POST) et à l'import CSV (POST /employees/import)
- En cas d'import CSV qui ferait dépasser la limite : rejeter tout le batch avec 403
- `max_employees = NULL` = plan Enterprise = illimité (ne jamais bloquer)
