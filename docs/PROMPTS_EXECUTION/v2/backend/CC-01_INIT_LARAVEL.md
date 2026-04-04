# CC-01 — Initialisation Laravel 11 + Infrastructure complète
# Agent : Claude Code
# Durée : 4-6 heures
# Prérequis : CC-00 vert (infrastructure o2switch validée)

---

## PRÉREQUIS VÉRIFIABLES (lire avant de commencer)

```bash
# Ces commandes doivent toutes répondre sans erreur
curl https://api.leopardo-rh.com/api/v1/health  # CC-00 doit retourner {"status":"ok"}
php8.3 -v                                         # PHP 8.3.x
redis-cli ping                                    # PONG
psql -U leopardo_user -c "SELECT version();"      # PostgreSQL 16.x
```

Si l'une échoue → retourner à CC-00.

---

## DOCUMENTS À LIRE EN PREMIER

| Document | Section critique |
|---|---|
| `docs/dossierdeConception/08_multitenancy/08_MULTITENANCY_STRATEGY.md` | Architecture shared vs schema — obligatoire |
| `api/.env.example` | Toutes les variables d'environnement |
| `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` | Tables schéma public uniquement pour cette étape |

---

## ÉTAPE 1 — Créer le projet Laravel

```bash
cd /var/www
composer create-project laravel/laravel leopardo-rh-api "11.*"
cd leopardo-rh-api
```

---

## ÉTAPE 2 — Installer les packages (dans cet ordre exact)

```bash
# Auth + API
composer require laravel/sanctum

# Queue + Dashboard
composer require laravel/horizon

# PDF
composer require barryvdh/laravel-dompdf

# Push notifications
composer require kreait/laravel-firebase

# Vue.js + Inertia
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3 vue@3

# Dev uniquement
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
composer require laravel/telescope --dev
php artisan pest:install
php artisan telescope:install
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## ÉTAPE 3 — Copier et configurer .env

```bash
cp docs/PROMPTS_EXECUTION/v2/backend/CC-01_INIT_LARAVEL.md /dev/null  # référence seulement
cp api/.env.example .env
php artisan key:generate
```

Remplir TOUTES les variables dans `.env` selon `api/.env.example`.

---

## ÉTAPE 4 — Configurer PostgreSQL multi-schéma

```php
// config/database.php — connexion pgsql existante, ajouter options :
'pgsql' => [
    'driver'         => 'pgsql',
    'host'           => env('DB_HOST', '127.0.0.1'),
    'port'           => env('DB_PORT', '5432'),
    'database'       => env('DB_DATABASE', 'leopardo_db'),
    'username'       => env('DB_USERNAME', 'leopardo_user'),
    'password'       => env('DB_PASSWORD', ''),
    'charset'        => 'utf8',
    'prefix'         => '',
    'prefix_indexes' => true,
    'search_path'    => 'public',      // ← défaut schéma public
    'sslmode'        => 'prefer',
    'options'        => [
        \PDO::ATTR_PERSISTENT => false, // Important : pas de connexion persistante (le search_path ne se réinitialise pas)
    ],
],
```

---

## ÉTAPE 5 — Créer TenantMiddleware

```php
// app/Http/Middleware/TenantMiddleware.php
<?php

namespace App\Http\Middleware;

