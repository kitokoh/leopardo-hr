# STRATÉGIE DE TESTS — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. NIVEAUX DE TESTS (PYRAMIDE)

### Tests Unitaires (PHPUnit/Pest)
- **Objectif** : Valider la logique métier pure (Services).
- **Cibles prioritaires** : `PayrollService`, `AttendanceService`, `AbsenceService`.
- **Exigence** : 100% de couverture sur les calculs (HS, Pénalités, Net à payer).

### Tests de Fonctionnalité (Feature Tests)
- **Objectif** : Valider les endpoints API et l'isolation.
- **Cibles prioritaires** : `Auth`, `TenantIsolation`, `CRUD Employees`.
- **Exigence** : 100% de succès sur le test d'isolation `TenantIsolationTest`.

### Tests Widget/Integration (Flutter)
- **Objectif** : Valider le flux utilisateur mobile.
- **Cibles prioritaires** : `LoginScreen`, `AttendanceScreen`, `AbsenceRequest`.

---

## 2. TESTS CRITIQUES (SCÉNARIOS BLOQUANTS)

### TenantIsolationTest
Vérifie qu'un employé de l'entreprise A ne peut jamais voir les données de l'entreprise B, même en changeant l'ID dans l'URL.

### PayrollCalculationTest
Vérifie que pour un brut donné, le net est calculé avec les bons taux de cotisation et tranches d'IR (modèle par pays).

### AttendanceConflictTest
Vérifie qu'un employé ne peut pas pointer deux fois l'arrivée sans avoir pointé le départ.

---

## 3. OUTILS ET AUTOMATISATION

- **Backend** : Pest PHP + Laravel Mocking.
- **Mobile** : Flutter Test + Mockito.
- **CI/CD** : GitHub Actions (Exécution auto à chaque Pull Request).


---

## 4. TESTS CRITIQUES COMPLÉMENTAIRES (ajoutés v2.0)

### PlanLimitTest
Vérifie que le middleware bloque la création d'employés au-delà de la limite du plan.
Voir spec complète : `07_securite_rbac/11_PLAN_LIMIT_MIDDLEWARE.md`

### PublicRegisterTest
```php
it('creates trial company on public registration', function () {
    $response = $this->postJson('/api/v1/public/register', [
        'company_name'        => 'Test SARL',
        'manager_email'       => 'manager@test.com',
        'manager_password'    => 'Secure123!',
        'country'             => 'DZ',
        'estimated_employees' => 10,
        'plan'                => 'starter',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['data' => ['company', 'token', 'user']]);

    $this->assertDatabaseHas('companies', ['email' => 'manager@test.com', 'status' => 'trial']);
    $this->assertDatabaseHas('user_lookups', ['email' => 'manager@test.com']);
});

it('rejects registration with existing email', function () {
    Company::factory()->create(['email' => 'existing@test.com']);

    $this->postJson('/api/v1/public/register', [
        'company_name'     => 'Autre SARL',
        'manager_email'    => 'existing@test.com',
        'manager_password' => 'Secure123!',
        'country'          => 'DZ',
        'estimated_employees' => 5,
    ])->assertStatus(422)
      ->assertJson(['error' => 'EMAIL_ALREADY_EXISTS']);
});
```

### SSENotificationTest
```php
it('streams notifications via SSE endpoint', function () {
    $employee = Employee::factory()->create();
    $token    = $employee->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->get('/api/v1/notifications/stream', ['Accept' => 'text/event-stream']);

    $response->assertStatus(200)
             ->assertHeader('Content-Type', 'text/event-stream');
});
```

### AdvanceStatusTest
```php
it('advance status transitions are valid', function () {
    $advance = SalaryAdvance::factory()->create(['status' => 'approved']);

    // Approuvée → Active (quand PayrollService commence le remboursement)
    $advance->update(['status' => 'active']);
    expect($advance->fresh()->status)->toBe('active');

    // Active → Repaid (quand amount_remaining <= 0)
    $advance->update(['status' => 'repaid', 'amount_remaining' => 0]);
    expect($advance->fresh()->status)->toBe('repaid');
});
```


---

## 5. TESTS COMPLÉMENTAIRES (ajoutés depuis pull ami — v3.3.0)

### CreateTenantTest
```php
// tests/Feature/TenantService/CreateTenantTest.php
it('creates a complete tenant in a single transaction', function () {
    $data = [
        'name' => 'TestCorp SPA', 'sector' => 'construction',
        'country' => 'DZ', 'city' => 'Alger',
        'email' => 'contact@testcorp.dz',
        'plan_id' => Plan::where('name', 'Business')->first()->id,
        'tenancy_type' => 'shared', 'language' => 'fr',
        'manager_first_name' => 'Karim', 'manager_last_name' => 'Bensalem',
        'manager_email' => 'karim@testcorp.dz', 'manager_password' => 'SecurePass123!',
    ];
    $company = app(TenantService::class)->createTenant($data);

    expect($company->status)->toBe('trial');
    expect(UserLookup::where('email', 'karim@testcorp.dz')->exists())->toBeTrue();
    // Vérifier : employees, company_settings, absence_types, schedule créés
});

it('rolls back everything if manager email already exists', function () {
    // Aucune company ne doit être créée si user_lookups.email existe déjà
    expect(fn() => app(TenantService::class)->createTenant([
        'manager_email' => 'exists@test.dz', ...
    ]))->toThrow(\Exception::class);
    expect(Company::where('email', 'contact@test.dz')->exists())->toBeFalse();
});
```

### SubscriptionTest
```php
// tests/Feature/SubscriptionTest.php
it('blocks access after grace period', function () {
    // subscription_end = -4 jours → hors grâce (3 jours) → 403
    $company = Company::factory()->create(['status' => 'active', 'subscription_end' => now()->subDays(4)]);
    $this->withToken($token)->getJson('/api/v1/employees')->assertStatus(403)
         ->assertJson(['error' => 'SUBSCRIPTION_EXPIRED']);
});

it('allows access during grace period with X-Subscription-Grace header', function () {
    // subscription_end = -2 jours → dans la grâce → 200 + header
    $response = $this->withToken($token)->getJson('/api/v1/employees');
    $response->assertStatus(200)->assertHeader('X-Subscription-Grace', 'true');
});

it('suspends trial after 14 days without upgrade', function () {
    $company = Company::factory()->create(['status' => 'trial', 'created_at' => now()->subDays(15)]);
    $this->artisan('subscriptions:check');
    expect($company->fresh()->status)->toBe('suspended');
});
```
Voir spec complète : `07_securite_rbac/13_CHECK_SUBSCRIPTION_SPEC.md`
