# CC-02 — Module Auth + Enregistrement public
# Agent : Claude Code
# Durée : 6-8 heures
# Prérequis : CC-01 vert (tous les tests passent, migrations OK)

---

## PRÉREQUIS VÉRIFIABLES

```bash
php artisan test --filter=Infrastructure  # doit passer à 100%
php artisan migrate:status               # toutes les migrations public = Ran
curl /api/v1/health                      # {"status":"ok"}
```

---

## DOCUMENTS À LIRE AVANT DE CODER

| Document | Section |
|---|---|
| `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` | Section 1 — Auth (payloads exacts) |
| `docs/dossierdeConception/07_securite_rbac/12_SECURITY_SPEC_COMPLETE.md` | Règles bcrypt, rate limiting |
| `docs/dossierdeConception/08_multitenancy/09_TENANT_SERVICE_SPEC.md` | TenantService 7 étapes |

---

## TESTS À ÉCRIRE EN PREMIER (avant tout code)

```php
// tests/Feature/Auth/LoginTest.php

it('returns token on valid credentials', function () {
    $company = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->managerPrincipal()->create([
        'password' => bcrypt('SecurePass123!')
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email'       => $employee->email,
        'password'    => 'SecurePass123!',
        'device_name' => 'Test iPhone 15',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'data' => [
                     'token',
                     'user' => ['id', 'first_name', 'last_name', 'email', 'role', 'manager_role'],
                     'company' => ['id', 'name', 'country', 'language', 'status'],
                 ]
             ]);
});

it('returns 401 on wrong password', function () {
    $this->postJson('/api/v1/auth/login', [
        'email'       => 'nobody@nowhere.com',
        'password'    => 'wrongpass',
        'device_name' => 'test',
    ])->assertStatus(401);
});

// TEST LE PLUS CRITIQUE DU PROJET
it('company A token cannot access company B employees', function () {
    $companyA = Company::factory()->withSchema()->create();
    $companyB = Company::factory()->withSchema()->create();

    $employeeA = Employee::factory()->inSchema($companyA)->create();
    $employeeB = Employee::factory()->inSchema($companyB)->create();

    $tokenA = $employeeA->createToken('test')->plainTextToken;

    // Avec le token de A, essayer d'accéder à l'employé B par son ID
    $this->withToken($tokenA)
         ->getJson("/api/v1/employees/{$employeeB->id}")
         ->assertStatus(404); // 404 et non 403 — l'employé n'existe pas dans le schéma A
});

it('suspended company returns 403', function () {
    $company = Company::factory()->withSchema()->create(['status' => 'suspended']);
    $employee = Employee::factory()->inSchema($company)->create();
    $token = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->getJson('/api/v1/auth/me')
         ->assertStatus(403)
         ->assertJson(['error' => 'COMPANY_SUSPENDED']);
});

it('expired subscription returns 403', function () {
    $company = Company::factory()->withSchema()->create(['subscription_status' => 'expired']);
    $employee = Employee::factory()->inSchema($company)->create();
    $token = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->getJson('/api/v1/auth/me')
         ->assertStatus(403)
         ->assertJson(['error' => 'SUBSCRIPTION_EXPIRED']);
});

it('logout invalidates token', function () {
    $company = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create();
    $token = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertStatus(200);
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('GET /auth/me returns full profile with company', function () {
    $company = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create();
    $token = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->getJson('/api/v1/auth/me')
         ->assertStatus(200)
         ->assertJsonStructure([
             'data' => [
                 'id', 'first_name', 'last_name', 'email', 'role', 'manager_role',
                 'company' => ['id', 'name', 'country', 'language'],
                 'leave_balance',
             ]
         ]);
});

it('public register creates company + manager + schema in one transaction', function () {
    $response = $this->postJson('/api/v1/public/register', [
        'company_name'        => 'Test SARL',
        'manager_first_name'  => 'Karim',
        'manager_last_name'   => 'Amrani',
        'manager_email'       => 'karim@testsaml.dz',
        'manager_password'    => 'SecurePass123!',
        'country'             => 'DZ',
        'estimated_employees' => 15,
        'plan'                => 'starter',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['data' => ['company', 'token', 'user']]);

    // Vérifier que la company est en DB schéma public
    $this->assertDatabaseHas('companies', [
        'email'  => 'karim@testsaml.dz',
        'status' => 'trial',
        'country' => 'DZ',
    ]);

    // Vérifier que le user_lookup est créé
    $this->assertDatabaseHas('user_lookups', [
        'email' => 'karim@testsaml.dz',
    ]);
});

it('rate limiting blocks after 5 failed login attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com', 'password' => 'wrong', 'device_name' => 'test'
        ]);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.com', 'password' => 'wrong', 'device_name' => 'test'
    ])->assertStatus(429); // Too Many Requests
});

it('fcm token is stored on device registration', function () {
    $company = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->inSchema($company)->create();
    $token = $employee->createToken('test')->plainTextToken;

    $this->withToken($token)
         ->postJson('/api/v1/auth/device/fcm', [
             'fcm_token'   => 'fake_fcm_token_xyz_123',
             'device_name' => 'iPhone 15 Pro',
             'platform'    => 'ios',
         ])
         ->assertStatus(200);

    // Vérifier dans le schéma du tenant
    // (nécessite helper pour switcher le schéma dans les tests)
    $this->assertDeviceExists($employee, 'fake_fcm_token_xyz_123');
});
```

---

## FICHIERS À CRÉER (dans cet ordre)

### 1. Factories (nécessaires pour les tests)