use App\Models\Public\UserLookup;
use App\Models\Public\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'UNAUTHENTICATED'], 401);
        }

        // Récupérer la company depuis le token Sanctum
        // Le token a été créé avec tokenable = Employee dans le bon schéma
        // On passe par user_lookups (schéma public) pour trouver le schéma
        $lookup = UserLookup::where('email', $user->email)->first();

        if (!$lookup) {
            return response()->json(['error' => 'TENANT_NOT_FOUND'], 404);
        }

        $company = Company::find($lookup->company_id);

        if (!$company || $company->status === 'suspended') {
            return response()->json(['error' => 'COMPANY_SUSPENDED'], 403);
        }

        if ($company->subscription_status === 'expired') {
            return response()->json(['error' => 'SUBSCRIPTION_EXPIRED'], 403);
        }

        // Switch du schéma PostgreSQL
        if ($company->tenancy_type === 'schema') {
            $schemaName = 'company_' . str_replace('-', '_', $company->uuid);
            DB::statement("SET search_path TO {$schemaName}, public");
        } else {
            // shared_tenants pour les plans Starter
            DB::statement("SET search_path TO shared_tenants, public");
            // Le Global Scope s'applique automatiquement via HasCompany trait
        }

        // Injecter la company dans le request pour les controllers
        $request->merge(['_company' => $company]);
        app()->instance('current_company', $company);

        return $next($request);

        // IMPORTANT : après la réponse, réinitialiser le search_path
        // (déjà géré par PDO::ATTR_PERSISTENT = false — nouvelle connexion par request)
    }
}
```

---

## ÉTAPE 6 — Migrations schéma PUBLIC (dans cet ordre)

Créer les fichiers dans `database/migrations/public/` :

```bash
php artisan make:migration create_plans_table --path=database/migrations/public
php artisan make:migration create_languages_table --path=database/migrations/public
php artisan make:migration create_companies_table --path=database/migrations/public
php artisan make:migration create_super_admins_table --path=database/migrations/public
php artisan make:migration create_user_lookups_table --path=database/migrations/public
php artisan make:migration create_invoices_table --path=database/migrations/public
```

Contenu obligatoire de chaque migration : voir `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` — les tables du schéma public.

**Table user_lookups (critique pour le login multi-tenant) :**
```php
Schema::create('user_lookups', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->unsignedBigInteger('company_id');
    $table->string('schema_name');      // ex: company_abc123
    $table->string('role');             // employee | super_admin
    $table->timestamps();

    $table->foreign('company_id')->references('id')->on('companies');
    $table->index(['email', 'company_id']); // index composé obligatoire
});
```

---

## ÉTAPE 7 — Seeders schéma public

```bash
php artisan make:seeder PlanSeeder
php artisan make:seeder LanguageSeeder
php artisan make:seeder HRModelSeeder
```

Contenu : voir `docs/dossierdeConception/04_architecture_erd/03_ERD_COMPLET.md` section Seeders.

Plans à créer : Trial (gratuit 14j), Starter (max 20 employés), Business (max 100), Enterprise (illimité).
Langues : fr, ar, tr, en.
Pays RH : DZ, MA, TN, TR, FR, SN, CI.

---

## ÉTAPE 8 — Routes de base

```php
// routes/api.php
Route::prefix('v1')->group(function () {

    // Santé (public, sans auth)
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

    // Auth public (sans auth)
    Route::prefix('auth')->group(base_path('routes/auth.php'));
    Route::post('/public/register', [\App\Http\Controllers\Auth\RegisterController::class, 'store']);

    // Admin (Super Admin uniquement)
    Route::prefix('admin')
        ->middleware(['auth:sanctum', 'super.admin'])
        ->group(base_path('routes/admin.php'));

    // Tenant (employés + managers)
    Route::middleware(['auth:sanctum', 'tenant'])
        ->group(base_path('routes/tenant.php'));
});
```

Créer les fichiers `routes/auth.php`, `routes/admin.php`, `routes/tenant.php` (vides pour l'instant).

---

## ÉTAPE 9 — Configurer i18n (4 langues)

```bash
mkdir -p resources/lang/{fr,ar,tr,en}
touch resources/lang/{fr,ar,tr,en}/messages.php
touch resources/lang/{fr,ar,tr,en}/validation.php
```

```php
// resources/lang/fr/messages.php
return [
    'employee_created'           => 'Employé créé avec succès.',
    'employee_updated'           => 'Employé mis à jour.',
    'employee_deleted'           => 'Employé archivé.',
    'insufficient_leave_balance' => 'Solde congés insuffisant. Disponible : :available jour(s), demandé : :requested.',
    'overlap_with_existing'      => 'Cette demande chevauche une absence existante.',
    'already_checked_in'         => 'Vous avez déjà pointé l\'arrivée aujourd\'hui.',
    'missing_check_in'           => 'Aucun pointage d\'arrivée trouvé pour aujourd\'hui.',
    'checkout_before_checkin'    => 'L\'heure de départ ne peut pas être antérieure à l\'arrivée.',
    'gps_outside_zone'           => 'Position GPS hors de la zone autorisée.',
    'advance_already_active'     => 'Une avance est déjà en cours de remboursement.',
    'subscription_expired'       => 'L\'abonnement a expiré. Veuillez renouveler.',
    'company_suspended'          => 'Ce compte entreprise est suspendu.',
];
```

Créer les équivalents en `ar/`, `tr/`, `en/`.

---

## ÉTAPE 10 — Middleware SetLocale

```php
// app/Http/Middleware/SetLocale.php
public function handle(Request $request, Closure $next)
{
    // Priorité : header Accept-Language > company.language > 'fr'
    $locale = $request->header('Accept-Language', 'fr');
    $company = app('current_company');
    if ($company) {
        $locale = $company->language ?? $locale;
    }
    app()->setLocale(substr($locale, 0, 2));
    return $next($request);
}
```

---

## TESTS À ÉCRIRE (avant de commencer le code)

```php
// tests/Feature/Infrastructure/HealthCheckTest.php
it('health endpoint returns ok', function () {
    $response = $this->getJson('/api/v1/health');
    $response->assertStatus(200)->assertJson(['status' => 'ok']);
});

