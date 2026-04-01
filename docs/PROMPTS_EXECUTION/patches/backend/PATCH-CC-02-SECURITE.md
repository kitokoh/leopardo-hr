# PATCH CC-02 — Sécurité complémentaire
# À appliquer PENDANT CC-02, après avoir implémenté le login de base
# Réf : 07_SECURITE_COMPLETE.md + 13_CHECK_SUBSCRIPTION_SPEC.md + 15_SUPERADMIN_MIDDLEWARE_SPEC.md

---

## PATCH 1 — Durées de session différentes par type de client

Le fichier `config/sanctum.php` doit gérer 3 durées différentes.
Une même valeur pour tous les clients est un bug de sécurité.

```php
// config/sanctum.php
'expiration' => null, // Désactiver la valeur globale — géré par client

// app/Http/Controllers/Auth/AuthController.php — login()
// Calculer l'expiration selon le type de client

private function getTokenExpiration(string $deviceName): ?Carbon
{
    // Super Admin → 4h (géré dans SuperAdminController, pas ici)
    // SPA Web (dashboard) → 8h d'inactivité (géré via cookie, pas token)
    // App mobile Flutter → 90 jours
    return now()->addDays(90);
}

// À la création du token Sanctum :
$token = $employee->createToken(
    name: $request->device_name,
    expiresAt: $this->getTokenExpiration($request->device_name)
);
```

**Pour le SPA web (Inertia.js) :**
```php
// config/session.php
'lifetime' => 480,          // 8 heures en minutes
'expire_on_close' => false, // Persiste entre fermetures d'onglet
```

**Pour le Super Admin :**
```php
// app/Http/Controllers/Admin/AdminAuthController.php
$token = $superAdmin->createToken(
    name: 'super-admin-session',
    expiresAt: now()->addHours(4) // 4h fixe
);
```

---

## PATCH 2 — Table super_admin_tokens + Double Provider Sanctum

Réf : `docs/dossierdeConception/07_securite_rbac/15_SUPERADMIN_MIDDLEWARE_SPEC.md`

### Migration à créer dans `database/migrations/public/`

```php
// create_super_admin_tokens_table.php
Schema::create('super_admin_tokens', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('super_admin_id');
    $table->string('name', 100);
    $table->string('token', 128)->unique();
    $table->text('abilities')->default('*');
    $table->timestampTz('last_used_at')->nullable();
    $table->timestampTz('expires_at')->nullable();
    $table->timestampTz('created_at')->default(DB::raw('NOW()'));

    $table->foreign('super_admin_id')
          ->references('id')->on('super_admins')
          ->onDelete('cascade');

    $table->index('super_admin_id', 'idx_sat_admin');
});
```

### Configuration auth

```php
// config/auth.php
'guards' => [
    'sanctum'     => ['driver' => 'sanctum', 'provider' => 'employees'],
    'super_admin' => ['driver' => 'sanctum', 'provider' => 'super_admins'],
],
'providers' => [
    'employees'   => ['driver' => 'eloquent', 'model' => \App\Models\Tenant\Employee::class],
    'super_admins'=> ['driver' => 'eloquent', 'model' => \App\Models\Public\SuperAdmin::class],
],
```

### SuperAdminMiddleware

```php
// app/Http/Middleware/SuperAdminMiddleware.php
public function handle(Request $request, Closure $next): mixed
{
    $bearerToken = $request->bearerToken();
    if (!$bearerToken) {
        return response()->json(['error' => 'UNAUTHENTICATED'], 401);
    }

    // Chercher d'abord dans super_admin_tokens (schéma public)
    $tokenHash = hash('sha256', $bearerToken);
    $token = DB::table('public.super_admin_tokens')
        ->where('token', $tokenHash)
        ->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->first();

    if (!$token) {
        return response()->json(['error' => 'FORBIDDEN_NOT_SUPER_ADMIN'], 403);
    }

    $superAdmin = \App\Models\Public\SuperAdmin::find($token->super_admin_id);
    if (!$superAdmin) {
        return response()->json(['error' => 'FORBIDDEN_NOT_SUPER_ADMIN'], 403);
    }

    // Mettre à jour last_used_at
    DB::table('public.super_admin_tokens')
        ->where('id', $token->id)
        ->update(['last_used_at' => now()]);

    // Injecter le super admin dans le request
    auth()->setUser($superAdmin);
    $request->merge(['_super_admin' => $superAdmin]);

    return $next($request);
}
```

---

## PATCH 3 — Middleware CheckSubscription

Réf : `docs/dossierdeConception/07_securite_rbac/13_CHECK_SUBSCRIPTION_SPEC.md`