```php
// database/factories/CompanyFactory.php
// Méthode withSchema() : crée le schéma PostgreSQL + exécute les migrations tenant
// Méthode inSchema() sur EmployeeFactory : switche le search_path avant de créer l'employé
```

### 2. Models schéma public

```
app/Models/Public/Company.php
app/Models/Public/UserLookup.php
app/Models/Public/SuperAdmin.php
app/Models/Public/Plan.php
app/Models/Public/Invoice.php
```

Tous héritent d'un `PublicModel` qui force `$connection = 'pgsql'` et `$schema = 'public'`.

### 3. Model schéma tenant

```
app/Models/Tenant/Employee.php    ← Authenticatable + HasApiTokens
app/Models/Tenant/EmployeeDevice.php
```

Héritent d'un `TenantModel` qui n'a PAS de `$connection` fixe (le search_path est géré par TenantMiddleware).

### 4. Services

```php
// app/Services/TenantService.php
// Méthode createTenantSchema(Company $company) :
//   Étape 1 : créer le schéma PostgreSQL (company_{uuid})
//   Étape 2 : exécuter les migrations du dossier database/migrations/tenant/
//   Étape 3 : créer le premier employee (manager principal)
//   Étape 4 : créer le user_lookup (schéma public)
//   Étape 5 : créer le token Sanctum
//   Étape 6 : configurer les company_settings par défaut
//   Étape 7 : envoyer email de bienvenue
// TOUT dans une DB::transaction() — rollback complet si une étape échoue
```

### 5. Controllers

```
app/Http/Controllers/Auth/AuthController.php      → login, logout, me
app/Http/Controllers/Auth/RegisterController.php  → store (POST /public/register)
app/Http/Controllers/Auth/PasswordController.php  → forgot, reset
app/Http/Controllers/Auth/DeviceController.php    → fcm store/destroy
```

### 6. FormRequests

```
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/Auth/RegisterRequest.php
app/Http/Requests/Auth/ForgotPasswordRequest.php
app/Http/Requests/Auth/ResetPasswordRequest.php
app/Http/Requests/Auth/StoreFcmRequest.php
```

### 7. Resources

```
app/Http/Resources/EmployeeResource.php   → utilisé par /auth/me
app/Http/Resources/CompanyResource.php
```

---

## FLUX LOGIN — IMPLÉMENTATION EXACTE

```php
// app/Http/Controllers/Auth/AuthController.php
public function login(LoginRequest $request): JsonResponse
{
    // 1. Chercher dans user_lookups (schéma PUBLIC) par email
    $lookup = UserLookup::where('email', $request->email)->first();
    if (!$lookup) {
        return response()->json(['error' => 'INVALID_CREDENTIALS'], 401);
    }

    // 2. Switcher temporairement vers le bon schéma
    DB::statement("SET search_path TO {$lookup->schema_name}, public");

    // 3. Vérifier le password dans employees (schéma tenant)
    $employee = Employee::where('email', $request->email)->first();
    if (!$employee || !Hash::check($request->password, $employee->password)) {
        DB::statement("SET search_path TO public"); // remettre avant de retourner
        return response()->json(['error' => 'INVALID_CREDENTIALS'], 401);
    }

    // 4. Vérifier la company (status + subscription)
    $company = Company::find($lookup->company_id);
    if ($company->status === 'suspended') {
        return response()->json(['error' => 'COMPANY_SUSPENDED'], 403);
    }
    if ($company->subscription_status === 'expired') {
        return response()->json(['error' => 'SUBSCRIPTION_EXPIRED'], 403);
    }

    // 5. Créer le token Sanctum (nom = device_name)
    $token = $employee->createToken($request->device_name)->plainTextToken;

    // 6. Stocker le FCM token si présent
    if ($request->fcm_token) {
        EmployeeDevice::updateOrCreate(
            ['employee_id' => $employee->id, 'device_name' => $request->device_name],
            ['fcm_token' => $request->fcm_token, 'platform' => $request->platform ?? 'unknown', 'last_seen_at' => now()]
        );
    }

    // 7. Retourner token + profil
    return response()->json([
        'data' => [
            'token'   => $token,
            'user'    => new EmployeeResource($employee),
            'company' => new CompanyResource($company),
        ]
    ]);
}
```

---

## RÈGLES DE SÉCURITÉ OBLIGATOIRES

```php
// Rate limiting dans RouteServiceProvider ou directement dans api.php
Route::middleware(['throttle:5,1'])->group(function () {  // 5 req/min/IP
    Route::post('/auth/login', ...);
    Route::post('/auth/forgot-password', ...);
});

// Bcrypt cost minimum
'bcrypt' => ['rounds' => 12],  // dans config/hashing.php

// Token Sanctum : expiration 90 jours
// Dans config/sanctum.php :
'expiration' => 60 * 24 * 90,  // minutes → 90 jours

// JAMAIS de données sensibles dans le token payload
// Sanctum stocke uniquement l'ID — OK par défaut
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-03

```
[ ] php artisan test --filter=Auth → tous les tests passent
[ ] Test isolation tenant → 100% (company A ne voit pas company B)
[ ] Rate limiting → test 429 passe
[ ] POST /api/v1/auth/login → token valide retourné
[ ] GET /api/v1/auth/me → profil complet avec company
[ ] POST /api/v1/public/register → company + schéma créés en base
[ ] Aucun test des modules précédents ne régresse (php artisan test → 0 failure)
```

---

## COMMIT

```
feat: add complete auth module with multi-tenant login, FCM device registration
feat: add public registration with atomic TenantService (schema + migrations + manager)
test: add TenantIsolationTest — company A cannot access company B data
test: add rate limiting test — 429 after 5 failed attempts
```
