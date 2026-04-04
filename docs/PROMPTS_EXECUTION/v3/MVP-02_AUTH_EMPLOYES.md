# MVP-02 — Auth + CRUD Employés
# Agent : Claude Code
# Durée : 6-8 heures
# Prérequis : MVP-01 vert (tous les tests passent)

---

## CE QUE TU FAIS

Implémenter l'authentification (login/logout/me) via Sanctum et le CRUD employés basique (list/create/show/update/archive). RBAC simplifié : 2 rôles (manager, employee).

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` à la racine (5 min)
2. Vérifie : `php artisan test` → 0 failure (sinon STOP)
3. Lis `docs/dossierdeConception/01_API_CONTRATS_COMPLETS/02_API_CONTRATS_COMPLET.md` — sections Auth + Employees
4. Lis `docs/dossierdeConception/07_securite_rbac/12_SECURITY_SPEC_COMPLETE.md` — rate limiting + bcrypt

---

## ENDPOINTS À IMPLÉMENTER

### Auth (3 endpoints)

| Méthode | Route | Description | Auth |
|---------|-------|-------------|------|
| POST | `/api/v1/auth/login` | Email + password → token | Non |
| POST | `/api/v1/auth/logout` | Révoque le token actuel | Oui |
| GET | `/api/v1/auth/me` | Profil + company | Oui |

### Employés (5 endpoints)

| Méthode | Route | Description | Auth | RBAC |
|---------|-------|-------------|------|------|
| GET | `/api/v1/employees` | Liste (paginée) | Oui | Manager: tous, Employee: interdit |
| POST | `/api/v1/employees` | Créer un employé | Oui | Manager uniquement |
| GET | `/api/v1/employees/{id}` | Détail | Oui | Manager: tous, Employee: sa fiche |
| PUT | `/api/v1/employees/{id}` | Modifier | Oui | Manager uniquement |
| DELETE | `/api/v1/employees/{id}` | Archiver (soft delete) | Oui | Manager uniquement |

---

## FLUX LOGIN (obligatoire)

```
1. Recevoir email + password + device_name
2. Chercher email dans user_lookups (schéma public) → trouver company_id + schema
3. Charger l'employé correspondant (avec company_id via le scope)
4. Vérifier Hash::check(password, employee.password_hash)
5. Vérifier company.status !== 'suspended'
6. Créer token Sanctum
7. Retourner { token, user: EmployeeResource, company: CompanyResource }
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/Auth/AuthController.php
app/Http/Controllers/EmployeeController.php
app/Http/Requests/Auth/LoginRequest.php
app/Http/Requests/Employee/StoreEmployeeRequest.php
app/Http/Requests/Employee/UpdateEmployeeRequest.php
app/Http/Resources/EmployeeResource.php
app/Http/Resources/CompanyResource.php
app/Services/AuthService.php
app/Services/EmployeeService.php
app/Policies/EmployeePolicy.php
database/factories/CompanyFactory.php
database/factories/EmployeeFactory.php
```

---

## TESTS À ÉCRIRE (avant le code)

```php
// tests/Feature/Auth/LoginTest.php
it('returns token on valid credentials');
it('returns 401 on wrong password');
it('returns 401 on unknown email');
it('suspended company returns 403');
it('logout invalidates token');
it('me returns profile with company');
it('rate limiting blocks after 5 failed attempts');

// tests/Feature/Employee/EmployeeCrudTest.php
it('manager can list employees');
it('employee cannot list employees');
it('manager can create employee');
it('employee cannot create employee');
it('manager can view any employee');
it('employee can only view self');
it('manager can update employee');
it('manager can archive employee');
it('archived employee disappears from list');

// tests/Feature/TenantIsolationTest.php (compléter MVP-01)
it('company A token cannot access company B employees via API');
it('company A manager cannot create employee in company B');
```

---

## RÈGLES CRITIQUES

- Login : NE JAMAIS révéler si l'email existe → toujours "INVALID_CREDENTIALS"
- Rate limiting : 5 tentatives / 15 min / IP sur le login
- Bcrypt : rounds = 12 (dans config/hashing.php)
- Token : expiration = 90 jours (dans config/sanctum.php)
- Password : minimum 8 caractères, 1 majuscule, 1 chiffre
- Créer un `user_lookups` à chaque création d'employé
- `password_hash` : nom du champ en DB — Laravel utilise `password` dans le modèle via mutator

---

## PORTE VERTE → MVP-03

```
[ ] php artisan test → 0 failure (incluant tests MVP-01)
[ ] POST /auth/login → token valide
[ ] POST /auth/logout → token révoqué
[ ] GET /auth/me → profil + company
[ ] GET /employees → liste paginée (manager uniquement)
[ ] POST /employees → créer employé (manager uniquement)
[ ] GET /employees/{id} → détail (manager ou self)
[ ] PUT /employees/{id} → modifier (manager uniquement)
[ ] DELETE /employees/{id} → archiver (manager uniquement)
[ ] Isolation tenant → company A ≠ company B via API
[ ] Rate limiting → 429 après 5 tentatives
```

---

## COMMIT

```
feat(auth): multi-tenant login with Sanctum + user_lookups
feat(employees): CRUD with manager/employee RBAC + soft delete
test(auth): login, logout, rate limiting, tenant isolation
test(employees): CRUD permissions + self-access
```
