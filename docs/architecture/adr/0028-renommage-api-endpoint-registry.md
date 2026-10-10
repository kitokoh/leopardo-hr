# ADR 0028 — Renommage du registre mobile : `FeatureRegistry` → `ApiEndpointRegistry` (BOS-015)

## Statut

Acceptée (critère d'acceptation BOS-015, issue #8202).

**Date** : 2026-10-09  
**Décideurs** : Équipe architecture Leopardo (Programme Business OS, `docs/architecture/business-os/09_EXECUTION_READINESS_REVIEW.md`).

## Contexte

Dans le cadre du programme Business OS et de la livraison du registre unifié de modules et fonctionnalités `ModuleRegistry` (BOS-010 / BOS-011) :
- Une composante pré-existante du module Billing et de Core s'intitulait « Feature Registry » (`FeatureRegistryInterface`, `FeatureRegistry`, `FeatureManifestController`, table `features`).
- Or, cette composante n'effectue **aucun feature-gating** par tenant ni gestion de plan d'abonnement. Elle agit comme un **inventaire des endpoints API destiné à la négociation de compatibilité pour les applications mobiles** (manifeste versionné, versions min/max, schémas de requête/réponse).
- Cette homonymie introduisait une confusion majeure avec le registre de modules et le feature-gating tenant.

## Décision

1. **Renommage de l'interface et du service :**
   - Nouvelle interface canonique : `App\Contracts\ApiEndpointRegistryInterface`
   - Nouvelle implémentation canonique : `App\Core\Feature\Infrastructure\Services\ApiEndpointRegistry`
   - `FeatureRegistryServiceProvider` enregistre l'implémentation sous `ApiEndpointRegistryInterface` ainsi que les alias de compatibilité.
   - Les commandes console `FeatureRegistryCommand` et `DemoFeatureRegistryCommand` ainsi que le contrôleur `FeatureManifestController` injectent `ApiEndpointRegistryInterface`.

2. **Période de transition & Rétro-compatibilité :**
   - Les classes et interfaces `App\Contracts\FeatureRegistryInterface` et `App\Core\Feature\Infrastructure\Services\FeatureRegistry` sont conservées comme alias dépréciés `@deprecated` pendant **1 release** afin de garantir zéro régression pour les consommateurs éventuels.
   - L'API HTTP `/api/v1/features/manifest` reste inchangée (contrat du manifeste mobile versionné préservé).

3. **Statu quo documenté sur la table `features` :**
   - La table tenant `features` reste en place avec le trait `BelongsToCompany` afin de ne pas déclencher de migration de données coûteuse ou de rupture sur la table sous-jacente.
   - Une migration vers un schéma plateforme global `api_endpoints` sera évaluée lors de la phase de consolidation ultérieure.

## Conséquences

- **Positif** : Clarification totale du rôle du composant ; fin de la confusion avec le `ModuleRegistry` (BOS-011) et les feature flags tenant.
- **Rétro-compatibilité** : Préservée via alias de classe et d'interface durant une release complète.
