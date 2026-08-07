# Tenant

Socle transversal multi-tenant (`App\Core\Tenant`). Migration terminée — voir
`api/ARCHITECTURE.md` section "Nettoyage complet" pour l'historique.

## Contenu

- **`TenantManager.php`** — Gestionnaire du contexte multi-tenant : active/désactive
  le tenant courant, manipule le `search_path` PostgreSQL pour l'isolation des
  données, expose `withinTenant()` pour les jobs/commands à contexte ponctuel.
  Enregistré en singleton dans `AppServiceProvider::register()`.
  Les shims legacy `App\Services\TenantManager` ont été supprimés (issue #1494).
  voir ce fichier) ; tout nouveau code doit référencer `App\Core\Tenant\TenantManager`
  directement.
- **`Domain/Models/`** — Modèles du domaine tenant : `Company`, `CompanyRequest`,
  `CompanySetting`, `Site`, `SuperAdmin`.
- **`Infrastructure/Services/TenantCacheService.php`** — Cache scoping tenant.

## Middleware associé

`App\Http\Middleware\TenantMiddleware` (dans `app/Http/Middleware/`, pas dans ce
module) résout la company courante à partir de la requête et l'injecte dans
`TenantManager`.

## Utilisation

```php
$manager = app(\App\Core\Tenant\TenantManager::class);
$manager->setTenant($company);

// Jobs/commands à contexte ponctuel :
$manager->withinTenant($company, fn () => /* traitement */);

// Accès global en lecture (helpers.php) :
currentCompany(); // -> app('current_company')
```
