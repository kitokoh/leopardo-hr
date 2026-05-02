# Feature Registry - Documentation

## Vue d'ensemble

Le **Feature Registry** est un système centralisé qui maintient un inventaire complet de toutes les fonctionnalités API disponibles dans l'application Leopardo RH. Il fournit des capacités de cache intelligent, de versioning et de synchronisation automatique.

## Architecture

### Composants principaux

1. **FeatureRegistryInterface** - Interface définissant le contrat du registre
2. **FeatureRegistry** - Implémentation principale avec cache et versioning
3. **FeatureDetector** - Service de détection automatique des fonctionnalités
4. **Feature Model** - Modèle Eloquent pour les fonctionnalités
5. **FeatureRegistryCommand** - Commandes Artisan pour la gestion

### Flux de données

```
API Routes → FeatureDetector → FeatureRegistry → Cache → Database
                                      ↓
Mobile App ← Manifest Generator ← FeatureRegistry
```

## Utilisation

### Injection de dépendance

```php
use App\Contracts\FeatureRegistryInterface;

class MyController extends Controller
{
    public function __construct(
        private FeatureRegistryInterface $registry
    ) {}

    public function index()
    {
        $features = $this->registry->getFeatures();
        return response()->json($features);
    }
}
```

### Enregistrement d'une fonctionnalité

```php
$feature = new Feature([
    'key' => 'employee_management',
    'title' => 'Gestion des Employés',
    'description' => 'Créer, modifier et gérer les employés',
    'endpoint' => '/api/v1/employees',
    'http_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'parameters' => [
        'list' => [
            'page' => ['type' => 'integer', 'required' => false],
            'search' => ['type' => 'string', 'required' => false]
        ]
    ],
    'permissions' => ['employees.view', 'employees.create'],
    'mobile_version_min' => '1.0.0',
    'api_version' => 'v1',
    'status' => 'active'
]);

$registry->registerFeature($feature);
```

### Récupération des fonctionnalités

```php
// Toutes les fonctionnalités
$allFeatures = $registry->getFeatures();

// Par version API
$v1Features = $registry->getFeatures('v1');

// Compatibles avec une version mobile
$compatibleFeatures = $registry->getCompatibleFeatures('1.2.0');

// Une fonctionnalité spécifique
$feature = $registry->getFeature('employee_management');
```

### Génération du manifeste

```php
// Manifeste pour une version mobile spécifique
$manifest = $registry->getManifest('1.2.0');

// Structure du manifeste
[
    'version' => 'v1',
    'generated_at' => '2024-01-15T10:30:00Z',
    'mobile_version_min' => '1.0.0',
    'mobile_version_target' => '1.2.0',
    'total_features' => 15,
    'features' => [
        [
            'key' => 'employee_management',
            'title' => 'Gestion des Employés',
            'endpoint' => '/api/v1/employees',
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'permissions' => ['employees.view', 'employees.create'],
            'ui_type' => 'list',
            // ... autres métadonnées
        ]
    ]
]
```

### Synchronisation automatique

```php
// Synchronisation manuelle
$result = $registry->synchronize();

// Résultat de la synchronisation
[
    'new' => 3,        // Nouvelles fonctionnalités détectées
    'updated' => 1,    // Fonctionnalités mises à jour
    'removed' => 0,    // Fonctionnalités supprimées
    'errors' => []     // Erreurs rencontrées
]
```

## Commandes Artisan

### Synchronisation

```bash
# Synchroniser le registre avec les fonctionnalités détectées
php artisan features:registry sync
```

### Affichage des fonctionnalités

```bash
# Lister toutes les fonctionnalités
php artisan features:registry list

# Lister par version API
php artisan features:registry list --version=v1

# Lister les compatibles avec une version mobile
php artisan features:registry list --mobile-version=1.2.0

# Format JSON
php artisan features:registry list --format=json
```

### Statistiques

```bash
# Afficher les statistiques du registre
php artisan features:registry stats

# Format JSON
php artisan features:registry stats --format=json
```

### Gestion du cache

```bash
# Vider le cache du registre
php artisan features:registry clear-cache
```

## Système de cache

### Configuration

Le registre utilise le système de cache Laravel avec les paramètres suivants :

- **TTL par défaut** : 3600 secondes (1 heure)
- **Préfixe des clés** : `feature_registry:`
- **Support des tags** : Oui (si le driver le supporte)

### Clés de cache

- `feature_registry:manifest:{version}` - Manifeste pour une version mobile
- `feature_registry:features:{version}` - Liste des fonctionnalités par version
- `feature_registry:features:single:{key}` - Fonctionnalité individuelle
- `feature_registry:statistics` - Statistiques du registre

### Invalidation automatique

