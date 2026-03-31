# Spec — SuperAdminMiddleware (Double Provider Sanctum)

## 1. Problème

L'architecture multi-tenant de Leopardo RH sépare les données en deux espaces :
- **Schéma public** : tables `super_admins`, `companies`, `plans`, `invoices` — communes à toute la plateforme.
- **Schémas tenant** : tables `employees`, `attendance_logs`, etc. — isolés par entreprise.

Les SuperAdmins sont stockés dans le schéma public, tandis que les employés sont dans leur schéma tenant respectif. Laravel Sanctum, par défaut, utilise un modèle `personal_access_tokens` unique. Cette configuration ne permet pas d'authentifier simultanément des utilisateurs issus de deux schémas PostgreSQL distincts avec une seule table de tokens.

## 2. Solution — Double Provider Sanctum

Deux tables de tokens sont utilisées :

| Provider | Table de tokens | Schéma | Modèle associé |
|----------|----------------|--------|----------------|
| `employee` | `personal_access_tokens` | Schéma tenant | `App\Models\Employee` |
| `super_admin` | `super_admin_tokens` | Schéma public | `App\Models\SuperAdmin` |

La table `super_admin_tokens` dans le schéma public :
```sql
CREATE TABLE super_admin_tokens (
    id          BIGSERIAL   PRIMARY KEY,
    super_admin_id INT       NOT NULL REFERENCES super_admins(id) ON DELETE CASCADE,
    name        VARCHAR(100) NOT NULL,
    token       VARCHAR(128) NOT NULL UNIQUE,
    abilities   TEXT        DEFAULT '*',
    last_used   TIMESTAMPTZ NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at  TIMESTAMPTZ NULL
);
CREATE INDEX idx_sat_admin ON super_admin_tokens(super_admin_id);
```

## 3. Flux du middleware

1. **Extraction du token** : lire le header `Authorization: Bearer <token>`.
2. **Tentative employé** : rechercher le token dans la table `personal_access_tokens` du schéma tenant courant. Si trouvé, authentifier l'employé.
3. **Tentative SuperAdmin** : si le token n'est pas trouvé en tant qu'employé, chercher dans `public.super_admin_tokens`. Si trouvé, authentifier le SuperAdmin.
4. **Échec** : si aucun provider ne correspond, retourner `401 Unauthorized`.

## 4. Routes protégées

Toutes les routes sous le préfixe `/admin/*` sont protégées par ce middleware et réservées exclusivement aux SuperAdmins :

```
/admin/companies/*          — Gestion des entreprises
/admin/plans/*              — Gestion des plans tarifaires
/admin/invoices/*           — Gestion des factures
/admin/stats/*              — Statistiques plateforme
/admin/companies/{id}/migrate-to-dedicated — Migration tenant
```

## 5. Exemple de middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\DB;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token manquant.'], 401);
        }

        $hash = hash('sha256', $token);

        // 1. Chercher dans les tokens SuperAdmin (schéma public)
        $adminToken = DB::table('public.super_admin_tokens')
            ->where('token', $hash)
            ->whereNull('expires_at')
            ->first();

        if ($adminToken) {
            $admin = SuperAdmin::find($adminToken->super_admin_id);

            if (!$admin) {
                return response()->json(['message' => 'SuperAdmin introuvable.'], 401);
            }

            // Mettre à jour last_used
            DB::table('public.super_admin_tokens')
                ->where('id', $adminToken->id)
                ->update(['last_used' => now()]);

            $request->setAttribute('super_admin', $admin);
            $request->attributes->set('auth_provider', 'super_admin');

            return $next($request);
        }

        return response()->json(['message' => 'Non autorisé.'], 403);
    }
}
```

## 6. Configuration des routes

```php
// routes/admin.php
Route::middleware(['auth:super_admin', SuperAdminMiddleware::class])
    ->prefix('admin')
    ->group(function () {
        Route::apiResource('companies', AdminCompanyController::class);
        Route::post('companies/{id}/migrate-to-dedicated', [TenantMigrationController::class, 'migrate']);
        // ...
    });
```

Le guard `super_admin` est configuré dans `config/auth.php` pour utiliser le provider `super_admins` avec le driver `database` pointant sur la table `super_admin_tokens`.