// tests/Feature/Infrastructure/PostgreSQLMultiSchemaTest.php
it('can switch postgresql schema', function () {
    DB::statement("CREATE SCHEMA IF NOT EXISTS test_schema_cc01");
    DB::statement("SET search_path TO test_schema_cc01, public");
    DB::statement("CREATE TABLE IF NOT EXISTS test_table (id SERIAL, name TEXT)");
    DB::statement("INSERT INTO test_table (name) VALUES ('test')");

    $result = DB::selectOne("SELECT name FROM test_table WHERE id = 1");
    expect($result->name)->toBe('test');

    DB::statement("SET search_path TO public");
    DB::statement("DROP SCHEMA test_schema_cc01 CASCADE");
});

it('schema isolation works — table from schema A invisible in schema B', function () {
    DB::statement("CREATE SCHEMA IF NOT EXISTS schema_a");
    DB::statement("CREATE SCHEMA IF NOT EXISTS schema_b");

    DB::statement("SET search_path TO schema_a, public");
    DB::statement("CREATE TABLE schema_a.secret_data (id SERIAL, value TEXT)");
    DB::statement("INSERT INTO schema_a.secret_data (value) VALUES ('secret_a')");

    DB::statement("SET search_path TO schema_b, public");

    // Dans le contexte de schema_b, la table secret_data de schema_a est invisible
    expect(fn() => DB::select("SELECT * FROM secret_data"))
        ->toThrow(\Illuminate\Database\QueryException::class);

    // Nettoyage
    DB::statement("SET search_path TO public");
    DB::statement("DROP SCHEMA schema_a CASCADE");
    DB::statement("DROP SCHEMA schema_b CASCADE");
});

// tests/Feature/Infrastructure/RedisTest.php
it('redis cache works', function () {
    Cache::put('cc01_test', 'value_ok', 60);
    expect(Cache::get('cc01_test'))->toBe('value_ok');
    Cache::forget('cc01_test');
});
```

---

## CRITÈRES DE VALIDATION — PORTE VERTE VERS CC-02

```
[ ] php artisan test → tous les tests passent (0 failure)
[ ] GET /api/v1/health → {"status":"ok"}
[ ] php artisan migrate → 0 erreur
[ ] php artisan db:seed → plans, langues, pays insérés
[ ] Test PostgreSQL multi-schéma → vert
[ ] Test isolation schémas → vert
[ ] Test Redis → vert
[ ] php artisan horizon:status → Horizon en attente de workers
```

---

## COMMIT

```
feat: initialize Laravel 11 with multi-tenant PostgreSQL, Redis, Horizon, Sanctum
test: add infrastructure tests — schema isolation, Redis, health check
```