Le cache est automatiquement invalidé lors de :

- Enregistrement d'une nouvelle fonctionnalité
- Mise à jour d'une fonctionnalité existante
- Suppression d'une fonctionnalité
- Synchronisation du registre

## Versioning

### Versions API

Chaque fonctionnalité est associée à une version API :

```php
$feature->api_version = 'v1'; // Version de l'API
```

### Compatibilité mobile

Les fonctionnalités définissent leur compatibilité mobile :

```php
$feature->mobile_version_min = '1.0.0'; // Version minimale requise
$feature->mobile_version_max = '2.0.0'; // Version maximale supportée (optionnel)
```

### Logique de compatibilité

Une fonctionnalité est compatible avec une version mobile si :

1. `mobile_version >= mobile_version_min`
2. `mobile_version_max` est null OU `mobile_version <= mobile_version_max`

## Gestion des erreurs

### Exceptions personnalisées

```php
use App\Exceptions\FeatureSynchronizationException;

try {
    $registry->registerFeature($feature);
} catch (FeatureSynchronizationException $e) {
    Log::error('Feature registration failed', ['error' => $e->getMessage()]);
}
```

### Types d'erreurs

- **detectionFailed** - Échec de détection des fonctionnalités
- **registrationFailed** - Échec d'enregistrement
- **updateFailed** - Échec de mise à jour
- **featureNotFound** - Fonctionnalité non trouvée
- **synchronizationFailed** - Échec de synchronisation
- **validationFailed** - Échec de validation
- **cacheFailed** - Échec d'opération de cache

## Monitoring et observabilité

### Logs

Le registre enregistre automatiquement :

- Enregistrement/mise à jour/suppression de fonctionnalités
- Résultats de synchronisation
- Erreurs et avertissements
- Statistiques d'utilisation du cache

### Métriques

Utilisez `getStatistics()` pour obtenir :

```php
$stats = $registry->getStatistics();

[
    'total_features' => 25,
    'active_features' => 23,
    'inactive_features' => 2,
    'by_api_version' => ['v1' => 20, 'v2' => 5],
    'by_status' => ['active' => 23, 'deprecated' => 2],
    'recently_updated' => 3,
    'last_synchronization' => '2024-01-15T10:30:00Z',
    'cache_status' => [
        'manifest_cached' => true,
        'features_cached' => true,
        'cache_driver' => 'redis'
    ]
]
```

## Tests

### Tests unitaires

```bash
# Exécuter les tests du registre
php artisan test --filter=FeatureRegistryTest
```

### Tests d'intégration

```bash
# Exécuter les tests d'intégration
php artisan test --filter=FeatureRegistryIntegrationTest
```

### Factory pour les tests

```php
// Créer des fonctionnalités de test
$features = Feature::factory()->count(5)->active()->create();

// Fonctionnalité spécifique
$feature = Feature::factory()->formType()->create([
    'key' => 'test_feature',
    'mobile_version_min' => '1.0.0'
]);
```

## Configuration

### Service Provider

Le `FeatureRegistryServiceProvider` enregistre automatiquement :

- L'interface avec son implémentation
- Le registre comme singleton
- L'alias `feature.registry`

### Dépendances

Le registre nécessite :

- `FeatureDetectorInterface` - Pour la détection automatique
- `CacheManager` - Pour le système de cache
- Base de données configurée avec la table `features`

## Bonnes pratiques

### Performance

1. **Utilisez le cache** - Le registre met automatiquement en cache les résultats
2. **Synchronisation périodique** - Planifiez `features:registry sync` régulièrement
3. **Monitoring** - Surveillez les statistiques et logs

### Sécurité

1. **Permissions** - Définissez toujours les permissions requises
2. **Validation** - Validez les données avant enregistrement
3. **Audit** - Surveillez les modifications du registre

### Maintenance

1. **Nettoyage** - Supprimez les fonctionnalités obsolètes
2. **Versioning** - Maintenez la compatibilité entre versions
3. **Documentation** - Documentez les nouvelles fonctionnalités

## Intégration avec l'écosystème

### Mobile Experience Service

Le registre s'intègre avec le `MobileExperienceService` existant pour :

- Filtrer les fonctionnalités par rôle utilisateur
- Générer les manifestes personnalisés
- Configurer l'expérience mobile dynamique

### Feature Detector

Le `FeatureDetector` analyse automatiquement :

- Routes Laravel enregistrées
- Contrôleurs API avec annotations
- Métadonnées des méthodes
- Changements de signatures

### Système de cache Laravel

Utilise le cache Laravel avec support pour :

- Drivers Redis, Memcached, Database
- Tags de cache (si supportés)
- Invalidation intelligente
- Métriques de performance