```php
// app/Http/Middleware/CheckSubscription.php
class CheckSubscription
{
    private const GRACE_DAYS = 3;

    public function handle(Request $request, Closure $next): mixed
    {
        $company = app('current_company');

        // Suspendu manuellement → bloqué immédiatement
        if ($company->status === 'suspended') {
            return response()->json([
                'error'   => 'ACCOUNT_SUSPENDED',
                'message' => __('errors.ACCOUNT_SUSPENDED'),
            ], 403);
        }

        $diff = now()->diffInDays($company->subscription_end, false);

        // Expiré depuis plus de GRACE_DAYS → suspendre et bloquer
        if ($diff < -self::GRACE_DAYS) {
            $company->update(['status' => 'suspended']);
            return response()->json([
                'error'   => 'SUBSCRIPTION_EXPIRED',
                'message' => __('errors.SUBSCRIPTION_EXPIRED'),
            ], 403);
        }

        // Dans la période de grâce → accès lecture seule + header d'avertissement
        if ($diff < 0) {
            // Bloquer les requêtes d'écriture pendant la grâce
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                return response()->json([
                    'error'   => 'GRACE_PERIOD_READ_ONLY',
                    'message' => __('errors.GRACE_PERIOD_READ_ONLY'),
                ], 403);
            }
            $response = $next($request);
            $response->headers->set('X-Subscription-Grace', 'true');
            $response->headers->set('X-Subscription-Grace-Days-Left',
                (string) max(0, self::GRACE_DAYS + $diff));
            return $response;
        }

        return $next($request);
    }
}
```

### Enregistrement dans bootstrap/app.php

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant'      => \App\Http\Middleware\TenantMiddleware::class,
        'super.admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
        'sub.check'   => \App\Http\Middleware\CheckSubscription::class,
        'plan.limit'  => \App\Http\Middleware\CheckPlanLimit::class,
    ]);
})
```

### Routes — ordre des middlewares

```php
// routes/api.php — ordre OBLIGATOIRE : tenant AVANT sub.check
Route::middleware(['auth:sanctum', 'tenant', 'sub.check'])->group(function () {
    // Toutes les routes tenant
});

// Routes exemptées de sub.check :
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/auth/me', ...);     // Toujours accessible même en grâce
    Route::post('/auth/logout', ...);
    Route::get('/onboarding/status', ...); // Onboarding toujours accessible
});
```

---

## PATCH 4 — Commande cron CheckExpiredSubscriptions

```php
// app/Console/Commands/CheckExpiredSubscriptions.php
class CheckExpiredSubscriptions extends Command
{
    protected $signature   = 'subscriptions:check';
    protected $description = 'Suspend expired trials and subscriptions';

    public function handle(): void
    {
        $suspended = 0;

        // Suspendre les trials > 14 jours sans conversion
        $suspended += Company::where('status', 'trial')
            ->where('created_at', '<', now()->subDays(14))
            ->update(['status' => 'suspended']);

        // Suspendre les abonnements expirés depuis > 3 jours (hors période de grâce)
        $suspended += Company::where('status', 'active')
            ->where('subscription_end', '<', now()->subDays(3))
            ->update(['status' => 'suspended']);

        $this->info("$suspended company(ies) suspended at " . now());
        Log::info("subscriptions:check — $suspended suspended");
    }
}
```

Ajouter dans `routes/console.php` :
```php
Schedule::command('subscriptions:check')->dailyAt('02:00');
```

---

## TESTS OBLIGATOIRES

```php
// tests/Feature/Security/SubscriptionTest.php

it('blocks access after grace period', function () {
    $company = Company::factory()->withSchema()->create([
        'status'           => 'active',
        'subscription_end' => now()->subDays(4),
    ]);
    $employee = Employee::factory()->inSchema($company)->create();
    $token    = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->getJson('/api/v1/employees')
         ->assertStatus(403)
         ->assertJson(['error' => 'SUBSCRIPTION_EXPIRED']);
});

it('allows GET during grace period with warning header', function () {
    $company = Company::factory()->withSchema()->create([
        'status'           => 'active',
        'subscription_end' => now()->subDays(2),
    ]);
    $employee = Employee::factory()->inSchema($company)->create();
    $token    = $employee->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson('/api/v1/employees');

    $response->assertStatus(200);
    $response->assertHeader('X-Subscription-Grace', 'true');
    $response->assertHeader('X-Subscription-Grace-Days-Left', '1');
});

it('blocks POST during grace period', function () {
    $company = Company::factory()->withSchema()->create([
        'status'           => 'active',
        'subscription_end' => now()->subDays(1),
    ]);
    $employee = Employee::factory()->inSchema($company)->create();
    $token    = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->postJson('/api/v1/employees', ['first_name' => 'Test'])
         ->assertStatus(403)
         ->assertJson(['error' => 'GRACE_PERIOD_READ_ONLY']);
});

it('suspends trial after 14 days', function () {
    $company = Company::factory()->create([
        'status'     => 'trial',
        'created_at' => now()->subDays(15),
    ]);

    $this->artisan('subscriptions:check');

    expect($company->fresh()->status)->toBe('suspended');
});

it('super admin token is rejected on employee routes', function () {
    $superAdmin = SuperAdmin::factory()->create();
    $token      = $superAdmin->createToken('admin')->plainTextToken;

    $this->withToken($token)
         ->getJson('/api/v1/employees')
         ->assertStatus(403);
});

it('mobile token expires after 90 days', function () {
    $company  = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create();

    $token = $employee->createToken('iPhone', expiresAt: now()->addDays(90));

    // Simuler le passage du temps
    $this->travel(91)->days();

    $this->withToken($token->plainTextToken)
         ->getJson('/api/v1/auth/me')
         ->assertStatus(401);
});
```
