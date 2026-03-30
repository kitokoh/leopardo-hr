# PROMPT CC-01 — Initialisation Laravel 11
# Agent : CLAUDE CODE
# Phase : Sprint 0 — Semaine 1
# Durée estimée : 4-6 heures

---

## CONTEXTE

Tu es l'architecte backend de **Leopardo RH**, une plateforme SaaS RH multi-entreprises.
Tu initialises le projet backend depuis zéro selon les spécifications validées.

---

## DOCUMENTS DE RÉFÉRENCE (lire avant de commencer)

- `01_PROMPT_MASTER_CLAUDE_CODE.md` → règles de code absolues
- `07_SCHEMA_SQL_COMPLET.sql` → schéma PostgreSQL à exécuter
- `05_SEEDERS_ET_DONNEES_INITIALES.md` → données à insérer
- `11_MULTITENANCY_STRATEGY.md` → architecture multi-tenant
- `.env.example` (dans 04_CICD_ET_CONFIG/) → variables à configurer

---

## STACK OBLIGATOIRE (non négociable)

```
PHP           : 8.3
Framework     : Laravel 11
Base données  : PostgreSQL 16 (multi-schéma)
Cache/Queue   : Redis 7 + Horizon
Auth          : Laravel Sanctum
PDF           : barryvdh/laravel-dompdf
Push notifs   : kreait/laravel-firebase
Tests         : Pest PHP
Hébergement   : o2switch (Nginx + PHP-FPM + Supervisor)
```

---

## TÂCHES À EXÉCUTER DANS L'ORDRE

### Étape 1 — Créer le projet Laravel
```bash
cd /var/www
composer create-project laravel/laravel leopardo-rh-api
cd leopardo-rh-api
```

### Étape 2 — Installer tous les packages
```bash
composer require laravel/sanctum
composer require barryvdh/laravel-dompdf
composer require kreait/laravel-firebase
composer require laravel/horizon
composer require laravel/telescope --dev
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
composer require fakerphp/faker --dev
```

### Étape 3 — Configurer PostgreSQL
Dans `config/database.php`, vérifier que la connexion `pgsql` est correcte.
Copier `.env.example` → `.env` et remplir les variables DB.

### Étape 4 — Créer le TenantMiddleware
Créer `app/Http/Middleware/TenantMiddleware.php` selon `11_MULTITENANCY_STRATEGY.md`.
Le middleware doit :
1. Lire le token Bearer
2. Chercher l'utilisateur dans `user_lookups` (schéma public)
3. Charger la `Company`
4. Si `tenancy_type = 'schema'` → `SET search_path TO company_{uuid}, public`
5. Si `tenancy_type = 'shared'` → `SET search_path TO shared_tenants, public` + Global Scope

### Étape 5 — Exécuter le schéma SQL
```bash
psql -U leopardo_user -d leopardo_db -f 07_SCHEMA_SQL_COMPLET.sql
```
Puis créer les migrations Laravel correspondantes (pour les futures migrations propres).

### Étape 6 — Créer et exécuter les seeders
Selon `05_SEEDERS_ET_DONNEES_INITIALES.md` :
- `PlanSeeder` (Starter, Business, Enterprise)
- `LanguageSeeder` (fr, ar, tr, en, es)
- `HRModelSeeder` (algeria, morocco, tunisia, turkey, france)

### Étape 7 — Configurer les routes API
Créer `routes/api.php` avec la structure :
```php
Route::prefix('v1')->group(function () {
    // Auth (public)
    Route::prefix('auth')->group('auth.php');
    // Admin (super admin uniquement)
    Route::prefix('admin')->middleware(['auth:sanctum', 'super.admin'])->group('admin.php');
    // Tenant (gestionnaire + employé)
    Route::middleware(['auth:sanctum', 'tenant'])->group('tenant.php');
});
```

### Étape 8 — Configurer Horizon
```bash
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"
```

---

## RÈGLES ABSOLUES

1. **JAMAIS** de `WHERE company_id` dans les models tenant — le schéma switché suffit
2. **TOUJOURS** `now()` côté serveur pour les timestamps — jamais le timestamp client
3. **TOUJOURS** écrire le test Pest AVANT d'écrire le Controller
4. Chaque PR doit passer les tests CI avant merge

---

## COMMIT ATTENDU

```
feat: initialize Laravel 11 with PostgreSQL multi-tenant, Sanctum, Horizon
```

---

## RÉSULTAT ATTENDU

- [ ] `php artisan serve` démarre sans erreur
- [ ] `php artisan test` passe 0 tests (normal — structure seule)
- [ ] `php artisan migrate` exécute sans erreur
- [ ] `php artisan db:seed` insère les plans, langues, modèles RH
- [ ] `GET /api/v1/health` retourne `{ "status": "ok" }`
