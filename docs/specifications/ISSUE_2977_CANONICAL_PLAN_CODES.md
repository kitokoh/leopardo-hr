# Mini-spécification — Issue #2977

## Objectif

Unifier les codes de plans entre la matrice de fonctionnalités backend, le checkout frontend, la facturation Stripe et les limites API.

## Contrat canonique

Les quatre codes persistés et échangés par les nouveaux flux sont : `free`, `pilot`, `operations` et `enterprise`. Les libellés marketing restent **Free**, **Pilot**, **Operations** et **Enterprise**.

Les alias historiques `trial`, `starter`, `business` et `scale` sont acceptés uniquement aux frontières compatibles et normalisés immédiatement vers le code canonique. Ils ne sont plus générés par le seeder ni par les nouveaux appels checkout.

## Changements

- Ajout de `PlanCode`, enum PHP unique pour les valeurs et la normalisation des alias.
- Seeder `FeaturePlanMatrixSeeder` migré vers les quatre codes canoniques et les limites Free/Pilot/Operations cohérentes avec la vitrine.
- FeatureService, FeatureFlagController, StripeService, facturation mensuelle et rate limiting normalisent les abonnements existants.
- Validations de matrice, upgrade et billing alignées sur `PlanCode::values()`.
- Checkout frontend et matrice de comparaison migrés vers `free/pilot/operations/enterprise`.
- Variables Stripe historiques conservées comme fallback de configuration pour éviter une rupture de déploiement.

## Critères d’acceptation

1. Aucun nouveau seeder ou checkout ne produit `trial`, `starter`, `business` ou `scale`.
2. Une matrice fraîche contient uniquement les quatre codes canoniques.
3. Un abonnement historique utilisant un alias continue d’être évalué via son plan canonique.
4. Les routes billing refusent les codes inconnus et acceptent les quatre codes canoniques.
5. Le checkout sandbox applique les prix Free 0 €, Pilot 29 €, Operations 99 € et Enterprise sur devis.
6. Les validations backend et frontend pertinentes passent.

## Fichiers principaux

- `api/app/Modules/Billing/Domain/Enums/PlanCode.php`
- `api/database/seeders/FeaturePlanMatrixSeeder.php`
- `api/app/Core/Feature/Infrastructure/Services/FeatureService.php`
- `api/app/Modules/Billing/Interfaces/Api/V1/FeatureFlagController.php`
- `api/app/Modules/Billing/Interfaces/Api/V1/BillingController.php`
- `api/app/Modules/Billing/Infrastructure/Services/StripeService.php`
- `api/app/Console/Commands/GenerateMonthlyInvoices.php`
- `api/app/Providers/AppServiceProvider.php`
- `api/config/security.php`, `api/config/services.php`
- `front/web/src/app/(landing)/pricing/page.tsx`
- `front/web/src/app/api/billing/checkout/route.ts`

## Plan de retour arrière

Réversion du commit puis restauration des valeurs de configuration Stripe historiques si nécessaire. Les données existantes restent lisibles grâce à `PlanCode::normalize()`.

## Trace Spec Kit

Issue : #2977  
Branche : `fix/2977-canonical-plan-codes`  
Date : 2026-08-15
