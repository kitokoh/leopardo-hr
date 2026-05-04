<?php

namespace App\Services;

use App\Contracts\FeatureDetectorInterface;
use App\Models\Feature;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implémentation du détecteur de fonctionnalités API
 *
 * Utilise la reflection PHP et l'analyse des routes Laravel pour détecter
 * automatiquement les nouvelles fonctionnalités API et extraire leurs métadonnées.
 */
class FeatureDetector implements FeatureDetectorInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly ReflectionService $reflection,
        private readonly AnnotationReader $annotations
    ) {}

    /**
     * {@inheritdoc}
     */
    public function detectNewFeatures(): Collection
    {
        Log::info('Starting feature detection process');

        $routes = $this->scanRoutes();
        $newFeatures = collect();

        // Récupérer les fonctionnalités déjà enregistrées
        $existingFeatures = [];
        try {
            $existingFeatures = Feature::pluck('key')->toArray();
        } catch (\Exception $e) {
            Log::warning('Could not fetch existing features from database', ['error' => $e->getMessage()]);
            // Continuer sans les fonctionnalités existantes pour permettre le test
        }

        foreach ($routes as $routeData) {
            try {
                $featureKey = $this->generateFeatureKey($routeData);

                // Ignorer si la fonctionnalité existe déjà
                if (in_array($featureKey, $existingFeatures)) {
                    continue;
                }

                $metadata = $this->extractMetadata(
                    $routeData['controller_class'],
                    $routeData['method']
                );

                if ($this->isValidFeature($metadata)) {
                    $featureData = $this->buildFeatureData($routeData, $metadata);
                    $newFeatures->push($featureData);

                    Log::info('New feature detected', ['key' => $featureKey]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to process route for feature detection', [
                    'route' => $routeData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Feature detection completed', ['count' => $newFeatures->count()]);

        return $newFeatures;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractMetadata(string $controllerClass, string $method): array
    {
        try {
            // Analyser la méthode avec reflection
            $methodInfo = $this->reflection->analyzeMethod($controllerClass, $method);

            // Extraire les annotations
            $annotations = $this->annotations->extractMethodAnnotations($controllerClass, $method);

            // Combiner les informations
            return [
                'method_info' => $methodInfo,
                'annotations' => $annotations,
                'title' => $annotations['title'] ?? $this->annotations->generateTitleFromMethod($method),
                'description' => $annotations['description'] ?? $this->annotations->generateDescriptionFromMethod($method, $controllerClass),
                'permissions' => $annotations['permissions'] ?? $this->inferPermissions($controllerClass, $method),
                'mobile_compatible' => $annotations['mobile_compatible'] ?? true,
                'ui_type' => $annotations['ui_type'] ?? $this->inferUIType($method),
                'parameters' => $this->extractParameters($methodInfo),
                'response_schema' => $this->inferResponseSchema($controllerClass, $method),
                'form_schema' => $this->generateFormSchema($controllerClass, $method),
                'list_schema' => $this->generateListSchema($controllerClass, $method),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to extract metadata', [
                'controller' => $controllerClass,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scanRoutes(): Collection
    {
        $routes = collect();

        foreach ($this->router->getRoutes() as $route) {
            // Filtrer uniquement les routes API
            if (! $this->isApiRoute($route)) {
                continue;
            }

            $action = $route->getAction();

            // Ignorer les routes sans contrôleur
            if (! isset($action['controller'])) {
                continue;
            }

            // Parser l'action du contrôleur
            $controllerAction = $action['controller'];
            if (! str_contains($controllerAction, '@')) {
                // Format moderne Laravel avec invokable ou array
                if (is_string($controllerAction)) {
                    $controllerClass = $controllerAction;
                    $method = '__invoke';
                } else {
                    continue; // Ignorer les autres formats
                }
            } else {
                [$controllerClass, $method] = explode('@', $controllerAction);
            }

            // Vérifier que c'est un contrôleur API valide
            if (! $this->reflection->isApiController($controllerClass)) {
                continue;
            }

            $routes->push([
                'uri' => $route->uri(),
                'methods' => $route->methods(),
                'name' => $route->getName(),
                'controller_class' => $controllerClass,
                'method' => $method,
                'middleware' => $route->middleware(),
                'parameters' => $route->parameterNames(),
                'where' => $route->wheres,
            ]);
        }

        return $routes;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function detectChanges(): Collection
    {
        $changes = collect();

        try {
            /** @var Collection<int, Feature> $existingFeatures */
            $existingFeatures = Feature::all();

            foreach ($existingFeatures as $feature) {
                try {
                    // Retrouver la route correspondante
                    $currentRoute = $this->findRouteByEndpoint($feature->endpoint);

                    if (! $currentRoute) {
                        // La route n'existe plus
                        $changes->push([
                            'type' => 'removed',
                            'feature_key' => $feature->key,
                            'feature' => $feature,
                        ]);

                        continue;
                    }

                    // Extraire les métadonnées actuelles
                    $currentMetadata = $this->extractMetadata(
                        $currentRoute['controller_class'],
                        $currentRoute['method']
                    );

                    // Comparer avec les métadonnées enregistrées
                    /** @var array<string, mixed> $currentMetadata */
                    if ($this->hasMetadataChanged($feature, $currentMetadata)) {
                        $changes->push([
                            'type' => 'modified',
                            'feature_key' => $feature->key,
                            'feature' => $feature,
                            'current_metadata' => $currentMetadata,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to detect changes for feature', [
                        'feature_key' => $feature->key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fetch existing features for change detection', ['error' => $e->getMessage()]);
        }

        return $changes;
    }

    /**
     * Vérifie si une route est une route API
     *
     * @param  Route  $route
     */
    private function isApiRoute($route): bool
    {
        // Vérifier le préfixe de l'URI
        if (! str_starts_with($route->uri(), 'api/')) {
            return false;
        }

        // Vérifier les middlewares
        $middleware = $route->middleware();
        if (! in_array('api', $middleware) && ! in_array('throttle:api', $middleware)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $routeData
     */
    protected function generateFeatureKey(array $routeData): string
    {
        $controller = class_basename($routeData['controller_class']);
        $method = $routeData['method'];

        // Supprimer "Controller" du nom
        $controller = str_replace('Controller', '', $controller);

        return Str::snake($controller . '_' . $method);
    }

    /**
     * Vérifie si les métadonnées constituent une fonctionnalité valide
     */
    protected function isValidFeature(array $metadata): bool
    {
        // Vérifier que les métadonnées de base sont présentes
        if (empty($metadata['title']) || empty($metadata['method_info'])) {
            return false;
        }

        // Vérifier que c'est compatible mobile (si spécifié)
        if (isset($metadata['mobile_compatible']) && !$metadata['mobile_compatible']) {
            return false;
        }

        // Ignorer les méthodes de constructeur et magiques
        $methodName = $metadata['method_info']['name'] ?? '';
        if (str_starts_with($methodName, '__') && $methodName !== '__invoke') {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $routeData
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    protected function buildFeatureData(array $routeData, array $metadata): array
    {
        return [
            'key' => $this->generateFeatureKey($routeData),
            'title' => $metadata['title'],
            'description' => $metadata['description'],
            'endpoint' => '/' . ltrim((string) ($routeData['uri'] ?? ''), '/'),
            'http_methods' => array_filter((array) ($routeData['methods'] ?? []), fn ($method) => $method !== 'HEAD'),
            'parameters' => $metadata['parameters'],
            'response_schema' => $metadata['response_schema'],
            'permissions' => $metadata['permissions'],
            'mobile_version_min' => $metadata['mobile_version_min'] ?? '1.0.0',
            'mobile_version_max' => $metadata['mobile_version_max'] ?? null,
            'api_version' => $this->extractApiVersionFromUri((string) ($routeData['uri'] ?? '')),
            'status' => 'active',
            'metadata' => [
                'ui_type' => $metadata['ui_type'],
                'controller_class' => $routeData['controller_class'],
                'controller_method' => $routeData['method'],
                'route_name' => $routeData['name'],
                'middleware' => $routeData['middleware'],
                'form_schema' => $metadata['form_schema'] ?? null,
                'list_schema' => $metadata['list_schema'] ?? null,
                'mobile_compatible' => $metadata['mobile_compatible'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function inferPermissions(string $controllerClass, string $method): array
    {
        $resource = $this->extractResourceName($controllerClass);

        $permissionMap = [
            'index' => ["{$resource}.view"],
            'show' => ["{$resource}.view"],
            'store' => ["{$resource}.create"],
            'update' => ["{$resource}.update"],
            'destroy' => ["{$resource}.delete"],
        ];

        return $permissionMap[$method] ?? ["{$resource}.manage"];
    }

    /**
     * Infère le type d'interface utilisateur basé sur la méthode
     */
    private function inferUIType(string $method): string
    {
        $uiTypeMap = [
            'index' => 'list',
            'show' => 'detail',
            'store' => 'form',
            'update' => 'form',
            'create' => 'form',
            'edit' => 'form',
        ];

        return $uiTypeMap[$method] ?? 'generic';
    }

    /**
     * @param array<string, mixed> $methodInfo
     * @return array<string, array<string, mixed>>
     */
    private function extractParameters(array $methodInfo): array
    {
        $parameters = [];

        /** @var array<int, array<string, mixed>> $methodParams */
        $methodParams = $methodInfo['parameters'] ?? [];

        foreach ($methodParams as $param) {
            $paramType = (string) ($param['type'] ?? '');
            $paramName = (string) ($param['name'] ?? '');

            // Ignorer les paramètres de type Request et Model
            if (in_array($paramType, ['Illuminate\\Http\\Request', 'string', 'int'])) {
                continue;
            }

            $parameters[$paramName] = [
                'type' => $this->mapPhpTypeToApiType($paramType),
                'required' => ! ($param['is_optional'] ?? false),
                'description' => "Paramètre {$paramName}",
            ];
        }

        return $parameters;
    }

    /**
     * @return array<string, mixed>
     */
    private function inferResponseSchema(string $controllerClass, string $method): array
    {
        $resource = $this->extractResourceName($controllerClass);

        // Schémas de base selon le type de méthode
        $schemas = [
            'index' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                'meta' => ['type' => 'object'],
            ],
            'show' => [
                'data' => ['type' => 'object'],
            ],
            'store' => [
                'data' => ['type' => 'object'],
                'message' => ['type' => 'string'],
            ],
            'update' => [
                'data' => ['type' => 'object'],
                'message' => ['type' => 'string'],
            ],
        ];

        return $schemas[$method] ?? ['data' => ['type' => 'object']];
    }

    /**
     * Mappe un type PHP vers un type API
     */
    private function mapPhpTypeToApiType(?string $phpType): string
    {
        $typeMap = [
            'string' => 'string',
            'int' => 'integer',
            'integer' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'array' => 'array',
        ];

        return $typeMap[$phpType] ?? 'string';
    }

    /**
     * Extrait le nom de la ressource depuis le contrôleur
     */
    private function extractResourceName(string $controllerClass): string
    {
        $shortName = class_basename($controllerClass);
        $resource = str_replace('Controller', '', $shortName);

        return Str::snake(Str::plural($resource));
    }

    /**
     * Extrait la version de l'API depuis l'URI
     */
    private function extractApiVersionFromUri(string $uri): string
    {
        // Extraire la version depuis l'URI (ex: api/v1/users -> v1)
        if (preg_match('/api\/(v\d+)\//', $uri, $matches)) {
            return $matches[1];
        }

        return 'v1'; // Version par défaut
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRouteByEndpoint(string $endpoint): ?array
    {
        $routes = $this->scanRoutes();

        return (array) $routes->first(function ($route) use ($endpoint) {
            return '/' . ltrim((string) ($route['uri'] ?? ''), '/') === $endpoint;
        });
    }

    /**
     * @param array<string, mixed> $currentMetadata
     */
    private function hasMetadataChanged(Feature $feature, array $currentMetadata): bool
    {
        // Comparer les champs critiques
        $criticalFields = ['title', 'description', 'permissions'];

        foreach ($criticalFields as $field) {
            $currentValue = $currentMetadata[$field] ?? null;
            $storedValue = $feature->$field ?? null;

            if ($currentValue !== $storedValue) {
                return true;
            }
        }

        // Comparer la signature de la méthode
        $currentSignature = (string) ($currentMetadata['method_info']['signature'] ?? '');
        $storedSignature = (string) (($feature->metadata['method_signature'] ?? ''));

        return $currentSignature !== $storedSignature;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFormSchema(string $controllerClass, string $method): array
    {
        // Schéma de base pour les formulaires
        $baseSchema = [
            'fields' => [],
        ];

        // Ajouter des champs selon le type de méthode
        if (in_array($method, ['store', 'update', 'create', 'edit'])) {
            $resource = $this->extractResourceName($controllerClass);

            // Champs de base génériques
            $baseSchema['fields'] = [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Nom',
                    'required' => true,
                    'validation' => [
                        'min_length' => 2,
                        'max_length' => 100,
                    ],
                ],
                [
                    'name' => 'description',
                    'type' => 'textarea',
                    'label' => 'Description',
                    'required' => false,
                    'validation' => [
                        'max_length' => 500,
                    ],
                ],
            ];
        }

        return $baseSchema;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateListSchema(string $controllerClass, string $method): array
    {
        // Schéma de base pour les listes
        $baseSchema = [
            'columns' => [],
            'actions' => [],
        ];

        // Ajouter des colonnes selon le type de méthode
        if (in_array($method, ['index', 'show'])) {
            $baseSchema['columns'] = [
                [
                    'field' => 'name',
                    'label' => 'Nom',
                    'sortable' => true,
                ],
                [
                    'field' => 'status',
                    'label' => 'Statut',
                    'sortable' => true,
                ],
                [
                    'field' => 'created_at',
                    'label' => 'Créé le',
                    'sortable' => true,
                    'type' => 'date',
                ],
            ];

            $baseSchema['actions'] = [
                ['type' => 'view', 'label' => 'Voir'],
                ['type' => 'edit', 'label' => 'Modifier'],
                ['type' => 'delete', 'label' => 'Supprimer'],
            ];
        }

        return $baseSchema;
    }
}
