# Implémentation du Registre d'Endpoints API (ApiEndpointRegistry)

> **BOS-015 (#8202)** : Renommé depuis *Feature Registry* vers *ApiEndpointRegistry* pour éliminer l'homonymie avec le `ModuleRegistry` (BOS-011) et le feature-gating tenant.

## Résumé de l'implémentation
Le système **ApiEndpointRegistry** maintient l'inventaire des endpoints API déclarés pour les applications mobiles (négociation de version, manifest versionné, compatibilité client mobile).

### ✅ Composants
1. **Interface & Implémentation Core** :
   - `ApiEndpointRegistryInterface` : interface canonique
   - `ApiEndpointRegistry` : implémentation avec cache intelligent et versioning
   - `FeatureRegistryInterface` & `FeatureRegistry` : alias `@deprecated` conservés 1 release
2. **Infrastructure Laravel** :
   - `FeatureRegistryServiceProvider` : enregistre `ApiEndpointRegistryInterface` et alias
   - `FeatureRegistryCommand` : commandes Artisan `features:registry`
   - `DemoFeatureRegistryCommand` : démonstration `features:demo`
3. **API REST** :
   - `FeatureManifestController` : endpoints `/api/v1/features/manifest`, `compatible/{version}`, etc.
4. **ADR** :
   - `docs/architecture/adr/0028-renommage-api-endpoint-registry.md`
