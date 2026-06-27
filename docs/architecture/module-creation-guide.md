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
Dans `api/app/Providers/AppServiceProvider.php`, ajouter :
```php
$this->app->register(\App\Modules\MonNouveauModule\Providers\MonNouveauModuleServiceProvider::class);
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
│   └── DTOs/            ← Data Transfer Objects
├── Domain/
│   ├── Models/          ← Eloquent models
│   ├── Exceptions/      ← Domain exceptions
│   └── Events/          ← Domain events
├── Infrastructure/
│   ├── Services/        ← Service layer
│   └── Repositories/    ← Data access
├── Interfaces/
│   └── Api/V1/
│       ├── Controllers/ ← HTTP controllers
│       ├── Requests/    ← Form requests
│       └── Resources/   ← API resources
└── Providers/
    └── MonNouveauModuleServiceProvider.php
```
