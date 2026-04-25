# Plan d'Action d'Amelioration - Leopardo RH Backend

> **Version** : 4.1.70  
> **Date** : 25/04/2026  
> **Source** : Rapport d'analyse technique Leopardo RH  
> **Auteur** : Devin (Cognition AI)

Ce document liste les **15 actions d'amelioration** identifiees lors de l'audit technique du backend. Chaque action est suffisamment detaillee pour etre implementee directement par un developpeur.

**Legende** : `[ ]` = a faire, `[x]` = fait

---

## Table des matieres

1. [Vue d'ensemble et priorisation](#1-vue-densemble-et-priorisation)
2. [Tableau recapitulatif](#2-tableau-recapitulatif)
3. [Phase 1 : Securite (avant go-live)](#phase-1--securite-avant-go-live)
4. [Phase 2 : Qualite du code](#phase-2--qualite-du-code)
5. [Phase 3 : Robustesse & Monitoring](#phase-3--robustesse--monitoring)
6. [Phase 4 : Scalabilite](#phase-4--scalabilite)
7. [Calendrier propose](#calendrier-propose)

---

## 1. Vue d'ensemble et priorisation

| Phase | Theme | Actions | Effort total | Prerequis |
|-------|-------|---------|-------------|-----------|
| **Phase 1** | Securite | 4 actions (P0) | ~7h | Aucun - **BLOQUANT avant go-live** |
| **Phase 2** | Qualite du code | 4 actions (P1) | ~14h | Phase 1 mergee |
| **Phase 3** | Robustesse & Monitoring | 4 actions (P1/P2) | ~21h | Phase 2 mergee |
| **Phase 4** | Scalabilite | 3 actions (P2) | ~10h | Phase 3 mergee |

**Effort total estime** : ~52 heures (~7 jours de travail)

---

## 2. Tableau recapitulatif

| # | Action | Prio | Effort | Phase | Risque si ignore | Fichiers cles |
|---|--------|------|--------|-------|-----------------|---------------|
| 1 | EncryptedCast donnees sensibles | P0 | 2h | Securite | Fuite donnees | `Employee.php` |
| 2 | Retirer google-services.json | P0 | 1h | Securite | Cles exposees | `.gitignore`, CI |
| 3 | Configurer CORS | P0 | 1h | Securite | Mobile bloque | `cors.php` |
| 4 | Lockout anti-brute-force | P0 | 3h | Securite | Attaque auth | `AuthService.php` |
| 5 | Realigner PILOTAGE.md | P1 | 2h | Qualite | Confusion | `PILOTAGE.md` |
| 6 | JsonResource Laravel | P1 | 6h | Qualite | Code duplique | `Resources/*.php` |
| 7 | Centraliser search_path | P1 | 4h | Qualite | Bug tenant | `TenantManager.php` |
| 8 | Pagination Dashboard | P1 | 2h | Qualite | Perf degrade | `DashboardCtrl.php` |
| 9 | 2FA super-admin | P1 | 6h | Robustesse | Acces non auth | `SuperAdmin`, login |
| 10 | Monitoring Sentry | P1 | 3h | Robustesse | Bugs silencieux | `config/sentry` |
| 11 | DTOs pour services | P2 | 8h | Robustesse | Type safety | `app/DTOs/*.php` |
| 12 | Rollback auto deploy | P2 | 4h | Robustesse | Downtime | `deploy-main.yml` |
| 13 | Rate limit par company | P2 | 4h | Scalabilite | Abus API | Middleware |
| 14 | Geler mode schema | P2 | 2h | Scalabilite | Complexite | Docs, code |
| 15 | Internationalisation | P2 | 4h | Scalabilite | Marche limite | `lang/*.php` |

---

## Phase 1 : Securite (avant go-live)

### [ ] ACTION 1 : Activer EncryptedCast sur les donnees sensibles

> **Priorite** : P0 - BLOQUANT | **Effort** : 2h

**Risque** : Fuite de donnees personnelles (IBAN, national_id) en cas de breach DB. Non-conformite RGPD et loi algerienne 18-07.

**Fichiers concernes** :
- `api/app/Models/Employee.php` (modification)
- `api/database/migrations/tenant/` (nouvelle migration)
- `api/tests/` (nouveau test)

**Etape 1** : Modifier le modele Employee pour ajouter les casts chiffres.

```php
// Dans api/app/Models/Employee.php
// Ajouter dans le tableau $casts :

protected $casts = [
    // ... casts existants ...
    'iban' => 'encrypted',
    'bank_account' => 'encrypted',
    'national_id' => 'encrypted',
];
```

**Etape 2** : Verifier que `APP_KEY` est bien configure dans `.env` et `.env.example`.

```env
# Dans api/.env.example, verifier :
APP_KEY=  # Genere par php artisan key:generate
```

**Etape 3** : Creer une migration pour chiffrer les donnees existantes en base.

```php
// Fichier : api/database/migrations/tenant/2026_xx_xx_encrypt_existing_sensitive_data.php

public function up(): void
{
    Employee::withoutGlobalScopes()->chunk(100, function ($employees) {
        foreach ($employees as $employee) {
            // Le simple fait de sauvegarder declenche le EncryptedCast
            $employee->save();
        }
    });
}
```

**Etape 4** : Ecrire un test unitaire pour verifier le chiffrement.

```php
// tests/Unit/EmployeeEncryptionTest.php

it('chiffre iban en base', function () {
    $employee = Employee::factory()->create([
        'iban' => 'DZ123456789',
    ]);
    $raw = DB::table('employees')
        ->where('id', $employee->id)
        ->value('iban');
    expect($raw)->not->toBe('DZ123456789');
    expect($employee->fresh()->iban)->toBe('DZ123456789');
});
```

**Criteres d'acceptation** :
- [ ] Les colonnes `iban`, `bank_account`, `national_id` sont chiffrees en base
- [ ] Le modele Employee dechiffre transparentement ces champs
- [ ] Un test unitaire valide le chiffrement/dechiffrement
- [ ] Les donnees existantes sont migrees

---

### [ ] ACTION 2 : Retirer google-services.json du depot

> **Priorite** : P0 - BLOQUANT | **Effort** : 1h

**Risque** : Cles Firebase exposees publiquement dans le depot GitHub.

**Fichiers concernes** :
- `api/google-services.json` (suppression du tracking)
- `.gitignore` (modification)
- `.github/workflows/deploy-main.yml` (modification)

**Etape 1** : Ajouter au `.gitignore`.

```gitignore
google-services.json
**/google-services.json
```

**Etape 2** : Supprimer du tracking Git.

```bash
git rm --cached api/google-services.json
git rm --cached mobile/android/app/google-services.json 2>/dev/null
```

**Etape 3** : Configurer comme secret GitHub Actions.

```yaml
# Dans deploy-main.yml ou mobile-distribute.yml
- name: Write google-services.json
  run: |
    echo '${{ secrets.GOOGLE_SERVICES_JSON }}' > mobile/android/app/google-services.json
```

**Criteres d'acceptation** :
- [ ] Aucun fichier `google-services.json` n'est tracke par Git
- [ ] Le `.gitignore` empeche tout ajout futur
- [ ] La CI mobile fonctionne via le secret GitHub
- [ ] Le fichier n'apparait plus dans les futurs commits

---

### [ ] ACTION 3 : Configurer le middleware CORS

> **Priorite** : P0 - BLOQUANT | **Effort** : 1h

**Risque** : L'app mobile Flutter sera bloquee par les restrictions CORS.

**Fichiers concernes** :
- `api/config/cors.php` (creation ou modification)
- `api/bootstrap/app.php` (verification)

**Etape 1** : Publier la config CORS.

```bash
php artisan config:publish cors
```

**Etape 2** : Configurer les origines autorisees.

```php
// api/config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
        env('APP_URL', 'http://localhost'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

**Etape 3** : Ajouter `FRONTEND_URL` dans `.env.example`.

```env
FRONTEND_URL=http://localhost:3000
```

**Criteres d'acceptation** :
- [ ] Les requetes cross-origin depuis l'app mobile et le frontend web fonctionnent
- [ ] Les cookies Sanctum sont envoyes correctement (`supports_credentials=true`)
- [ ] Un test Feature valide les headers CORS

---

### [ ] ACTION 4 : Implementer le lockout anti-brute-force

> **Priorite** : P0 - BLOQUANT | **Effort** : 3h

**Risque** : Attaque brute-force sur les endpoints de login sans protection.

**Fichiers concernes** :
- `api/app/Services/AuthService.php` (modification)
- `api/database/migrations/tenant/` (nouvelle migration)
- `api/app/Exceptions/AccountLockedException.php` (creation)
- `api/tests/Feature/AuthLoginGuardrailsTest.php` (enrichissement)

**Etape 1** : Ajouter une migration pour les colonnes de lockout.

```php
Schema::table('employees', function (Blueprint $t) {
    $t->unsignedSmallInteger('failed_login_attempts')->default(0);
    $t->timestampTz('locked_until')->nullable();
});
```

**Etape 2** : Modifier `AuthService::login()` pour gerer le lockout.

```php
// Apres avoir trouve l'employee :

if ($employee->locked_until && $employee->locked_until->isFuture()) {
    throw new AccountLockedException($employee->locked_until);
}

if (!Hash::check($password, $employee->password_hash)) {
    $employee->increment('failed_login_attempts');
    if ($employee->failed_login_attempts >= 5) {
        $employee->locked_until = now()->addMinutes(15);
        $employee->save();
    }
    throw new InvalidCredentialsException;
}

// En cas de succes, reset le compteur :
$employee->failed_login_attempts = 0;
$employee->locked_until = null;
```

**Etape 3** : Creer l'exception `AccountLockedException`.

```php
// api/app/Exceptions/AccountLockedException.php
class AccountLockedException extends DomainException
{
    public function __construct(Carbon $until)
    {
        parent::__construct(
            "Compte verrouille jusqu'a " . $until->toIso8601String(),
            423,
            'ACCOUNT_LOCKED'
        );
    }
}
```

**Criteres d'acceptation** :
- [ ] Apres 5 tentatives echouees, le compte est verrouille 15 minutes
- [ ] Un login reussi remet le compteur a zero
- [ ] L'API retourne `ACCOUNT_LOCKED` avec la date de deblocage
- [ ] Tests : incrementation, lockout effectif, reset apres succes

---

## Phase 2 : Qualite du code

### [ ] ACTION 5 : Realigner PILOTAGE.md sur le code reel

> **Priorite** : P1 - IMPORTANT | **Effort** : 2h

**Risque** : Confusion pour les nouveaux contributeurs entre la doc et la realite du code.

**Fichiers concernes** :
- `PILOTAGE.md` (modification)
- `docs/GESTION_PROJET/CORRECTIONS.md` (mise a jour)

**Sections a mettre a jour** :
1. **ARCHITECTURE MVP** : Remplacer "Mode SHARED uniquement" par "Mode SHARED (MVP) + Schema (Enterprise, implemente mais gele)"
2. **SCOPE MVP** : Ajouter les features reellement implementees (biometrie, kiosque ZKTeco, multi sous-roles) dans le tableau
3. **RBAC MVP** : Documenter les 6 sous-roles reels : `principal`, `rh`, `dept`, `comptable`, `superviseur`, `employee`
4. **Multitenancy MVP** : Mentionner que le code supporte le mode schema mais qu'il est gele
5. Ajouter une reference a ce plan d'action en bas du fichier

**Criteres d'acceptation** :
- [ ] Plus aucune contradiction entre PILOTAGE.md et le code sur `main`
- [ ] Un nouveau contributeur comprend l'etat reel du projet en lisant PILOTAGE.md

---

### [ ] ACTION 6 : Migrer vers des JsonResource Laravel

> **Priorite** : P1 - IMPORTANT | **Effort** : 6h

**Risque** : Duplication de code de serialisation dans 5+ controleurs, incoherences de format API.

**Fichiers a creer** :
- `api/app/Http/Resources/EmployeeResource.php`
- `api/app/Http/Resources/EmployeeListResource.php`
- `api/app/Http/Resources/AttendanceLogResource.php`
- `api/app/Http/Resources/DailySummaryResource.php`
- `api/app/Http/Resources/QuickEstimateResource.php`

**Fichiers a modifier** :
- `api/app/Http/Controllers/Api/V1/AuthController.php`
- `api/app/Http/Controllers/Api/V1/EmployeeController.php`
- `api/app/Http/Controllers/Api/V1/AttendanceController.php`
- `api/app/Http/Controllers/Api/V1/EstimationController.php`
- `api/app/Http/Controllers/Api/V1/MeController.php`

**Etape 1** : Creer les Resources.

```bash
php artisan make:resource EmployeeResource
php artisan make:resource EmployeeListResource
php artisan make:resource AttendanceLogResource
```

**Etape 2** : Extraire la logique de serialisation existante.

```php
// api/app/Http/Resources/EmployeeResource.php
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'role' => $this->role,
            'manager_role' => $this->manager_role,
            'status' => $this->status,
            // ... tous les champs necessaires ...
            'capabilities' => $this->when(
                $request->routeIs('auth.*'),
                fn() => $this->capabilities()
            ),
        ];
    }
}
```

**Etape 3** : Remplacer dans les controleurs.

```php
// Avant :
return new JsonResponse(['data' => ['id' => $employee->id, ...]]);

// Apres :
return new EmployeeResource($employee);
```

**Criteres d'acceptation** :
- [ ] Aucune methode `serializeEmployee()` ou `serialize*()` dans les controleurs
- [ ] Tous les endpoints API renvoient le meme format pour un meme modele
- [ ] Les 98 tests existants passent sans modification
- [ ] Le contrat API est inchange pour les consommateurs

---

### [ ] ACTION 7 : Centraliser la gestion du search_path

> **Priorite** : P1 - IMPORTANT | **Effort** : 4h

**Risque** : Bug de fuite de donnees si un `search_path` n'est pas restaure correctement.

**Fichier a creer** :
- `api/app/Services/TenantManager.php`

**Fichiers a modifier** :
- `api/app/Http/Middleware/TenantMiddleware.php`
- `api/app/Services/CompanyProvisioningService.php`
- `api/app/Services/UserInvitationService.php`
- `api/app/Services/KioskAttendanceService.php`

**Etape 1** : Creer le service `TenantManager`.

```php
// api/app/Services/TenantManager.php
class TenantManager
{
    private string $previousPath = 'public';

    public function setTenant(Company $company): void
    {
        $this->previousPath = DB::selectOne('SHOW search_path')->search_path ?? 'public';
        $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $company->schema_name ?: 'shared_tenants') ?: 'shared_tenants';
        DB::statement('SET search_path TO "' . $schema . '",public');
        app()->instance('current_company', $company);
    }

    public function resetToPrevious(): void
    {
        DB::statement('SET search_path TO ' . $this->previousPath);
    }

    public function withinTenant(Company $company, Closure $cb): mixed
    {
        $this->setTenant($company);
        try {
            return $cb();
        } finally {
            $this->resetToPrevious();
        }
    }
}
```

**Etape 2** : Enregistrer comme singleton dans `AppServiceProvider`.

```php
// AppServiceProvider::register()
$this->app->singleton(TenantManager::class);
```

**Etape 3** : Remplacer tous les appels directs `DB::statement('SET search_path...')` par `TenantManager`.

**Criteres d'acceptation** :
- [ ] Zero appel direct a `SET search_path` en dehors de `TenantManager`
- [ ] `TenantMiddleware` utilise `TenantManager::setTenant()`
- [ ] `withinTenant()` garantit la restauration via `try/finally`
- [ ] `MultiTenantSharedIsolationTest` et `TenantIsolationTest` passent

---

### [ ] ACTION 8 : Ajouter la pagination au DashboardController

> **Priorite** : P1 - IMPORTANT | **Effort** : 2h

**Risque** : Performance degradee pour les entreprises avec 30+ employes.

**Fichiers concernes** :
- `api/app/Http/Controllers/Web/DashboardController.php`
- `api/resources/views/dashboard.blade.php`

**Etape 1** : Remplacer `->get()` par `->paginate()`.

```php
// Avant :
$employees = Employee::query()->orderBy('last_name')->get();

// Apres :
$perPage = max(1, min(50, (int) request()->integer('per_page', 20)));
$employees = Employee::query()->orderBy('last_name')->paginate($perPage);
```

**Etape 2** : Ajouter les liens de pagination dans la vue Blade.

```blade
{{-- En bas du tableau dans dashboard.blade.php --}}
{{ $employees->links() }}
```

**Etape 3** : Adapter le calcul des statistiques pour couvrir TOUS les employes (pas seulement la page courante).

```php
// Stats globales (hors pagination) :
$statsQuery = Employee::query();
$totalEmployees = $statsQuery->count();
// ... calcul present/late sur tout le set
```

**Criteres d'acceptation** :
- [ ] Le dashboard affiche 20 employes par page par defaut
- [ ] Les statistiques couvrent TOUS les employes
- [ ] Le parametre `?per_page=` est respecte (min 1, max 50)
- [ ] `WebManagerPagesTest` passe toujours

---

## Phase 3 : Robustesse & Monitoring

### [ ] ACTION 9 : Implementer le 2FA super-admin

> **Priorite** : P1 - IMPORTANT | **Effort** : 6h

**Risque** : Acces non autorise a la console super-admin (gestion de toutes les companies).

**Fichiers concernes** :
- `api/app/Models/SuperAdmin.php` (modification)
- `api/app/Http/Controllers/Web/PlatformAuthController.php` (modification)
- `api/resources/views/platform/auth/two-factor.blade.php` (creation)
- `api/composer.json` (ajout dependance)

**Implementation** :
1. Ajouter la dependance : `composer require pragmarx/google2fa-laravel`
2. Modifier le flow de login platform pour verifier le code TOTP apres le mot de passe
3. Creer une vue `two-factor.blade.php` avec champ de saisie du code 6 chiffres
4. Ajouter une commande Artisan pour setup/reset le 2FA

```bash
php artisan superadmin:setup-2fa admin@example.com
# Affiche le QR code et le secret TOTP
```

**Criteres d'acceptation** :
- [ ] Le super-admin doit saisir un code TOTP apres le mot de passe
- [ ] `two_fa_secret` est stocke chiffre en base
- [ ] Commande Artisan pour setup/reset
- [ ] Tests : login sans TOTP echoue, login avec TOTP valide reussit

---

### [ ] ACTION 10 : Ajouter un monitoring structure (Sentry)

> **Priorite** : P1 - IMPORTANT | **Effort** : 3h

**Risque** : Bugs silencieux en production, pas de visibilite sur les erreurs reelles.

**Implementation** :

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

Configurer dans `.env.example` :

```env
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2
```

Enrichir le contexte Sentry avec le tenant :

```php
// Dans TenantMiddleware, apres setTenant :
Sentry\configureScope(function ($scope) use ($company) {
    $scope->setTag('company_id', $company->id);
    $scope->setTag('company', $company->name);
});
```

Ajouter `SENTRY_LARAVEL_DSN` aux secrets Render.

**Criteres d'acceptation** :
- [ ] Les exceptions non gerees remontent dans Sentry avec le contexte company
- [ ] Le healthcheck n'est pas affecte par Sentry
- [ ] Le DSN n'est pas commite dans le code

---

### [ ] ACTION 11 : Introduire des DTOs pour les services

> **Priorite** : P2 - RECOMMANDE | **Effort** : 8h

**Risque** : Payloads non types, risque d'erreurs silencieuses.

**Fichiers a creer** :
- `api/app/DTOs/CreateEmployeeDTO.php`
- `api/app/DTOs/UpdateEmployeeDTO.php`
- `api/app/DTOs/CheckInDTO.php`
- `api/app/DTOs/CompanyProvisioningDTO.php`

**Exemple** :

```php
// api/app/DTOs/CreateEmployeeDTO.php
final readonly class CreateEmployeeDTO
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $phone = null,
        public string $role = 'employee',
        public ?string $manager_role = null,
        public ?string $password = null,
        public bool $send_invitation = false,
    ) {}

    public static function fromRequest(StoreEmployeeRequest $request): self
    {
        return new self(...$request->validated());
    }
}
```

Modifier les services pour accepter des DTOs au lieu d'arrays :

```php
// Avant :
public function create(array $payload, ?Employee $actor): Employee

// Apres :
public function create(CreateEmployeeDTO $dto, ?Employee $actor): Employee
```

**Criteres d'acceptation** :
- [ ] Les services n'acceptent plus de arrays bruts
- [ ] Chaque DTO documente explicitement les champs avec leurs types
- [ ] Les tests existants passent apres adaptation

---

### [ ] ACTION 12 : Rollback automatique post-deploiement

> **Priorite** : P2 - RECOMMANDE | **Effort** : 4h

**Risque** : Downtime prolonge si un deploiement casse la production.

**Fichier concerne** :
- `.github/workflows/deploy-main.yml` (modification)

**Implementation** :

```yaml
- name: Smoke test post-deploy
  run: |
    curl --fail $URL/api/v1/health
    curl --fail -X POST $URL/api/v1/auth/login \
      -d '{"email":"smoke@test","password":"x"}' \
      -H 'Content-Type: application/json' \
      | grep -q 'INVALID_CREDENTIALS'

- name: Rollback on failure
  if: failure()
  run: |
    curl --fail -X POST ${{ secrets.RENDER_ROLLBACK_HOOK_URL }}
```

**Criteres d'acceptation** :
- [ ] Le smoke test verifie `/health` ET un endpoint metier
- [ ] En cas d'echec, un rollback est declenche automatiquement
- [ ] Une notification est envoyee en cas de rollback

---

## Phase 4 : Scalabilite

### [ ] ACTION 13 : Rate limiting par company

> **Priorite** : P2 - RECOMMANDE | **Effort** : 4h

**Risque** : Un client peut monopoliser l'API.

```php
// Dans AppServiceProvider::boot()
RateLimiter::for('api', function (Request $r) {
    $employee = $r->user();
    if ($employee && $employee->company_id) {
        return Limit::perMinute(300)->by('company:' . $employee->company_id);
    }
    return Limit::perMinute(60)->by($r->ip());
});
```

**Criteres d'acceptation** :
- [ ] Rate limit par company (300/min) pour les requetes authentifiees
- [ ] Rate limit par IP (60/min) pour les non-authentifiees
- [ ] Header `X-RateLimit-Remaining` retourne
- [ ] Healthcheck exclu du rate limiting

---

### [ ] ACTION 14 : Geler le mode schema Enterprise

> **Priorite** : P2 - RECOMMANDE | **Effort** : 2h

**Risque** : Complexite inutile maintenue, bugs potentiels dans le code schema.

**Implementation** : Ajouter un garde-fou dans `CompanyProvisioningService`.

```php
abort_if(
    ($payload['tenancy_type'] ?? 'shared') === 'schema',
    422,
    'Mode schema Enterprise gele. Contactez le support.'
);
```

**Criteres d'acceptation** :
- [ ] Impossible de creer une company en mode schema via l'API ou le web
- [ ] Le code existant de gestion schema reste en place
- [ ] Un test empeche la regression

---

### [ ] ACTION 15 : Preparer l'internationalisation

> **Priorite** : P2 - RECOMMANDE | **Effort** : 4h

**Risque** : Expansion vers d'autres marches bloquee par les strings en dur.

**Implementation** :
1. Extraire les strings Blade vers `lang/fr/*.php`
2. Extraire les messages d'erreur API vers les fichiers de traduction
3. Creer les squelettes `lang/en/` et `lang/ar/`

```php
// Avant (dans une vue Blade) :
<h1>Tableau de bord</h1>

// Apres :
<h1>{{ __('dashboard.title') }}</h1>

// Dans api/lang/fr/dashboard.php :
return [
    'title' => 'Tableau de bord',
    'employees' => 'Employes',
    'present' => 'Presents',
];
```

**Criteres d'acceptation** :
- [ ] Zero string en dur dans les vues Blade
- [ ] Les messages d'erreur API passent par `__()`
- [ ] `lang/fr/` est complet et fonctionnel
- [ ] `lang/en/` et `lang/ar/` existent (meme si vides)

---

## Calendrier propose

| Semaine | Actions | Effort | Livrable |
|---------|---------|--------|----------|
| S1 | Actions 1-4 (Securite) + Action 5 | 9h | Branch securite + PR documentation |
| S2 | Actions 6-8 (Qualite) | 12h | PR refactoring + PR tenant + PR dashboard |
| S3 | Actions 9-10 (Robustesse) | 9h | PR securite-2 + PR monitoring |
| S4 | Actions 11-12 (Robustesse) | 12h | PR refactoring-2 + PR ci-cd |
| S5 | Actions 13-15 (Scalabilite) | 10h | PR scalabilite |

**Regles** :
- Chaque phase est livree en 1+ PRs
- Phase N+1 ne commence qu'apres le merge de Phase N
- Les 98 tests existants ne doivent JAMAIS etre modifies pour passer
- Chaque action terminee est cochee `[x]` dans ce document
