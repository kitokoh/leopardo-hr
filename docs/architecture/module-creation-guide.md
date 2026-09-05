# Créer un nouveau module en 5 minutes

## Prérequis
- Branche créée depuis `feat/clean-architecture` ou `main`
- `api/stubs/module-template/` disponible

## Étapes

### 1. Copier le template
```bash
cp -r api/stubs/module-template api/app/Modules/MonNouveauModule
```

### 2. Créer le fichier de routes
```bash
cat > api/routes/modules/mon_nouveau_module.php << 'EOF'
<?php

use Illuminate\Support\Facades\Route;
use App\Modules\MonNouveauModule\Interfaces\Api\V1\MonController;

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::apiResource('mon-resource', MonController::class);
});
EOF
```

### 3. Créer le ServiceProvider
```php
// api/app/Modules/MonNouveauModule/Providers/MonNouveauModuleServiceProvider.php
<?php

namespace App\Modules\MonNouveauModule\Providers;

use Illuminate\Support\ServiceProvider;

class MonNouveauModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Modules\MonNouveauModule\Domain\Contracts\MonRepositoryInterface::class,
            \App\Modules\MonNouveauModule\Infrastructure\Repositories\MonRepository::class,
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/modules/mon_nouveau_module.php'));
    }
}
```

### 4. Enregistrer le ServiceProvider
Le mécanisme réel (issue #6586) est le registre `api/bootstrap/providers.php`
(Laravel 12 auto-discovers les providers listés dans le tableau retourné) —
ajouter la classe dans le tableau :
```php
return [
    // ... providers existants ...
    \App\Modules\MonNouveauModule\Providers\MonNouveauModuleServiceProvider::class,
];
```

### 5. Créer le controller
```php
// api/app/Modules/MonNouveauModule/Interfaces/Api/V1/MonController.php
<?php

namespace App\Modules\MonNouveauModule\Interfaces\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MonController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}
```

### Résultat
Nouveau module opérationnel, isolé, testable, sans impact sur les autres modules.

## Structure finale attendue
```
api/app/Modules/MonNouveauModule/
├── Application/
│   ├── Actions/         ← Use cases
│   ├── DTOs/            ← Data Transfer Objects
│   ├── Listeners/       ← Event listeners
│   └── Queries/         ← Queries CQRS
├── Domain/
│   ├── Models/          ← Eloquent models
│   ├── Contracts/       ← Domain contracts (interfaces)
│   ├── Enums/           ← Enums métier
│   ├── Exceptions/      ← Domain exceptions
│   ├── Events/          ← Domain events
│   └── ValueObjects/    ← Value objects
├── Infrastructure/
│   ├── Services/        ← Service layer
│   ├── Exports/         ← Exports (PDF, CSV…)
│   └── Repositories/    ← Data access
├── Interfaces/
│   └── Api/V1/
│       ├── Controllers/ ← HTTP controllers
│       └── Requests/    ← Form requests
├── Providers/
│   └── MonNouveauModuleServiceProvider.php
└── Console/              ← Commandes artisan du module (optionnel)
```
Les routes du module vivent dans `api/routes/modules/[nom_module].php` et sont
chargées via `loadRoutesFrom()` dans le ServiceProvider (étape 3).

> **API Resources : namespace centralise (derogation documentee PA2-ARCH-010)**
> Les classes `JsonResource` de ton nouveau module vont dans `app/Http/Resources/Api/V1/`
> (meme dossier que tous les autres modules), **pas** dans `Interfaces/Api/V1/Resources/`.
> Raison : plusieurs Resources sont partagees entre modules, et un module ne doit jamais
> importer directement une classe d'un autre module — les centraliser evite ce couplage.
> Voir `api/ARCHITECTURE.md` (section "Modules existants") pour le detail de la derogation.
> Exception : une Resource strictement interne a ce module et jamais consommee ailleurs
> peut rester dans `Interfaces/Api/V1/Resources/` si tu preferes.
