# PROMPT CC-02 — Module Auth Complet
# Agent : CLAUDE CODE
# Phase : Semaine 2
# Prérequis : CC-01 terminé et validé

---

## MISSION

Implémenter le module d'authentification complet de Leopardo RH.
C'est le module le plus critique : tout le reste en dépend.

---

## ENDPOINTS À IMPLÉMENTER (dans l'ordre)

```
POST   /api/v1/public/register     ← Auto-onboarding public (sans auth) — VOIR SPEC COMPLÈTE dans API_CONTRATS
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
POST   /api/v1/auth/refresh
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/device/fcm
DELETE /api/v1/auth/device/fcm
```

> **Note :** `POST /public/register` est exempt du middleware `auth:sanctum` et `tenant`.
> Il crée la company + le premier manager + un token Sanctum en une seule transaction atomique.
> Voir payload complet dans `01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` section 1.

Payloads JSON exacts : voir `01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` — Section 1.

---

## FLUX LOGIN — IMPLÉMENTATION EXACTE

```php
// AuthController::login()
// 1. Valider email + password + device_name + fcm_token
// 2. Chercher dans user_lookups (schéma PUBLIC) par email → obtenir company_id + schema_name
// 3. Switcher vers le bon schéma : SET search_path TO {schema_name}, public
// 4. Vérifier le password dans employees (schéma tenant)
// 5. Vérifier que la company est active (pas suspended, pas subscription_expired)
// 6. Créer le Sanctum token avec nom = device_name
// 7. Stocker le fcm_token dans employee_devices (schéma tenant)
// 8. Retourner le token + user + company

// JAMAIS : chercher l'utilisateur dans tous les schémas — ça ne scale pas
// TOUJOURS : passer par user_lookups pour trouver le bon schéma
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Auth/AuthController.php
app/Http/Controllers/Auth/RegisterController.php  ← Nouveau: gère POST /public/register
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/Auth/ResetPasswordRequest.php
app/Http/Requests/Auth/RegisterFcmRequest.php
app/Http/Resources/EmployeeResource.php       ← utilisé par GET /auth/me
app/Http/Resources/CompanyResource.php
app/Http/Middleware/TenantMiddleware.php       ← si pas encore créé
app/Http/Middleware/SuperAdminMiddleware.php
app/Http/Middleware/CheckSubscription.php
app/Models/Public/UserLookup.php              ← modèle sur schéma public
app/Models/Public/Company.php
app/Models/Tenant/Employee.php
app/Models/Tenant/EmployeeDevice.php
```

---

## TESTS OBLIGATOIRES (écrire AVANT les controllers)

```php
// tests/Feature/Auth/LoginTest.php

it('returns token on valid credentials', function () {
    // Setup: créer une company + employee dans le bon schéma
    $company = Company::factory()->withSchema()->create();
    $employee = Employee::factory()->for($company)->create(['password' => bcrypt('pass123')]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $employee->email,
        'password' => 'pass123',
        'device_name' => 'Test iPhone',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'role'], 'user' => ['company']]]);
});

it('returns 401 on wrong password', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'wrong@test.com',
        'password' => 'wrongpass',
        'device_name' => 'Test',
    ])->assertStatus(401);
});

it('company A token cannot access company B employees', function () {
    // LE TEST LE PLUS CRITIQUE DU PROJET — 100% coverage obligatoire
    $companyA = Company::factory()->withSchema()->create();
    $companyB = Company::factory()->withSchema()->create();
    $employeeA = Employee::factory()->for($companyA)->create();
    $employeeB = Employee::factory()->for($companyB)->create();

    $tokenA = $employeeA->createToken('test')->plainTextToken;

    // Avec le token de A, essayer d'accéder à l'employé B
    $this->withToken($tokenA)
         ->getJson("/api/v1/employees/{$employeeB->id}")
         ->assertStatus(404); // Pas 403 — l'employé n'existe pas dans le schéma A
});

it('suspended company returns 403', function () { ... });
it('logout invalidates token', function () { ... });
it('GET /auth/me returns full profile', function () { ... });
it('fcm token is stored on login', function () { ... });
it('fcm token is removed on DELETE', function () { ... });
```

---

## RÈGLES DE SÉCURITÉ

- Password : `bcrypt` avec `cost >= 12` — jamais MD5/SHA1
- Token Sanctum : expiration 90 jours (configurable via settings)
- Rate limiting sur `/auth/login` : 5 tentatives / minute / IP
- Le token contient uniquement l'ID — pas de données sensibles en payload

---

## COMMIT ATTENDU

```
feat: add complete auth module with multi-tenant login, FCM device registration
test: add TenantIsolationTest — company A cannot access company B data
```

---

## RÉSULTAT ATTENDU

- [ ] `POST /auth/login` retourne token + profil complet
- [ ] `GET /auth/me` retourne profil avec company
- [ ] Test isolation tenant passe à 100%
- [ ] Rate limiting actif sur /auth/login
- [ ] FCM token stocké et supprimable
